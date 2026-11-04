<?php
/**
 * Render comparison: prove a change left a site's pages exactly as they were.
 *
 * Built first for the cuttingclass work (notes/CUTTINGCLASS_PLAN.md, step 0). Every cuttingclass
 * feature must be opt-in, so simrbase -- which never turns them on -- must render byte-for-byte
 * as before. This is how that is checked rather than assumed: capture the real pages, make the
 * change, capture again, compare.
 *
 *   php scripts/render_compare.php capture <dir>          save every page into <dir>
 *   php scripts/render_compare.php compare <dirA> <dirB>  diff two captures
 *   php scripts/render_compare.php check <baseline>       capture now, compare against <baseline>
 *
 * Options:
 *   --pages=FILE       page list (default tests/render_pages.json -- simrbase's pages)
 *   --login-file=FILE  two lines, username then password, for the 'admin' viewer
 *                      (default ~/.moop-claude-admin). Read, never printed.
 *
 * Viewers -- the same page looks different to each, and all three must stay the same:
 *   public   http://127.0.0.1/<site>     not in auto_login_ip_ranges, so a PUBLIC visitor
 *   network  http://<host ip>/<site>     inside the ranges: IP_IN_RANGE, full data, no admin
 *   admin    https://<host ip>/<site>    logged in with the login file
 *
 * WHAT IS BLANKED, AND WHY. Some values differ on every request with no code change. Each is
 * removed by a NAMED rule below (see $NORMALISE), never by "ignore this diff": a rule that
 * hides too much would make the check agree with any change, which is worse than no check.
 * The rule names are printed with every comparison, so what was ignored is always visible.
 *
 * LIMITS. This compares the HTML the server sends. It does not see a CSS change (the stylesheet
 * is a separate file; its ?v= stamp is blanked) or what JavaScript builds after load -- for a
 * visual change, compare computed styles in a browser (CLAUDE.md §12b).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$root = dirname(__DIR__);
$site = basename($root);

$args = array_slice($argv, 1);
$opts = [];
foreach ($args as $i => $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m)) { $opts[$m[1]] = $m[2]; unset($args[$i]); }
}
$args  = array_values($args);
$cmd   = $args[0] ?? '';
$pages_file = $opts['pages'] ?? "$root/tests/render_pages.json";
$login_file = $opts['login-file'] ?? (getenv('HOME') . '/.moop-claude-admin');

// Values that change on every request, each blanked by name. Keep this list SHORT and specific.
$NORMALISE = [
    'csrf token'        => ['/(name="csrf_token" value=")[^"]+/', '/(name="csrf-token" content=")[^"]+/',
                            '/("csrf_?[tT]oken"\s*[:=]\s*["\'])[^"\']+/'],
    'asset ?v= stamp'   => ['/(\.(?:css|js|png|jpe?g|svg|gif|ico|webp|woff2?)\?v=)\d+/'],
    'JWT track token'   => ['/(eyJ[A-Za-z0-9_-]{8,}\.)[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/'],
    // The header shows a RANDOM banner per request (includes/navbar.php). Only the file name
    // under images/banners/ is blanked; the markup around it is still compared.
    'random header banner' => ['#(/images/banners/)[^"\')\s]+#'],
    // Site Configuration: the favicon preview's cache-buster, and its live clock.
    'favicon ?t= stamp' => ['/(\.(?:jpe?g|png|ico|svg|gif|webp)\?t=)\d+/'],
    'site clock'        => ['/(The site\'s time right now: <strong>)[^<]+/'],
];

function usage(): void {
    fwrite(STDERR, "usage: php scripts/render_compare.php capture <dir> | compare <dirA> <dirB> | check <baseline>\n"
                 . "       [--pages=FILE] [--login-file=FILE]\n");
    exit(2);
}

function host_ip(): string {
    $ip = trim((string) shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    return $ip !== '' ? $ip : '127.0.0.1';
}

function viewer_base(string $viewer, string $site): string {
    switch ($viewer) {
        case 'public':  return "http://127.0.0.1/$site";
        case 'network': return 'http://' . host_ip() . "/$site";
        case 'admin':   return 'https://' . host_ip() . "/$site";
    }
    throw new RuntimeException("unknown viewer '$viewer'");
}

/** One HTTP request; returns [status, body]. Cookies persist per viewer in $jar. */
function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,   // the host IP is not on the cert
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    if ($body === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException("$url: $err"); }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, $body];
}

function csrf_from(string $html): string {
    return preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) ? $m[1] : '';
}

function normalise(string $body, array $rules): string {
    foreach ($rules as $patterns) {
        foreach ($patterns as $re) $body = preg_replace($re, '$1<blanked>', $body);
    }
    return $body;
}

