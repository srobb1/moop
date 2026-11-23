<?php
/**
 * Where an address is: country and state/region, from a LOCAL copy of DB-IP's free
 * "IP to City Lite" database (CC BY 4.0 -- pages that show its results must say
 * "IP geolocation by DB-IP" with a link; moop_geoip_attribution()).
 *
 * Why local: looking an address up with an online service would send every visitor's address to
 * a third party. Here nothing leaves the server; the database is downloaded once a month
 * (housekeeping task geoip_update, or scripts/update_geoip.php by hand).
 *
 * Only country + state/region are kept (the city column is dropped and neighbouring ranges in
 * the same region merged), which is all the System Monitor shows and keeps the file small.
 * Accuracy: country is reliable; region is usually right for home and university connections,
 * often wrong for mobile networks and VPNs, and meaningless for cloud providers (a crawler in an
 * Azure data centre shows the data centre's region).
 *
 * File: {cache}/geoip/dbip-region.sqlite
 *   v4(start INTEGER PRIMARY KEY, end INTEGER, cc TEXT, region TEXT)
 *   v6(start TEXT PRIMARY KEY, end TEXT, cc TEXT, region TEXT)  -- 32 lowercase hex digits
 *   meta(k TEXT PRIMARY KEY, v TEXT)                              -- source, built_at
 */

require_once __DIR__ . '/cache_paths.php';

const MOOP_GEOIP_URL      = 'https://download.db-ip.com/free/dbip-city-lite-%s.csv.gz';
const MOOP_GEOIP_MAX_AGE  = 35 * 86400;   // DB-IP publishes monthly

function moop_geoip_dir(): string {
    return moop_cache_root() . '/geoip';
}

function moop_geoip_db_file(): string {
    return moop_geoip_dir() . '/dbip-region.sqlite';
}

/** HTML for the required attribution line. */
function moop_geoip_attribution(): string {
    return '<a href="https://db-ip.com" target="_blank" rel="noopener">IP geolocation by DB-IP</a>';
}

/** When the local database was built (unix time), or null when there is none. */
function moop_geoip_built_at(): ?int {
    $f = moop_geoip_db_file();
    return is_file($f) ? (int) filemtime($f) : null;
}

