<?php
/**
 * Filesystem permission checks — shared collector.
 *
 * The permission rules (which paths need which perms/owner/group) and the
 * organism-tree walk used to live inline in admin/manage_filesystem_permissions.php.
 * They are extracted here so TWO callers can produce the SAME result without the
 * logic drifting (the same reason computeDataHealthAlerts() is shared):
 *
 *   1. admin/manage_filesystem_permissions.php — the full detail page.
 *   2. lib/housekeeping.php — a once-per-interval aggregate count for the dashboard
 *      pointer card (see PAGE_BY_PAGE_AUDIT_PLAN §N). This walk stat()s many files,
 *      so it must NOT run on every dashboard load — housekeeping runs it at most once
 *      per HOUSEKEEPING_MIN_INTERVAL and persists the count.
 *
 * Pure logic: defines functions only, no include-time side effects. All helper
 * calls (getWebServerUser/getMoopOwner/moop_organism_cache_file) happen at call time,
 * by which point admin_init has loaded them for both callers.
 */

/**
 * How to judge a path — by IMPACT, not by an exact permission string.
 *
 * The old checker demanded an exact mode (e.g. FASTA must be 644). After the SELinux
 * hardening most data files are 660 (group `apache`, no world) — the web server reads
 * them fine, and NOT being world-readable is *safer* for restricted data. So exact-644
 * flagged hundreds of files that aren't actually problems. Instead we classify each rule
 * and check what actually matters:
 *
 *   'data'     — read-only files the web serves (FASTA, genome, SQLite). Fine as long as
 *                the web user can READ it, it is not world-writable, and it is not
 *                executable. 640/660/664/644 all pass; 666/777/exec fail.
 *   'writable' — dirs/files the web maintains (logs, caches, config, metadata, images,
 *                the organism tree). Must be writable by the web user, correct group
 *                (so new files inherit it), and not world-writable.
 *   'secret'   — private key material (JWT). Must be readable by the web user and NOT
 *                exposed to other users on the host.
 */
function moop_permission_check_mode(string $name): string {
    // 'secret' — private key material.
    if ($name === 'JWT Key Files' || $name === 'JWT Certificates Directory') return 'secret';

    // 'writable' — the ONLY paths the web server (apache/httpd_t) legitimately writes,
    // straight from the table in docs/SELINUX_AND_HARDENING.md §55. Everything else is
    // read-only served content — including the whole organisms/ tree except organism.json
    // (§73) and, in Phase 2, the index/ subdir.
    static $writable = [
        'Logs Directory'                  => 1,  // error.log, login_attempts.json
        'Site Configuration Files'        => 1,  // config/config_editable.json (admin UI)
        'Metadata Configuration Files'    => 1,  // metadata/*.json
        'Taxonomy Lineage Cache'          => 1,  // metadata/taxonomy_lineage_cache.json
        'Metadata Directory'              => 1,
        'Metadata Backups Directory'      => 1,
        'Change Log Directory'            => 1,
        'JBrowse2 Track Config Directory' => 1,  // metadata/jbrowse2-configs
        'Genome Data Directory'           => 1,  // data/genomes — gff.gz + tabix
        'Cache Directory'                 => 1,  // cache_path
        'Organism Cache File'             => 1,  // under cache_path
        'NCBI Taxonomy Images Cache'      => 1,  // images/ncbi_taxonomy (php downloads)
        'Wikimedia Images Cache'          => 1,  // images/wikimedia (php downloads)
        'Organism Metadata Files'         => 1,  // organisms/*/organism.json — narrow exception (§73)
    ];
    if (isset($writable[$name])) return 'writable';

    // Default: read-only served content (FASTA/genome/SQLite, the organism tree,
    // top-level images/, banners, docs, data/tracks, jbrowse2/ app dir).
    return 'data';
}

/**
 * Is SELinux in enforcing mode? (Cached; one getenforce per process.)
 * When enforcing, the SELinux label — not the Unix mode — is what actually decides
 * whether httpd_t can write. is_writable() sees only DAC, so a dir can be
 * is_writable()==true yet unwritable by php-fpm (the §O "false-green" trap).
 */
