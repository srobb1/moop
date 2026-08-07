<?php
/**
 * Primer3 - locating the binary and its thermodynamic parameter tables.
 *
 * WHY THIS IS TWO THINGS, NOT ONE: primer3 needs a directory of thermodynamic
 * parameter tables as well as the executable, and the two are installed
 * separately and land in DIFFERENT PLACES depending on where primer3 came from:
 *
 *   Debian / Ubuntu package   /usr/bin/primer3_core   +  /etc/primer3_config/
 *   source build              /usr/local/bin/…        +  /usr/local/share/primer3_config/
 *
 * primer3's own `make install` copies the executables and NOT the tables, so
 * "primer3_core is on the PATH" says nothing about whether primer3 works. With
 * no tables it runs, exits 0, and returns
 * PRIMER_ERROR=Unable to open file …/dangle.dh on every query.
 *
 * ⚠️ THE PATH IS PASSED AS A BOULDER-IO INPUT TAG, NOT A COMMAND-LINE FLAG:
 * PRIMER_THERMODYNAMIC_PARAMETERS_PATH goes in the input record. primer3 wants
 * a TRAILING SLASH on it.
 *
 * Both settings are config-driven and both may be left empty, in which case the
 * usual locations are probed — so a deployment that installed either way works
 * without being configured, and one that installed somewhere unusual is a
 * settings change rather than a code change.
 *
 * @package MOOP\Primer
 */

class Primer3
{
    /**
     * Where the thermodynamic tables live, in the order they are checked.
     *
     * /etc first because that is the Debian and Ubuntu package layout, and a
     * host with a package almost certainly wants it over a stray source build.
     */
    const CONFIG_CANDIDATES = [
        '/etc/primer3_config',
        '/usr/local/share/primer3_config',
        '/usr/share/primer3_config',
        '/usr/share/primer3/primer3_config',
    ];

    /**
     * One of the files primer3 actually opens.
     *
     * Probed instead of just is_dir(): an empty primer3_config/ directory is a
     * real state — it is what a half-finished install or a failed copy leaves —
     * and it would pass a directory check while failing every query.
     */
    const SENTINEL = 'dangle.dh';

    /**
     * Full path to primer3_core, or '' if it cannot be found.
     *
     * A configured value that is an absolute path is used as given; anything
     * else is looked up on PATH, then in /usr/local/bin, which php-fpm's
     * restricted PATH often misses.
     */
    public static function binary()
    {
        $config = ConfigManager::getInstance();
        $name   = $config->getArray('primer3_tools')['primer3_core'] ?? 'primer3_core';

        if (strpos($name, '/') === 0) {
            return is_executable($name) ? $name : '';
        }

        $found = trim((string)@shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($found !== '') {
            return $found;
        }
        foreach (['/usr/local/bin/', '/usr/bin/'] as $dir) {
            if (is_executable($dir . $name)) {
                return $dir . $name;
            }
        }
        return '';
    }

    /**
     * Directory holding the thermodynamic tables, WITH a trailing slash, or ''.
     *
     * Configured value wins. Empty means auto-detect, which is the default:
     * hardcoding one path would be wrong on whichever of the two install
     * routes the deployment did not take.
     */
    public static function configPath()
    {
        $configured = trim((string)ConfigManager::getInstance()->getString('primer3_config_path'));

        $candidates = $configured !== ''
            ? [rtrim($configured, '/')]
            : self::CONFIG_CANDIDATES;

        foreach ($candidates as $dir) {
            if (is_readable($dir . '/' . self::SENTINEL)) {
                return $dir . '/';   // primer3 wants the trailing slash
            }
        }
        return '';
    }

    /**
     * Is primer3 usable? Both halves, because either alone is not enough.
     *
     * @return array{ok:bool, binary:string, config:string, problem:string}
     *         'problem' distinguishes the two failures, because they need
     *         different fixes and one of them looks like success from outside.
     */
    public static function status()
    {
        $binary = self::binary();
        if ($binary === '') {
            return [
                'ok' => false, 'binary' => '', 'config' => '',
                'problem' => 'missing',
            ];
        }

        $config = self::configPath();
        if ($config === '') {
            return [
                'ok' => false, 'binary' => $binary, 'config' => '',
                'problem' => 'no_parameters',
            ];
        }

        return ['ok' => true, 'binary' => $binary, 'config' => $config, 'problem' => ''];
    }
}
