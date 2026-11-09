<?php
/**
 * Content pages: pages an admin writes in Markdown (About, Protocols, FAQ, ...) -- cuttingclass
 * step 2 (notes/CUTTINGCLASS_PLAN.md). Opt-in: a site with no pages shows nothing new, and a page
 * appears in a menu only when a menu links it.
 *
 * Each page is one file, {metadata_path}/pages/{slug}.json -- SITE DATA, not code, so the
 * housekeeping snapshot copies it into the private site-data repo:
 *
 *   { "slug": "about-simrbase",
 *     "title": {"en": "About SIMRbase", "es": "Acerca de SIMRbase"},
 *     "body":  {"en": "# ...markdown...", "es": "..."},
 *     "updated_at": "2026-11-03T16:40:00-06:00", "updated_by": "admin" }
 *
 * Languages are the site's (Admin -> Manage Languages, ConfigManager::languages()). A page keeps
 * the text of EVERY language it was written in, retired ones included: retiring a language hides
 * it, it never deletes what was written (the retired-organism rule), and saving a page in the
 * editor -- which only shows the active languages -- leaves the others untouched. Until the
 * language work (step 5) chooses per visitor, a page is shown in English.
 *
 * A page is written in Markdown (the default) or HTML ('format'), the same for every language.
 * ONE renderer -- moop_page_render() -- for the published page, the home AND the editor's preview
 * (admin/api/preview_page.php), so the preview can never disagree with what visitors see:
 *   markdown  Parsedown in SAFE MODE: typed HTML is shown as text, javascript: links neutralised.
 *   html      written as-is (cards, grids, Bootstrap classes), then cleaned by HTML Purifier --
 *             a maintained sanitiser, not ours: scripts, on*= handlers, javascript: links, iframes
 *             and forms are removed; class and style are kept. The site's CSP still allows inline
 *             script, so this cleaning is what stops a page from carrying one.
 */

/** The languages a page is edited in: the site's active languages, English first. */
function moop_page_langs(): array {
    return ConfigManager::getInstance()->languageCodes();
}

/** Where pages live. Under metadata_path: written by the web (Manage Pages), snapshot to the backup. */
function moop_pages_dir(): string {
    return rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/pages';
}

/**
 * Is this a usable page address? Lower-case letters, digits and hyphens, 1-60 characters,
 * starting with a letter or digit. It is also the file name, so nothing else may pass.
 */
function moop_page_slug_ok(string $slug): bool {
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug);
}

/** Suggest an address from a title: "About SIMRbase!" -> "about-simrbase". */
function moop_page_slugify(string $title): string {
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return substr(trim((string) $s, '-'), 0, 60);
}

/** One page, or null if there is none (or the address is not a valid one). */
function moop_page_get(string $slug): ?array {
    if (!moop_page_slug_ok($slug)) return null;
    $file = moop_pages_dir() . "/$slug.json";
    if (!is_file($file)) return null;
    $page = json_decode((string) @file_get_contents($file), true);
    return is_array($page) ? moop_page_normalise($page + ['slug' => $slug]) : null;
}

/** Every page, sorted by English title: [slug => page]. */
function moop_page_list(): array {
    $pages = [];
    foreach (glob(moop_pages_dir() . '/*.json') ?: [] as $file) {
        $slug = basename($file, '.json');
        if ($page = moop_page_get($slug)) $pages[$slug] = $page;
    }
    uasort($pages, fn($a, $b) => strcasecmp($a['title']['en'], $b['title']['en']));
    return $pages;
}

/** Fill in a page's shape so callers never have to check for missing keys. */
function moop_page_normalise(array $page): array {
    $out = ['slug' => (string) ($page['slug'] ?? ''), 'format' => ($page['format'] ?? '') === 'html' ? 'html' : 'markdown',
            'title' => [], 'body' => [],
            'updated_at' => (string) ($page['updated_at'] ?? ''), 'updated_by' => (string) ($page['updated_by'] ?? '')];
    // Every language the page has text in (active or retired), plus every active one, so callers
    // can index any active language without checking.
    $langs = array_unique(array_merge(moop_page_langs(),
        array_keys((array) ($page['title'] ?? [])), array_keys((array) ($page['body'] ?? []))));
    foreach ($langs as $lang) {
        if (!preg_match(ConfigManager::LANGUAGE_CODE, (string) $lang)) continue;
        $out['title'][$lang] = (string) ($page['title'][$lang] ?? '');
        $out['body'][$lang]  = (string) ($page['body'][$lang] ?? '');
    }
    return $out;
}

