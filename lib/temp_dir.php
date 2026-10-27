<?php
/**
 * Where the web server writes its temporary files: BLAST results (the raw archive, XML,
 * pairwise, tabular), sequence-extraction batch lists, primer and alignment inputs.
 *
 * Not /tmp. On this host /tmp is a 4 GB volume, and php-fpm's PrivateTmp is only a directory
 * on it, so a few large BLAST searches at once could fill it (one left a 374 MB archive and a
 * 268 MB XML behind), and then every BLAST and sequence download fails. /var is no better
 * (4 GB, 85% full). The big disk is the one holding the web root, so the directory is a
 * HIDDEN one beside the app: nginx's moop-security.conf already refuses every path segment
 * that starts with a dot, so nothing in it is ever served.
 *
 * Also unlike PrivateTmp, the command line sees the same directory as php-fpm, so
 * housekeeping and an admin at a shell clean and inspect the same files.
 *
 * Created (apache-owned, mode 2770, SELinux httpd_sys_rw_content_t) by
 * scripts/fix_moop_selinux.sh. Until it exists and is writable, this FAILS OPEN to the system
 * temp dir, with the reason in the error log: a missing temp directory must never stop a search.
 */

/** The directory MOOP means to use: {web root}/.{site}-tmp, e.g. /var/www/html/.moop-tmp */
function moop_temp_dir_path(): string {
    $app = dirname(__DIR__);
    return dirname($app) . '/.' . basename($app) . '-tmp';
}

/** The directory to write temp files to now: moop_temp_dir_path(), or the system temp dir. */
function moop_temp_dir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;
    $want = moop_temp_dir_path();
    if (is_dir($want) && is_writable($want)) return $dir = $want;
    // is_writable() answers for whoever runs this: in a web request, apache. Logged only when the
    // directory exists but cannot be used; before setup it is simply not there yet.
    if (is_dir($want)) error_log("MOOP: temp dir $want is not writable by " . get_current_user() . '; using ' . sys_get_temp_dir());
    return $dir = sys_get_temp_dir();
}
