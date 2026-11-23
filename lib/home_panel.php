<?php
/**
 * The home page's "Getting started" and "What's new" boxes (2026-11-23, SIMRbase first).
 *
 * Opt-in per site: Admin -> Manage Pages -> Home page -> "MOOP's home, with Getting started and
 * What's new" (home setting mode 'panel'). Every other site keeps its home exactly as it was.
 *
 * GETTING STARTED is built, not typed: an item shows only when what it points to exists here --
 *   the tour        (content page `tour`)
 *   what changed    (content page `what-changed`; "Used the old <name>?" names the site this one
 *                    replaced, from the home setting's old_site_name)
 *   AI assistant    (the ai_guide setting, as the footer link)
 *   send a message  (always)
 * so a site cannot show a link that leads nowhere, and a page made private hides its item.
 *
 * WHAT'S NEW is a short list kept by an admin (Admin -> News, admin/manage_news.php) in
 * {metadata_path}/news.json -- site data, copied to the site-data backup with the rest of metadata/.
 * Each item: id, date (Y-m-d), text (one or two sentences of Markdown, links allowed), groups.
 * PERMISSION-AWARE: an item naming groups shows only to a visitor who can see something in one of
 * them, so news of a private genome never reaches the public. No groups = everyone.
 * Stale news hides itself: the home shows items from the last MOOP_NEWS_HOME_DAYS days only, and
 * with none the box is left out -- nothing to keep tidy by hand. tools/news.php lists all of them.
 */

const MOOP_NEWS_HOME_MAX  = 3;
const MOOP_NEWS_HOME_DAYS = 180;
const MOOP_NEWS_MAX_TEXT  = 600;

/** The file. $file overrides (tests). */
function moop_news_file(?string $file = null): string {
    if ($file !== null) return $file;
    return rtrim((string) ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/news.json';
}

/** Every item, newest first. Missing file = no news; unreadable/garbled = null (an error, never "no news"). */
function moop_news_all(?string $file = null): ?array {
    $f = moop_news_file($file);
    if (!file_exists($f)) return [];
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    $d = array_values(array_filter($d, fn($n) => is_array($n) && isset($n['id'], $n['date'], $n['text'])));
    usort($d, fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']) ?: strcmp((string) $b['id'], (string) $a['id']));
    return $d;
}

/**
 * The items this visitor may see. $visible_groups = the groups holding something they can see
 * (moop_news_visible_groups()); passed in so tests need no session.
 */
function moop_news_for_visitor(array $items, array $visible_groups, ?int $limit = null, ?int $max_age_days = null, ?int $now = null): array {
    $now = $now ?? time();
    $set = array_flip($visible_groups);
    $out = [];
    foreach ($items as $n) {
        $groups = array_values(array_filter((array) ($n['groups'] ?? []), 'is_string'));
        if ($groups && !array_intersect_key(array_flip($groups), $set)) continue;
        if ($max_age_days !== null) {
            $t = strtotime((string) $n['date']);
            if ($t === false || $t < $now - $max_age_days * 86400) continue;
        }
        $out[] = $n;
        if ($limit !== null && count($out) >= $limit) break;
    }
    return $out;
}

/** Groups holding at least one thing this visitor can see. */
function moop_news_visible_groups(): array {
    $g = [];
    foreach (moop_visible_group_entries(getGroupData()) as $e) {
        foreach ((array) ($e['groups'] ?? []) as $name) $g[(string) $name] = true;
    }
    return array_keys($g);
}

/** An item's text as HTML: Markdown (safe, moop_markdown), one paragraph unwrapped. */
function moop_news_html(string $text): string {
    require_once __DIR__ . '/content_pages.php';
    $html = trim(moop_page_render($text, 'markdown'));
    if (substr_count($html, '<p>') === 1 && preg_match('#^<p>(.*)</p>$#s', $html, $m)) $html = $m[1];
    // A link written as on the home page ("tools/blast.php") is made site-relative, so it works the
    // same on All news (tools/news.php) and in the admin list -- relative, it pointed at tools/tools/.
    return preg_replace_callback('#href="(?![a-z][a-z0-9+.-]*:|/|\#)([^"]+)"#i',
        fn($m) => 'href="' . htmlspecialchars(moop_url(html_entity_decode($m[1], ENT_QUOTES))) . '"', $html);
}

/** Check one item from the admin form. Returns [item, errors]. */
function moop_news_validate(array $in, array $known_groups): array {
    $date = trim((string) ($in['date'] ?? ''));
    $text = trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]+/u', '', (string) ($in['text'] ?? '')));
    $groups = array_values(array_intersect(array_map('strval', (array) ($in['groups'] ?? [])), $known_groups));
    $errors = [];
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) $errors[] = 'The date must be a real date (YYYY-MM-DD).';
    if ($text === '') $errors[] = 'Write what is new.';
    if (mb_strlen($text) > MOOP_NEWS_MAX_TEXT) $errors[] = 'Keep it short: at most ' . MOOP_NEWS_MAX_TEXT . ' characters. Put the details on a page and link to it.';
    return [['date' => $date, 'text' => $text, 'groups' => $groups], $errors];
}

/**
 * Add, replace (same id) or delete ($item === null) one item, under a lock. Returns true when saved.
 * Never overwrites a file it could not read.
 */
function moop_news_put(?string $id, ?array $item, ?string $file = null): bool {
    $f = moop_news_file($file);
    $lock = @fopen($f . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) return false;
    try {
        $all = moop_news_all($file);
        if ($all === null) return false;
        $all = array_values(array_filter($all, fn($n) => $id === null || $n['id'] !== $id));
        if ($item !== null) $all[] = $item + ['id' => $id ?? bin2hex(random_bytes(5))];
        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $f . '.tmp' . getmypid();
        if ($json === false || @file_put_contents($tmp, $json . "\n") === false) { @unlink($tmp); return false; }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
        return true;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** The Getting started items for this visitor: [icon, title, subtitle, url]. */
function moop_home_getting_started(string $old_site_name): array {
    require_once __DIR__ . '/content_pages.php';
    $items = [];
    if (moop_page_for_visitor('tour') !== null) {
        $items[] = ['fa-compass', t_plain('New here?'), t_plain('A two-minute tour of what is where'), moop_url('page.php?p=tour')];
    }
    if (moop_page_for_visitor('what-changed') !== null) {
        $items[] = ['fa-route', $old_site_name !== '' ? t_plain('Used the old %s?', $old_site_name) : t_plain('What changed?'),
                    t_plain('What changed: more organisms, more tools'), moop_url('page.php?p=what-changed')];
    }
    $ai = moop_ai_guide();
    if ($ai) {
        $items[] = ['fa-robot', t_plain('Ask an AI assistant'), t_plain('Use this site from ChatGPT or Claude, in plain words'), $ai['help']];
    }
    $items[] = ['fa-comment-dots', t_plain('Questions or requests?'), t_plain('Send us a message — a person reads every one'), moop_url('tools/feedback.php?from=' . rawurlencode(moop_url_base() . '/'))];
    return $items;
}