function moop_selinux_enforcing(): bool {
    static $enf = null;
    if ($enf !== null) return $enf;
    // Read the kernel state directly — PATH-independent. php-fpm runs with a restricted
    // PATH (often no /usr/sbin, where getenforce lives), so shell_exec('getenforce') would
    // return empty under the web server and silently disable the label check.
    $f = @file_get_contents('/sys/fs/selinux/enforce');
    if ($f !== false && $f !== '') return $enf = (trim($f) === '1');
    // Fallback: getenforce by absolute path.
    foreach (['/usr/sbin/getenforce', '/sbin/getenforce'] as $bin) {
        if (is_executable($bin)) {
            $out = @trim((string) shell_exec(escapeshellarg($bin) . ' 2>/dev/null'));
            return $enf = (strcasecmp($out, 'Enforcing') === 0);
        }
    }
    return $enf = false;
}

/**
 * SELinux *type* label of a path (e.g. httpd_sys_rw_content_t), or null if SELinux
 * context is unavailable. Uses `ls -dZ` — PHP has no native SELinux API.
 */
function moop_selinux_type(string $path): ?string {
    $out = [];
    @exec('ls -dZ ' . escapeshellarg($path) . ' 2>/dev/null', $out);
    if (empty($out[0])) return null;
    // context: user_u:object_r:<type>:s0
    return preg_match('/:([a-z0-9_]+_t):/', $out[0], $m) ? $m[1] : null;
}

/**
 * Collapse a per-path check name into a category so findings aggregate:
 * one "Sequence files (FASTA)" finding, not 233. Fixed rules are their own category.
 */
function moop_permission_category(string $name): string {
    if (str_starts_with($name, 'FASTA File:'))            return 'Sequence files (FASTA)';
    if (str_starts_with($name, 'Assembly Subdirectory:')) return 'Assembly directories';
    if (str_starts_with($name, 'Gene Set Subdirectory:')) return 'Gene-set directories';
    if ($name === 'Organism Directory')                   return 'Organism directories';
    return $name;
}

/**
 * Is this a data-TREE path (organism/assembly/gene-set/genome/jbrowse/tracks)?
 * Used to soften the severity of "not writable": the web only writes here during
 * occasional admin builds (BLAST index / rename), which are run from the compute
 * server anyway — so it is a future-build concern (medium), not a live breakage.
 */
function moop_permission_is_data_tree(string $name): bool {
    if (str_starts_with($name, 'Assembly Subdirectory:')) return true;
    if (str_starts_with($name, 'Gene Set Subdirectory:')) return true;
    return in_array($name, [
        'Organism Data Directories', 'Organism Directory', 'Genome Data Directory',
        'JBrowse2 App Directory', 'Track Data Directory',
    ], true);
}

/**
 * Check one path by IMPACT and assign a severity.
 *
 * Result keys mirror the old shape (name/path/exists/current_perms/current_owner/
 * current_group/is_readable/is_writable/issues) plus 'check_mode', 'category', and
 * 'severity' (high|medium|low). 'issues' empty = OK.
 *
 * Severity (per the user's definition — impact for users & logging):
 *   high   — the site is broken now: web can't read a file it serves, can't write
 *            logs/caches/config, or a secret is exposed.
 *   medium — works today, will bite a future admin action (data dir not web-writable),
 *            or wrong group so new files inherit it.
 *   low    — cosmetic only (accessible, not exposed).
 */