/**
 * Save a page. Returns ['ok' => bool, 'error' => ?string]. Refuses a bad address or a page
 * with no English title, and reports a write that did not happen -- the recurring failure here
 * is a write the web server could not do, reported as success (CLAUDE.md §11).
 *
 * Written to a temp file and renamed, so a reader never sees half a page.
 */
function moop_page_save(array $page): array {
    // Text in a language not being saved now (a retired one, which the editor does not show) is
    // carried over from the stored page, never dropped.
    $stored = moop_page_slug_ok((string) ($page['slug'] ?? '')) ? moop_page_get((string) $page['slug']) : null;
    if ($stored !== null) {
        foreach (['title', 'body'] as $part) {
            foreach ($stored[$part] as $lang => $text) {
                if (!array_key_exists($lang, (array) ($page[$part] ?? []))) $page[$part][$lang] = $text;
            }
        }
    }
    $page = moop_page_normalise($page);
    if (!moop_page_slug_ok($page['slug'])) {
        return ['ok' => false, 'error' => 'The address may use only lower-case letters, digits and hyphens (up to 60).'];
    }
    if (trim($page['title']['en']) === '') {
        return ['ok' => false, 'error' => 'A page needs an English title.'];
    }
    $dir = moop_pages_dir();
    if (!is_dir($dir) && !@mkdir($dir, 02775, true)) {
        return ['ok' => false, 'error' => "Could not create $dir — the web server cannot write there."];
    }
    $page['updated_at'] = date('c');
    $page['updated_by'] = (string) ($_SESSION['username'] ?? '');
    $json = json_encode($page, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp  = "$dir/.{$page['slug']}." . getmypid() . '.tmp';
    if ($json === false || @file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, "$dir/{$page['slug']}.json")) {
        @unlink($tmp);
        return ['ok' => false, 'error' => "Could not write the page into $dir — the web server cannot write there."];
    }
    @chmod("$dir/{$page['slug']}.json", 0664);
    return ['ok' => true, 'error' => null];
}

/** Delete a page. Returns ['ok' => bool, 'error' => ?string]. */
function moop_page_delete(string $slug): array {
    if (!moop_page_slug_ok($slug)) return ['ok' => false, 'error' => 'No such page.'];
    $file = moop_pages_dir() . "/$slug.json";
    if (!is_file($file)) return ['ok' => false, 'error' => 'No such page.'];
    return @unlink($file) ? ['ok' => true, 'error' => null]
                          : ['ok' => false, 'error' => 'Could not delete the page — the web server cannot write there.'];
}

/**
 * Markdown -> HTML, the ONE converter for pages and their preview. Safe mode: raw HTML is
 * escaped, javascript: URLs are neutralised. Tables get Bootstrap's table class.
 */
function moop_markdown(string $markdown): string {
    static $parser = null;
    if ($parser === null) {
        // Loaded here, not at the top: including this file (housekeeping, the page list) must not
        // need Composer -- only turning Markdown into HTML does.
        require_once __DIR__ . '/../vendor/autoload.php';
        $parser = new Parsedown();
        $parser->setSafeMode(true);
        $parser->setBreaksEnabled(false);
    }
    $html = $parser->text($markdown);
    return str_replace('<table>', '<table class="table table-sm table-bordered">', $html);
}

/**
 * HTML -> cleaned HTML (HTML Purifier). Keeps structure and styling -- block and inline text
 * elements, lists, tables, links, images, collapsible <details>/<summary> sections (long FAQs and
 * protocols, cuttingclass 2026-11-05: they open and close with no script), and class/style/title on
 * any of them -- and removes
 * everything that can run: <script>, on*= handlers, javascript: links, <iframe>, <form>, <style>.
 *
 * ONE iframe is allowed: a YouTube video from https://www.youtube-nocookie.com/embed/<id>
 * (cuttingclass's HHMI film, 2026-11-09). Any other iframe source is still removed -- the
 * pattern below is the whole allowlist. The privacy-enhanced youtube-nocookie host sets no
 * cookie until the visitor presses play. The browser also needs the host in the CSP
 * frame-src (docs/nginx/moop-headers.conf); without it the box shows blank.
 * With $removed passed, it is filled with what was taken out (for the editor's preview).
 */
