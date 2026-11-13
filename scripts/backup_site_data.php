<?php
/**
 * Back up this site's data NOW -- the same copy housekeeping makes every hour
 * (housekeeping_snapshot_site_data(): config, metadata, pages, translations, images,
 * css/custom.css, users.json) into the site-data backup directory.
 *
 *     php scripts/backup_site_data.php            # from the site's checkout, as smr
 *     php /var/www/html/planosphere/scripts/backup_site_data.php
 *
 * Use it after editing site data by hand (a site's css/custom.css is the usual case), so the
 * backup -- and its git repo -- has the change without waiting for the next admin page load.
 * It does not commit: committing the backup stays a manual step (CLAUDE.md, "How snapshots work").
 *
 * Runs as you, not the web server: copies are written 660 in 2770 directories (group = the web
 * group), so the web server can still overwrite them. The housekeeping status file in logs/ is
 * left alone -- the dashboard updates it on its next run.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

umask(0007);   // anything created here: group-writable, never world-readable

$base = dirname(__DIR__);
require_once $base . '/includes/config_init.php';
require_once $base . '/lib/housekeeping.php';

$status = housekeeping_snapshot_site_data(false);
if ($status === null) {
    fwrite(STDERR, "backup_site_data: no site_data_path configured for this site\n");
    exit(1);
}

echo "Backup directory: {$status['path']}\n";
if (($status['status'] ?? '') === 'error' && empty($status['files_failed'])) {
    fwrite(STDERR, 'backup_site_data: ' . strip_tags($status['message'] ?? 'failed') . "\n");
    exit(1);
}
echo 'Copied: ' . (int)($status['files_copied'] ?? 0) . ' file(s)'
   . (!empty($status['files_archived']) ? ', archived ' . (int)$status['files_archived'] . ' stale copy(ies)' : '') . "\n";

if (!empty($status['files_failed'])) {
    fwrite(STDERR, "FAILED to write (owned by another user, not group-writable):\n");
    foreach ($status['files_failed'] as $f) {
        fwrite(STDERR, "  $f\n");
    }
    fwrite(STDERR, "One-time fix:  sudo find " . escapeshellarg($status['path']) . " -path '*/.git' -prune -o -type d -exec chmod 2770 {} + -o -type f -exec chmod 660 {} +\n");
    exit(1);
}

if (!empty($status['is_git'])) {
    echo "\nIt is a git repo. To version this snapshot:\n"
       . "  git -C " . escapeshellarg($status['path']) . " status\n";
}
exit(0);