function performPermissionCheck($path, $item, $web_group = 'www-data') {
    $mode = $item['check_mode'] ?? moop_permission_check_mode($item['name']);
    $result = [
        'name' => $item['name'],
        'path' => $path,
        'exists' => file_exists($path),
        'type' => $item['type'],
        'required_perms' => $item['required_perms'],
        'required_group' => $item['required_group'] ?? $web_group,
        'reason' => $item['reason'] ?? '',
        'why_write' => $item['why_write'] ?? '',
        'sticky_bit' => $item['sticky_bit'] ?? false,
        'check_mode' => $mode,
        'category' => moop_permission_category($item['name']),
        'severity' => 'low',
        'issues' => [],
    ];

    if (!$result['exists']) {
        $result['issues'][] = 'Path does not exist';
        $result['severity'] = 'medium';
        return $result;
    }

    $perms_full = substr(sprintf('%o', fileperms($path)), -4);
    // Remove leading zero for comparison (0664 -> 664, 02775 -> 2775)
    $perms = ltrim($perms_full, '0') ?: '0';
    $mode_bits = octdec($perms_full) & 07777; // numeric perm bits for bit tests
    $file_uid = fileowner($path);
    $file_gid = filegroup($path);
    $owner = 'unknown';
    $group = 'unknown';
    if (function_exists('posix_getpwuid')) {
        $pw = posix_getpwuid($file_uid);
        if ($pw) { $owner = $pw['name']; }
    }
    if (function_exists('posix_getgrgid')) {
        $gr = posix_getgrgid($file_gid);
        if ($gr) { $group = $gr['name']; }
    }
    // Fallback: use stat command if posix not available
    if ($owner === 'unknown' || $group === 'unknown') {
        $stat_out = [];
        @exec("stat -c '%U:%G' " . escapeshellarg($path) . " 2>/dev/null", $stat_out);
        if (!empty($stat_out[0])) {
            $parts = explode(':', $stat_out[0]);
            if ($owner === 'unknown' && !empty($parts[0])) { $owner = $parts[0]; }
            if ($group === 'unknown' && !empty($parts[1])) { $group = $parts[1]; }
        }
    }

    $result['current_perms'] = $perms;
    $result['current_owner'] = $owner;
    $result['current_group'] = $group;
    $result['is_readable'] = is_readable($path);
    $result['is_writable'] = is_writable($path);

    $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
    $sev  = 'low';
    $bump = function (string $s) use (&$sev, $rank) { if ($rank[$s] > $rank[$sev]) $sev = $s; };

    $world_writable = ($mode_bits & 0002) === 0002;
    $any_exec       = ($mode_bits & 0111) !== 0;
    $world_any      = ($mode_bits & 0007) !== 0;

    if ($mode === 'data') {
        // Read-only data the web serves. World-readability is NOT required.
        if (!$result['is_readable']) {
            $result['issues'][] = "Not readable by the web server ($web_group) — BLAST and sequence views will fail";
            $bump('high');
        }
        if ($world_writable) {
            $result['issues'][] = "World-writable ($perms) — any user on the host can overwrite it";
            $bump('high');
        }
        if ($any_exec && $result['type'] !== 'directory') {
            // Directories legitimately carry the traverse (x) bit; only flag executable FILES.
            $result['issues'][] = "Marked executable ($perms) — data files should not be executable";
            $bump('medium');
        }
    } elseif ($mode === 'secret') {
        if (!$result['is_readable']) {
            $result['issues'][] = "Not readable by the web server — JWT signing/verification will fail";
            $bump('high');
        }
        if ($world_any) {
            $result['issues'][] = "Accessible to other users on the host ($perms) — private key should not be world-accessible";
            $bump('high');
        }
    } else { // 'writable'
        // Under enforcing SELinux the LABEL decides writability, not the Unix mode —
        // is_writable() below sees only DAC and would false-green a wrong label.
        if (moop_selinux_enforcing()) {
            $type = moop_selinux_type($path);
            if ($type !== null && $type !== 'httpd_sys_rw_content_t') {
                $result['issues'][] = "SELinux label is '$type' — php-fpm (httpd_t) cannot write here regardless of Unix perms; needs httpd_sys_rw_content_t (see docs/SELINUX_AND_HARDENING.md / scripts/fix_moop_selinux.sh)";
                $bump('high');
            }
        }
        if (!$result['is_writable']) {
            $is_tree = moop_permission_is_data_tree($item['name']);
            $result['issues'][] = "Not writable by the web server ($web_group)"
                . ($is_tree ? ' — admin builds/renames into this tree will fail' : ' — admin saves/logging/caching here will fail');
            $bump($is_tree ? 'medium' : 'high');
        }
        if (isset($item['required_group']) && $group !== $item['required_group']) {
            $result['issues'][] = "Group is $group, should be " . $item['required_group'] . ' — new files here inherit the wrong group';
            $bump('medium');
        }
        if ($world_writable) {
            $result['issues'][] = "World-writable ($perms)";
            $bump('high');
        }
    }

    $result['severity'] = empty($result['issues']) ? 'low' : $sev;
    return $result;
}