/** The only iframe a content page may carry: a YouTube video id on the no-cookie host. */
const MOOP_PAGE_IFRAME_REGEXP = '%^https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}(\?[A-Za-z0-9_=&;.-]*)?$%';

function moop_html_clean(string $html, ?array &$removed = null): string {
    // Loaded here, not at the top: including this file must not need Composer.
    require_once __DIR__ . '/../vendor/autoload.php';
    $collect = func_num_args() > 1;
    static $purifiers = [];
    if (!isset($purifiers[$collect])) {
        $c = HTMLPurifier_Config::createDefault();
        $c->set('Cache.DefinitionImpl', null);   // no cache directory to keep writable; pages are small
        $c->set('HTML.AllowedElements', ['details', 'summary', 'div', 'span', 'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sub', 'sup', 'blockquote', 'pre', 'code',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'a', 'img', 'iframe']);
        $c->set('HTML.AllowedAttributes', ['*.class', '*.style', '*.title', 'details.open', 'a.href', 'a.target',
            'img.src', 'img.alt', 'img.width', 'img.height', 'iframe.src', 'iframe.width', 'iframe.height',
            'iframe.allowfullscreen', 'th.colspan', 'th.rowspan', 'td.colspan', 'td.rowspan']);
        $c->set('Attr.AllowedFrameTargets', ['_blank']);
        $c->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $c->set('HTML.SafeIframe', true);
        $c->set('URI.SafeIframeRegexp', MOOP_PAGE_IFRAME_REGEXP);
        $c->set('Core.CollectErrors', $collect);
        // HTML Purifier predates HTML5's <details>/<summary>: teach it the two elements. The
        // definition id/rev name this addition (required for a custom definition; nothing is cached).
        $c->set('HTML.DefinitionID', 'moop-content-pages');
        $c->set('HTML.DefinitionRev', 2);
        if ($def = $c->maybeGetRawHTMLDefinition()) {
            $def->addElement('details', 'Block', 'Flow', 'Common', ['open' => 'Bool#open']);
            $def->addElement('summary', 'Block', 'Flow', 'Common');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
        }
        $purifiers[$collect] = new HTMLPurifier($c);
    }
    $p = $purifiers[$collect];
    $clean = $p->purify($html);
    // HTML Purifier drops a disallowed iframe's src but keeps an empty <iframe></iframe>. Remove
    // the husk, and tell the editor why (its error list does not mention it).
    $husks = 0;
    $clean = preg_replace('#<iframe(?![^>]*\ssrc=)[^>]*>\s*</iframe>#i', '', $clean, -1, $husks);
    if ($collect) {
        $removed = [];
        $errors = $p->context->get('ErrorCollector', true);
        foreach ($errors ? $errors->getRaw() : [] as $e) {
            // Plain text naming the tag ("Unrecognized <script> tag removed") -- escaped by whoever
            // shows it. "transformed" lines report additions (an alt= filled in), not removals.
            $msg = trim(preg_replace('/\s+/', ' ', html_entity_decode((string) ($e[2] ?? ''))));
            if ($msg !== '' && stripos($msg, 'transformed') === false && strpos($msg, 'Unrecognized </') !== 0
                && !in_array($msg, $removed, true)) $removed[] = $msg;
        }
        if ($husks) $removed[] = 'A video from a site other than youtube-nocookie.com/embed/ removed';
    }
    return $clean;
}

/**
 * A page body as visitors see it, in its format. THE renderer: page.php, the home (index.php)
 * and the editor's preview all call this. $removed (HTML only) lists what the cleaner took out.
 */
function moop_page_render(string $body, string $format, ?array &$removed = null): string {
    if ($format !== 'html') { $removed = []; return moop_markdown($body); }
    return func_num_args() > 2 ? moop_html_clean($body, $removed) : moop_html_clean($body);
}

/** A page's public address. */
function moop_page_url(string $slug): string {
    return '/' . ConfigManager::getInstance()->getString('site') . '/page.php?p=' . rawurlencode($slug);
}