function capture(string $dir, string $pages_file, string $login_file, string $site, array $rules): int {
    $pages = json_decode((string) @file_get_contents($pages_file), true);
    if (!is_array($pages) || !$pages) { fwrite(STDERR, "No pages in $pages_file\n"); return 2; }
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) { fwrite(STDERR, "Cannot create $dir\n"); return 2; }

    $jars = [];
    $jar_for = function (string $viewer) use (&$jars, $site, $login_file): string {
        if (isset($jars[$viewer])) return $jars[$viewer];
        $jar = tempnam(sys_get_temp_dir(), 'rc_jar_');
        $jars[$viewer] = $jar;
        if ($viewer === 'admin') {
            $lines = is_readable($login_file) ? file($login_file, FILE_IGNORE_NEW_LINES) : [];
            if (count($lines) < 2) throw new RuntimeException("admin viewer needs $login_file (username, password)");
            $base = viewer_base('admin', $site);
            [, $login_page] = http("$base/login.php", $jar);
            preg_match('/name="csrf_token" value="([^"]+)"/', $login_page, $m);
            http("$base/login.php", $jar, ['username' => trim($lines[0]), 'password' => $lines[1], 'csrf_token' => $m[1] ?? '']);
            [$st, $dash] = http("$base/admin/admin.php", $jar);
            if ($st !== 200) throw new RuntimeException("admin login failed (dashboard answered $st)");
        }
        return $jar;
    };

    $n = 0; $bad = 0;
    foreach ($pages as $p) {
        $name = $p['name'] ?? ''; $viewer = $p['viewer'] ?? 'public'; $path = $p['path'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $name) || $path === '') { fwrite(STDERR, "skipping bad entry: " . json_encode($p) . "\n"); continue; }
        try {
            $jar  = $jar_for($viewer);
            $base = viewer_base($viewer, $site);
            if (isset($p['post'])) {
                [, $form] = http($base . ($p['form'] ?? $path), $jar);   // a POST needs this session's token
                [$st, $body] = http($base . $path, $jar, $p['post'] + ['csrf_token' => csrf_from($form)]);
            } else {
                [$st, $body] = http($base . $path, $jar);
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "  $name: " . $e->getMessage() . "\n"); $bad++; continue;
        }
        file_put_contents("$dir/$viewer.$name.html", "HTTP $st\n" . normalise($body, $rules));
        printf("  %-8s %-34s %d  %7d bytes\n", $viewer, $name, $st, strlen($body));
        $n++;
    }
    foreach ($jars as $j) @unlink($j);
    file_put_contents("$dir/.captured", date('c') . "\n" . implode("\n", array_keys($rules)) . "\n");
    echo "Captured $n pages into $dir" . ($bad ? " ($bad failed)" : '') . "\n";
    return $bad ? 1 : 0;
}

function compare(string $a, string $b, array $rules): int {
    $files = array_unique(array_merge(array_map('basename', glob("$a/*.html") ?: []), array_map('basename', glob("$b/*.html") ?: [])));
    sort($files);
    if (!$files) { fwrite(STDERR, "Nothing to compare\n"); return 2; }
    $changed = [];
    foreach ($files as $f) {
        $x = @file_get_contents("$a/$f"); $y = @file_get_contents("$b/$f");
        if ($x === false || $y === false) { $changed[$f] = $x === false ? 'only in ' . $b : 'only in ' . $a; continue; }
        if ($x !== $y) $changed[$f] = 'differs';
    }
    echo "Blanked before comparing: " . implode(', ', array_keys($rules)) . "\n";
    if (!$changed) { echo "IDENTICAL: all " . count($files) . " pages match.\n"; return 0; }
    echo count($changed) . " of " . count($files) . " pages CHANGED:\n";
    foreach ($changed as $f => $why) {
        echo "\n=== $f ($why)\n";
        if ($why === 'differs') {
            passthru('diff -u ' . escapeshellarg("$a/$f") . ' ' . escapeshellarg("$b/$f") . ' | head -40');
        }
    }
    return 1;
}

switch ($cmd) {
    case 'capture':
        if (empty($args[1])) usage();
        exit(capture($args[1], $pages_file, $login_file, $site, $NORMALISE));
    case 'compare':
        if (empty($args[1]) || empty($args[2])) usage();
        exit(compare($args[1], $args[2], $NORMALISE));
    case 'check':
        if (empty($args[1])) usage();
        $now = sys_get_temp_dir() . '/render_check_' . getmypid();
        $rc = capture($now, $pages_file, $login_file, $site, $NORMALISE);
        if ($rc === 2) exit(2);
        $cmp = compare($args[1], $now, $NORMALISE);
        exec('rm -rf ' . escapeshellarg($now));
        exit($cmp ?: $rc);
    default:
        usage();
}
