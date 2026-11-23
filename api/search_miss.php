<?php
/**
 * A search that found nothing, reported by the search page once all organisms have answered
 * (js/modules/annotation-search.js finishSearch). The System Monitor lists these: the words
 * people looked for and did not find are the clearest sign of what the site is missing.
 *
 * Privacy (Sofia, 2026-11-23): search words are recorded ONLY for searches with no results, in
 * {cache}/metrics/search-misses-YYYY-MM-DD.tsv, admin-only, deleted after 30 days with the
 * request log. Searches that found something are never recorded.
 *
 * Only real visitors get here: api/ POSTs from anyone who has not passed the human check are
 * refused before this runs (lib/human_check.php); CSRF token required; 30 per session per hour.
 */
include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/request_metrics.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
csrf_protect(true);

$hour = (int) floor(time() / 3600);
$_SESSION['search_miss'] = ($_SESSION['search_miss']['h'] ?? 0) === $hour ? $_SESSION['search_miss'] : ['h' => $hour, 'n' => 0];
if (++$_SESSION['search_miss']['n'] > 30) { echo '{"ok":false}'; exit; }

$clean = fn($s, $n) => mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) $s)), 0, $n);
$words = $clean($_POST['words'] ?? '', 200);
if ($words === '') { echo '{"ok":false}'; exit; }
$line = implode("\t", [
    time(),
    $clean(get_username() !== '' ? get_username() : ($_SERVER['REMOTE_ADDR'] ?? ''), 80),
    $clean(get_access_level(), 20),
    (int) ($_POST['organisms'] ?? 0),
    ($_POST['maps_to'] ?? '') === '1' ? '1' : '0',
    $clean(parse_url((string) ($_POST['page'] ?? ''), PHP_URL_PATH) ?? '', 80),
    str_replace("\t", ' ', $words),
]) . "\n";
$dir = moop_metrics_dir();
if ($dir !== '' && moop_ensure_cache_dir($dir)) {
    @file_put_contents($dir . '/search-misses-' . date('Y-m-d') . '.tsv', $line, FILE_APPEND);
}
echo '{"ok":true}';