/**
 * Build the list of fixed permission rules (directories + specific files).
 *
 * @param ConfigManager $config
 * @param array $ctx  ['web_group' => string, 'moop_owner' => string, 'metadata_path' => string,
 *                     'organism_data' => string, 'cache_path' => string, 'site_path' => string,
 *                     'absolute_images_path' => string, 'docs_path' => string]
 */
function moop_build_permission_items($config, array $ctx): array {
    $web_group            = $ctx['web_group'];
    $moop_owner           = $ctx['moop_owner'];
    $metadata_path        = $ctx['metadata_path'];
    $organism_data        = $ctx['organism_data'];
    $cache_path           = $ctx['cache_path'];
    $site_path            = $ctx['site_path'];
    $absolute_images_path = $ctx['absolute_images_path'];
    $docs_path            = $ctx['docs_path'];

    return [
        // Site Configuration Files - Require Write
        [
            'name' => 'Site Configuration Files',
            'description' => 'Site configuration files edited through admin interface',
            'type' => 'file',
            'paths' => [
                $config->getPath('root_path') . '/' . $config->getString('site') . '/config/config_editable.json',
            ],
            'required_perms' => '664',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Site configuration is edited by admins through the web interface',
            'why_write' => 'Admin interface needs to save changed site settings (title, email, etc.)',
        ],

        // Metadata Configuration Files - Require Write
        [
            'name' => 'Metadata Configuration Files',
            'description' => 'JSON configuration files for annotations, taxonomy, and groups',
            'type' => 'file',
            'paths' => [
                $metadata_path . '/annotation_config.json',
                $metadata_path . '/taxonomy_tree_config.json',
                $metadata_path . '/group_descriptions.json',
                $metadata_path . '/organism_assembly_groups.json',
            ],
            'required_perms' => '664',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Configuration files are edited by admins and read by the web server',
            'why_write' => 'Admin interface needs to modify these files when you change settings',
        ],

        // Taxonomy Lineage Cache - Write Required
        [
            'name' => 'Taxonomy Lineage Cache',
            'description' => 'Permanent per-organism NCBI lineage cache; written by cache refresh and taxonomy tree generation',
            'type' => 'file',
            'paths' => [
                $metadata_path . '/taxonomy_lineage_cache.json',
            ],
            'required_perms' => '664',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Written by background cache refresh process (apache) and admin taxonomy tree page',
            'why_write' => 'Cache refresh and "Rebuild Tree" store NCBI lineage data here so subsequent runs need no network calls',
        ],

        // Metadata Directory - SGID for Group Assignment
        [
            'name' => 'Metadata Directory',
            'description' => 'Parent directory for all configuration files',
            'type' => 'directory',
            'paths' => [$metadata_path],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit (shown as \'s\' in permissions) ensures new files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server needs to create/write files here. SGID ensures group is always ' . $web_group . ' without manual fixes',
            'sgid_bit' => true,
        ],

        // Organism Directories
        [
            'name' => 'Organism Data Directories',
            'description' => 'Parent directory and subdirectories for all organisms',
            'type' => 'directory',
            'paths' => [
                $organism_data,
            ],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server needs to read databases, organism.json files, and RENAME/MOVE assembly subdirectories during admin operations',
            'sgid_bit' => true,
        ],

        // Organism.json Files - Require Write
        [
            'name' => 'Organism Metadata Files',
            'description' => 'JSON files describing each organism (genus, species, images, etc.)',
            'type' => 'file_pattern',
            'pattern' => 'organisms/*/organism.json',
            'required_perms' => '664',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Edited by admin interface, read by web server',
            'why_write' => 'Admin can update organism metadata (descriptions, images, feature types)',
        ],

        // Organism Cache File - Write Required
        [
            'name' => 'Organism Cache File',
            'description' => 'JSON cache of all organism metadata, written by background cache refresh process',
            'type' => 'file',
            'paths' => [
                moop_organism_cache_file(),
            ],
            'required_perms' => '664',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Written by background cache refresh process run as web server user',
            'why_write' => 'The "Update Cache" background process (run as apache) must be able to overwrite this file',
        ],

        // Cache Directory - Write Required (only when cache_path is configured)
        [
            'name' => 'Cache Directory',
            'description' => 'Directory for generated caches (organism scan, annotation counts, chromosome names, annotated feature types). All contents are regenerable.',
            'type' => 'directory',
            // Empty when cache_path is unset — the loop skips entries with no paths,
            // and the in-tree caches are covered by the organism directory checks above.
            'paths' => ($cache_path !== '' ? [$cache_path] : []),
            'required_perms' => '2775',
            'required_owner' => 'apache',
            'required_group' => $web_group,
            'reason' => 'SGID bit forces new cache files to group ' . $web_group . ' so both php-fpm (apache) and CLI scripts (in group ' . $web_group . ') can read and write them',
            'why_write' => 'Web server writes caches here; keeping them outside organisms/ lets that data tree stay read-only',
            'sgid_bit' => true,
        ],

        // Database Files - Read Only
        [
            'name' => 'SQLite Database Files',
            'description' => 'Database files containing feature, annotation, and genome data',
            'type' => 'file_pattern',
            'pattern' => 'organisms/*/organism.sqlite',
            'required_perms' => '644',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Web server reads data; files are pre-built and not modified',
            'why_write' => 'Database files must be readable by web server but typically not written to',
        ],

        // Logs Directory - Write Required
        [
            'name' => 'Logs Directory',
            'description' => 'Application log files for debugging and monitoring',
            'type' => 'directory',
            'paths' => [$site_path . '/logs'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new log files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server writes error and debug logs here',
            'sgid_bit' => true,
        ],

        // Images Directory - Write for Uploads
        [
            'name' => 'Images Directory',
            'description' => 'Organism images and banners displayed on web pages',
            'type' => 'directory',
            'paths' => [$absolute_images_path],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new image files automatically get ' . $web_group . ' as group',
            'why_write' => 'Admin may upload new organism images via web interface',
            'sgid_bit' => true,
        ],

        // NCBI Taxonomy Images Cache - Write for Downloaded Images
        [
            'name' => 'NCBI Taxonomy Images Cache',
            'description' => 'Cached images downloaded from NCBI taxonomy database',
            'type' => 'directory',
            'paths' => [$absolute_images_path . '/ncbi_taxonomy'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures downloaded taxonomy images automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server downloads and caches organism images from NCBI when generating taxonomy tree',
            'sgid_bit' => true,
        ],

        // Wikimedia Images Cache - Write for Downloaded Images (docs/SELINUX_AND_HARDENING.md §55)
        [
            'name' => 'Wikimedia Images Cache',
            'description' => 'Cached organism images downloaded from Wikipedia/Wikimedia',
            'type' => 'directory',
            'paths' => [$absolute_images_path . '/wikimedia'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures downloaded Wikipedia images automatically get ' . $web_group . ' as group',
            'why_write' => 'php-fpm (apache) downloads and caches organism images from Wikimedia here — needs httpd_sys_rw_content_t',
            'sgid_bit' => true,
        ],

        // Banner Images Directory - Write for Upload/Delete
        [
            'name' => 'Banner Images Directory',
            'description' => 'Banner images managed through site configuration interface',
            'type' => 'directory',
            'paths' => [$absolute_images_path . '/banners'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new banner files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server uploads new banners and deletes old ones through admin interface. Existing files also need 664 permissions.',
            'sgid_bit' => true,
        ],

        // Genome Data Directory
        [
            'name' => 'Genome Data Directory',
            'description' => 'Reference genomes and annotations per organism/assembly',
            'type' => 'directory',
            'paths' => [$config->getPath('genomes_directory')],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new genome files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server reads genome files for JBrowse2 and BLAST; admin may upload new assemblies',
            'sgid_bit' => true,
        ],

        // JBrowse2 App Directory (parent of all trix index subdirectories)
        [
            'name' => 'JBrowse2 App Directory',
            'description' => 'Root of the JBrowse2 installation; Apache creates per-assembly trix index subdirectories here',
            'type' => 'directory',
            'paths' => [$site_path . '/jbrowse2'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures that {organism}/{assembly}/trix/ subdirectories created by Apache automatically inherit the ' . $web_group . ' group; without it Apache cannot create per-assembly trix directories',
            'why_write' => 'jbrowse text-index writes per-assembly trix files to jbrowse2/{organism}/{assembly}/trix/ — Apache must be able to create those subdirectories at runtime',
            'sgid_bit' => true,
        ],

        // JBrowse2 Track Config Directory
        [
            'name' => 'JBrowse2 Track Config Directory',
            'description' => 'Per-assembly track JSON config files generated by Sync Tracks',
            'type' => 'directory',
            'paths' => [$metadata_path . '/jbrowse2-configs'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures track JSON files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server creates track JSON files during Sync Tracks and updates them when text-index is built',
            'sgid_bit' => true,
        ],

        // Track Data Directory
        [
            'name' => 'Track Data Directory',
            'description' => 'Additional track files (BigWig, BAM, VCF, etc.) served via JWT authentication',
            'type' => 'directory',
            'paths' => [$config->getPath('tracks_directory')],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new track files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server reads track files through api/jbrowse2/tracks.php; admin may add new tracks',
            'sgid_bit' => true,
        ],

        // JWT Certificates Directory
        [
            'name' => 'JWT Certificates Directory',
            'description' => 'Private and public keys for JBrowse2 track authentication',
            'type' => 'directory',
            'paths' => [$config->getPath('certs_directory')],
            'required_perms' => '2750',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new certificate files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server reads keys to sign/verify JWT tokens for track access',
            'sgid_bit' => true,
        ],

        // JWT Key Files
        [
            'name' => 'JWT Key Files',
            'description' => 'RSA private and public keys used to sign JBrowse2 track tokens',
            'type' => 'file',
            'paths' => [
                $config->getPath('jwt_private_key'),
                $config->getPath('jwt_public_key'),
            ],
            'required_perms' => '640',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'Private key must never be world-readable; web server needs read access to sign tokens',
            'why_write' => 'Keys are generated once during setup and only read thereafter',
        ],

        // Documentation Directory
        [
            'name' => 'Documentation Directory',
            'description' => 'README files and documentation for the system',
            'type' => 'directory',
            'paths' => [$docs_path],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures new documentation files automatically get ' . $web_group . ' as group',
            'why_write' => 'Docs may be updated through admin interface',
            'sgid_bit' => true,
        ],

        // Backups Directory
        [
            'name' => 'Metadata Backups Directory',
            'description' => 'Automatic backups of configuration files',
            'type' => 'directory',
            'paths' => [$metadata_path . '/backups'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures backup files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server creates backup files when configs are updated',
            'sgid_bit' => true,
        ],

        // Change Log Directory
        [
            'name' => 'Change Log Directory',
            'description' => 'Records of changes made through admin interface',
            'type' => 'directory',
            'paths' => [$metadata_path . '/change_log'],
            'required_perms' => '2775',
            'required_owner' => $moop_owner,
            'required_group' => $web_group,
            'reason' => 'SGID (Set-Group-ID) bit ensures change log files automatically get ' . $web_group . ' as group',
            'why_write' => 'Web server logs all admin actions for auditing',
            'sgid_bit' => true,
        ],
    ];
}

/**
 * Run every permission check and return the full result set.
 *
 * @return array{
 *   checks: array, assembly_subdir_issues: array, fasta_file_issues: array,
 *   web_user: string, web_group: string, moop_owner: string, organism_data: string
 * }
 */
function moop_collect_permission_checks($config): array {
    $organism_data        = $config->getPath('organism_data');
    $metadata_path        = $config->getPath('metadata_path');
    $cache_path           = $config->getPath('cache_path'); // empty = caches live in organisms/
    $absolute_images_path = $config->getPath('absolute_images_path');
    $site_path            = $config->getPath('site_path');
    $docs_path            = $config->getPath('docs_path');

    $webserver = getWebServerUser();
    $web_user  = $webserver['user'];
    $web_group = $webserver['group'];
    $moop_owner = getMoopOwner();

    $permission_items = moop_build_permission_items($config, [
        'web_group'            => $web_group,
        'moop_owner'           => $moop_owner,
        'metadata_path'        => $metadata_path,
        'organism_data'        => $organism_data,
        'cache_path'           => $cache_path,
        'site_path'            => $site_path,
        'absolute_images_path' => $absolute_images_path,
        'docs_path'            => $docs_path,
    ]);

    // Check permissions for each fixed item
    $checks = [];
    foreach ($permission_items as $item) {
        if ($item['type'] === 'directory' || ($item['type'] === 'file' && !isset($item['pattern']))) {
            foreach ($item['paths'] ?? [] as $path) {
                $checks[] = performPermissionCheck($path, $item, $web_group);
            }
        }
    }

    // Check assembly subdirectories and FASTA files
    $assembly_subdir_issues = [];
    $fasta_file_issues = [];
    if (is_dir($organism_data)) {
        foreach (scandir($organism_data) as $organism) {
            if ($organism !== '.' && $organism !== '..' && is_dir($organism_data . '/' . $organism)) {
                $org_path = $organism_data . '/' . $organism;

                // Check organism subdirectory itself
                $check = performPermissionCheck($org_path, [
                    'name' => 'Organism Directory',
                    'type' => 'directory',
                    'required_perms' => '2775',
                    'required_group' => $web_group,
                    'reason' => 'SGID required for assembly rename operations',
                    'why_write' => 'Web server needs to rename/move assembly subdirectories',
                ], $web_group);

                if (!empty($check['issues'])) {
                    $assembly_subdir_issues[] = $check;
                }

                // Check assembly subdirectories and FASTA files
                foreach (scandir($org_path) as $item) {
                    $item_path = $org_path . '/' . $item;

                    // Skip dots and files
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    if (is_dir($item_path)) {
                        // Check assembly subdirectory
                        $check = performPermissionCheck($item_path, [
                            'name' => 'Assembly Subdirectory: ' . $organism . '/' . $item,
                            'type' => 'directory',
                            'required_perms' => '2775',
                            'required_group' => $web_group,
                            'reason' => 'Web server needs write access to rename gene set subdirectories within this assembly dir',
                            'why_write' => 'Gene set rename operations require group-write on the parent assembly directory',
                        ], $web_group);

                        if (!empty($check['issues'])) {
                            $assembly_subdir_issues[] = $check;
                        }

                        // Check gene_set subdirectories and their FASTA files
                        $sequence_types = $config->getSequenceTypes();
                        foreach (scandir($item_path) as $gs_item) {
                            $gs_path = $item_path . '/' . $gs_item;
                            if ($gs_item === '.' || $gs_item === '..') {
                                continue;
                            }

                            if (is_dir($gs_path)) {
                                // Check gene_set subdirectory
                                $check = performPermissionCheck($gs_path, [
                                    'name' => 'Gene Set Subdirectory: ' . $organism . '/' . $item . '/' . $gs_item,
                                    'type' => 'directory',
                                    'required_perms' => '2775',
                                    'required_group' => $web_group,
                                    'reason' => 'Web server needs to write BLAST index files into gene set directories',
                                    'why_write' => 'BLAST indexes (.nhr, .nin, .nsq, .phr, .pin, .psq) must be writable by web server',
                                ], $web_group);

                                if (!empty($check['issues'])) {
                                    $assembly_subdir_issues[] = $check;
                                }

                                // Check FASTA files in gene_set directory
                                foreach ($sequence_types as $seq_type => $seq_config) {
                                    $pattern = $seq_config['pattern'] ?? '';
                                    if (empty($pattern)) {
                                        continue;
                                    }
                                    $expected_file = basename($pattern);
                                    $fasta_path = $gs_path . '/' . $expected_file;
                                    if (file_exists($fasta_path)) {
                                        $check = performPermissionCheck($fasta_path, [
                                            'name' => 'FASTA File: ' . $organism . '/' . $item . '/' . $gs_item . '/' . $expected_file,
                                            'type' => 'file',
                                            'required_perms' => '644',
                                            'required_group' => $web_group,
                                            'reason' => ucfirst($seq_type) . ' file must be readable by web server for BLAST',
                                            'why_write' => 'Web server reads ' . $seq_type . ' files to run BLAST searches',
                                        ], $web_group);
                                        if (!empty($check['issues'])) {
                                            $fasta_file_issues[] = $check;
                                        }
                                    }
                                }
                            } else {
                                // Files at assembly level (genome.fa, genome.fa.fai) — check genome type only
                                foreach ($sequence_types as $seq_type => $seq_config) {
                                    $pattern = $seq_config['pattern'] ?? '';
                                    if (empty($pattern)) {
                                        continue;
                                    }
                                    $expected_file = basename($pattern);
                                    if ($gs_item === $expected_file && file_exists($gs_path)) {
                                        $check = performPermissionCheck($gs_path, [
                                            'name' => 'FASTA File: ' . $organism . '/' . $item . '/' . $gs_item,
                                            'type' => 'file',
                                            'required_perms' => '644',
                                            'required_group' => $web_group,
                                            'reason' => ucfirst($seq_type) . ' file must be readable by web server',
                                            'why_write' => 'Web server reads ' . $seq_type . ' files for JBrowse2 and sequence views',
                                        ], $web_group);
                                        if (!empty($check['issues'])) {
                                            $fasta_file_issues[] = $check;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return [
        'checks'                 => $checks,
        'assembly_subdir_issues' => $assembly_subdir_issues,
        'fasta_file_issues'      => $fasta_file_issues,
        'web_user'               => $web_user,
        'web_group'              => $web_group,
        'moop_owner'             => $moop_owner,
        'organism_data'          => $organism_data,
    ];
}

/**
 * Aggregate per-path checks into CATEGORY-level findings.
 *
 * A section with 233 drifted FASTA files is ONE finding ("Sequence files (FASTA) —
 * 233 affected"), not 233 — so the count reflects "how many things are wrong", not
 * "how many files exist". Each finding carries the worst severity in its category, how
 * many paths are affected, the distinct reasons, and one example path.
 *
 * @return list<array{category:string, severity:string, count:int, reasons:list<string>, example:?string}>
 *         sorted worst-severity first, then most-affected first.
 */
function moop_permission_findings($config, ?array $collected = null): array {
    if ($collected === null) {
        $collected = moop_collect_permission_checks($config);
    }
    $all = array_merge(
        $collected['checks'],
        $collected['assembly_subdir_issues'],
        $collected['fasta_file_issues']
    );
    $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
    $cats = [];
    foreach ($all as $c) {
        if (empty($c['issues'])) continue;
        $cat = $c['category'] ?? $c['name'];
        if (!isset($cats[$cat])) {
            $cats[$cat] = ['category' => $cat, 'severity' => 'low', 'count' => 0, 'reasons' => [], 'example' => null];
        }
        $cats[$cat]['count']++;
        $s = $c['severity'] ?? 'medium';
        if (!isset($rank[$s])) $s = 'medium';
        if ($rank[$s] > $rank[$cats[$cat]['severity']]) $cats[$cat]['severity'] = $s;
        foreach ($c['issues'] as $iss) $cats[$cat]['reasons'][$iss] = true;
        if ($cats[$cat]['example'] === null) $cats[$cat]['example'] = $c['path'] ?? null;
    }
    $findings = [];
    foreach ($cats as $d) {
        $d['reasons'] = array_keys($d['reasons']);
        $findings[] = $d;
    }
    usort($findings, fn($a, $b) => ($rank[$b['severity']] <=> $rank[$a['severity']]) ?: ($b['count'] <=> $a['count']));
    return $findings;
}

/**
 * Compact summary for the dashboard pointer card — built from CATEGORY findings, not
 * raw file counts. high/medium/low are how many *areas* (findings) sit at each severity;
 * affected_files is the underlying path total if it's ever wanted. See §N.
 *
 * @return array{findings:array, finding_count:int, high:int, medium:int, low:int, affected_files:int, worst:?string, checked_at:string}
 */
function moop_permission_issue_summary($config, ?array $collected = null): array {
    if ($collected === null) {
        $collected = moop_collect_permission_checks($config);
    }
    $findings = moop_permission_findings($config, $collected);
    $sev = ['high' => 0, 'medium' => 0, 'low' => 0];
    $affected = 0;
    foreach ($findings as $f) {
        $sev[$f['severity']]++;
        $affected += $f['count'];
    }
    $worst = $sev['high'] > 0 ? 'high' : ($sev['medium'] > 0 ? 'medium' : ($sev['low'] > 0 ? 'low' : null));
    return [
        'findings'       => $findings,
        'finding_count'  => count($findings),
        'high'           => $sev['high'],
        'medium'         => $sev['medium'],
        'low'            => $sev['low'],
        'affected_files' => $affected,
        'worst'          => $worst,
        'checked_at'     => date('Y-m-d H:i:s'),
    ];
}