/** Is this a private / loopback / link-local address (no place on the map)? */
function moop_geoip_is_private(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * Key for an address: [table, key] -- v4 as an integer, v6 as 32 hex digits. Null if not an IP.
 * An IPv4-mapped IPv6 address (::ffff:1.2.3.4) is looked up as IPv4.
 */
function moop_geoip_key(string $ip): ?array {
    $bin = @inet_pton($ip);
    if ($bin === false) return null;
    if (strlen($bin) === 16 && substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") $bin = substr($bin, 12);
    if (strlen($bin) === 4) return ['v4', unpack('N', $bin)[1]];
    return ['v6', bin2hex($bin)];
}

/**
 * ['cc' => 'US', 'country' => 'United States', 'region' => 'Missouri'] for an address.
 * Private addresses: cc 'LAN'. Unknown / no database: null. Results are memoised per request.
 */
function moop_geoip_lookup(string $ip): ?array {
    static $memo = [];
    static $db = false;
    if (array_key_exists($ip, $memo)) return $memo[$ip];
    if (moop_geoip_is_private($ip)) {
        return $memo[$ip] = ['cc' => 'LAN', 'country' => 'Institute network', 'region' => ''];
    }
    $key = moop_geoip_key($ip);
    if ($key === null) return $memo[$ip] = null;
    if ($db === false) {
        $db = null;
        $f = moop_geoip_db_file();
        if (is_readable($f)) {
            try {
                $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Throwable $e) {
                $db = null;
            }
        }
    }
    if ($db === null) return $memo[$ip] = null;
    [$table, $k] = $key;
    $st = $db->prepare("SELECT end, cc, region FROM $table WHERE start <= ? ORDER BY start DESC LIMIT 1");
    $st->execute([$k]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($table === 'v4' ? (int) $row['end'] < $k : strcmp($row['end'], $k) < 0) || $row['cc'] === 'ZZ') {
        return $memo[$ip] = null;
    }
    return $memo[$ip] = ['cc' => $row['cc'], 'country' => moop_geoip_country_name($row['cc']), 'region' => (string) $row['region']];
}

/** "Missouri, United States" / "United States" / "Institute network" / "" */
function moop_geoip_label(?array $g): string {
    if (!$g) return '';
    return $g['region'] !== '' ? $g['region'] . ', ' . $g['country'] : $g['country'];
}

/**
 * Download this month's (or last month's) DB-IP file and rebuild the local database.
 * Builds into a temp file and renames, so a failed build never leaves a half database.
 * Returns ['ok' => bool, 'source' => url, 'ranges' => n, 'error' => ?string].
 */
function moop_geoip_update(?callable $progress = null): array {
    $dir = moop_geoip_dir();
    if (!moop_ensure_cache_dir($dir)) return ['ok' => false, 'error' => "cannot create $dir"];
    $gz = $dir . '/.download.csv.gz';
    $url = null;
    foreach ([date('Y-m'), date('Y-m', strtotime('first day of last month'))] as $month) {
        $try = sprintf(MOOP_GEOIP_URL, $month);
        if ($progress) $progress("Downloading $try");
        if (moop_geoip_download($try, $gz)) { $url = $try; break; }
    }
    if ($url === null) {
        @unlink($gz);
        return ['ok' => false, 'error' => 'could not download the DB-IP file (this month or last)'];
    }
    if ($progress) $progress('Building the region table');
    $tmp = $dir . '/.build-' . getmypid() . '.sqlite';
    @unlink($tmp);
    try {
        $n = moop_geoip_build($gz, $tmp);
        $db = new PDO('sqlite:' . $tmp);
        $db->prepare('INSERT INTO meta (k, v) VALUES (?, ?), (?, ?)')->execute(['source', $url, 'built_at', date('c')]);
        $db = null;
        @chmod($tmp, 0664);
        if (!rename($tmp, moop_geoip_db_file())) throw new RuntimeException('could not move the new database into place');
    } catch (Throwable $e) {
        @unlink($tmp);
        @unlink($gz);
        return ['ok' => false, 'source' => $url, 'error' => $e->getMessage()];
    }
    @unlink($gz);
    return ['ok' => true, 'source' => $url, 'ranges' => $n, 'error' => null];
}

function moop_geoip_download(string $url, string $dest): bool {
    $in = @fopen($url, 'rb', false, stream_context_create(['http' => [
        'timeout' => 60, 'user_agent' => 'MOOP geoip update', 'ignore_errors' => false,
    ]]));
    if (!$in) return false;
    $out = fopen($dest, 'wb');
    $bytes = $out ? stream_copy_to_stream($in, $out) : false;
    fclose($in);
    if ($out) fclose($out);
    // A real file is ~80 MB; anything tiny is an error page.
    return $bytes !== false && $bytes > 1000000;
}

/** Stream the gzipped CSV into $db_file, merging neighbouring ranges in the same region. */
function moop_geoip_build(string $gz, string $db_file): int {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF;
        CREATE TABLE v4 (start INTEGER PRIMARY KEY, end INTEGER NOT NULL, cc TEXT NOT NULL, region TEXT NOT NULL);
        CREATE TABLE v6 (start TEXT PRIMARY KEY, end TEXT NOT NULL, cc TEXT NOT NULL, region TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE meta (k TEXT PRIMARY KEY, v TEXT);');
    $ins = ['v4' => $db->prepare('INSERT INTO v4 VALUES (?, ?, ?, ?)'), 'v6' => $db->prepare('INSERT INTO v6 VALUES (?, ?, ?, ?)')];
    $fh = gzopen($gz, 'rb');
    if (!$fh) throw new RuntimeException('cannot open the downloaded file');
    $db->beginTransaction();
    $n = 0;
    $cur = null;   // [table, start, end, cc, region]
    $flush = function () use (&$cur, &$n, $ins) {
        if ($cur) { $ins[$cur[0]]->execute([$cur[1], $cur[2], $cur[3], $cur[4]]); $n++; }
    };
    while (($f = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if (count($f) < 5) continue;
        $a = moop_geoip_key($f[0]);
        $b = moop_geoip_key($f[1]);
        if (!$a || !$b || $a[0] !== $b[0]) continue;
        $cc = $f[3];
        $region = $f[4];
        if ($cur && $cur[0] === $a[0] && $cur[3] === $cc && $cur[4] === $region && moop_geoip_next($cur[0], $cur[2]) === $a[1]) {
            $cur[2] = $b[1];
            continue;
        }
        $flush();
        $cur = [$a[0], $a[1], $b[1], $cc, $region];
    }
    $flush();
    gzclose($fh);
    $db->commit();
    if ($n < 100000) throw new RuntimeException("only $n ranges read -- the file looks wrong");
    return $n;
}

/** The address after $k (integer for v4, 32-hex for v6). */
function moop_geoip_next(string $table, $k) {
    if ($table === 'v4') return $k + 1;
    $bin = hex2bin($k);
    for ($i = 15; $i >= 0; $i--) {
        $c = ord($bin[$i]);
        if ($c < 255) { $bin[$i] = chr($c + 1); break; }
        $bin[$i] = "\0";
    }
    return bin2hex($bin);
}

function moop_geoip_country_name(string $cc): string {
    static $names = null;
    if ($names === null) {
        $names = [];
        foreach (explode('|', 'AD:Andorra|AE:United Arab Emirates|AF:Afghanistan|AG:Antigua and Barbuda|AI:Anguilla|AL:Albania|AM:Armenia|AO:Angola|AQ:Antarctica|AR:Argentina|AS:American Samoa|AT:Austria|AU:Australia|AW:Aruba|AX:Åland Islands|AZ:Azerbaijan|BA:Bosnia and Herzegovina|BB:Barbados|BD:Bangladesh|BE:Belgium|BF:Burkina Faso|BG:Bulgaria|BH:Bahrain|BI:Burundi|BJ:Benin|BL:Saint Barthélemy|BM:Bermuda|BN:Brunei|BO:Bolivia|BQ:Caribbean Netherlands|BR:Brazil|BS:Bahamas|BT:Bhutan|BW:Botswana|BY:Belarus|BZ:Belize|CA:Canada|CD:DR Congo|CF:Central African Republic|CG:Congo|CH:Switzerland|CI:Côte d\'Ivoire|CK:Cook Islands|CL:Chile|CM:Cameroon|CN:China|CO:Colombia|CR:Costa Rica|CU:Cuba|CV:Cape Verde|CW:Curaçao|CY:Cyprus|CZ:Czechia|DE:Germany|DJ:Djibouti|DK:Denmark|DM:Dominica|DO:Dominican Republic|DZ:Algeria|EC:Ecuador|EE:Estonia|EG:Egypt|ER:Eritrea|ES:Spain|ET:Ethiopia|FI:Finland|FJ:Fiji|FK:Falkland Islands|FM:Micronesia|FO:Faroe Islands|FR:France|GA:Gabon|GB:United Kingdom|GD:Grenada|GE:Georgia|GF:French Guiana|GG:Guernsey|GH:Ghana|GI:Gibraltar|GL:Greenland|GM:Gambia|GN:Guinea|GP:Guadeloupe|GQ:Equatorial Guinea|GR:Greece|GT:Guatemala|GU:Guam|GW:Guinea-Bissau|GY:Guyana|HK:Hong Kong|HN:Honduras|HR:Croatia|HT:Haiti|HU:Hungary|ID:Indonesia|IE:Ireland|IL:Israel|IM:Isle of Man|IN:India|IQ:Iraq|IR:Iran|IS:Iceland|IT:Italy|JE:Jersey|JM:Jamaica|JO:Jordan|JP:Japan|KE:Kenya|KG:Kyrgyzstan|KH:Cambodia|KI:Kiribati|KM:Comoros|KN:Saint Kitts and Nevis|KP:North Korea|KR:South Korea|KW:Kuwait|KY:Cayman Islands|KZ:Kazakhstan|LA:Laos|LB:Lebanon|LC:Saint Lucia|LI:Liechtenstein|LK:Sri Lanka|LR:Liberia|LS:Lesotho|LT:Lithuania|LU:Luxembourg|LV:Latvia|LY:Libya|MA:Morocco|MC:Monaco|MD:Moldova|ME:Montenegro|MF:Saint Martin|MG:Madagascar|MH:Marshall Islands|MK:North Macedonia|ML:Mali|MM:Myanmar|MN:Mongolia|MO:Macao|MP:Northern Mariana Islands|MQ:Martinique|MR:Mauritania|MS:Montserrat|MT:Malta|MU:Mauritius|MV:Maldives|MW:Malawi|MX:Mexico|MY:Malaysia|MZ:Mozambique|NA:Namibia|NC:New Caledonia|NE:Niger|NF:Norfolk Island|NG:Nigeria|NI:Nicaragua|NL:Netherlands|NO:Norway|NP:Nepal|NR:Nauru|NU:Niue|NZ:New Zealand|OM:Oman|PA:Panama|PE:Peru|PF:French Polynesia|PG:Papua New Guinea|PH:Philippines|PK:Pakistan|PL:Poland|PM:Saint Pierre and Miquelon|PR:Puerto Rico|PS:Palestine|PT:Portugal|PW:Palau|PY:Paraguay|QA:Qatar|RE:Réunion|RO:Romania|RS:Serbia|RU:Russia|RW:Rwanda|SA:Saudi Arabia|SB:Solomon Islands|SC:Seychelles|SD:Sudan|SE:Sweden|SG:Singapore|SI:Slovenia|SK:Slovakia|SL:Sierra Leone|SM:San Marino|SN:Senegal|SO:Somalia|SR:Suriname|SS:South Sudan|ST:São Tomé and Príncipe|SV:El Salvador|SX:Sint Maarten|SY:Syria|SZ:Eswatini|TC:Turks and Caicos Islands|TD:Chad|TG:Togo|TH:Thailand|TJ:Tajikistan|TL:Timor-Leste|TM:Turkmenistan|TN:Tunisia|TO:Tonga|TR:Türkiye|TT:Trinidad and Tobago|TV:Tuvalu|TW:Taiwan|TZ:Tanzania|UA:Ukraine|UG:Uganda|US:United States|UY:Uruguay|UZ:Uzbekistan|VA:Vatican City|VC:Saint Vincent and the Grenadines|VE:Venezuela|VG:British Virgin Islands|VI:U.S. Virgin Islands|VN:Vietnam|VU:Vanuatu|WS:Samoa|XK:Kosovo|YE:Yemen|YT:Mayotte|ZA:South Africa|ZM:Zambia|ZW:Zimbabwe') as $pair) {
            [$k, $v] = explode(':', $pair, 2);
            $names[$k] = $v;
        }
    }
    return $names[$cc] ?? $cc;
}
