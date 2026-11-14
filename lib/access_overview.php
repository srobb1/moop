<?php
/**
 * ACCESS OVERVIEW -- "who can see what", every item on one list (admin/access_overview.php).
 * Plan: notes/PERMISSIONS_PLAN.md §4c (step 5).
 *
 * Read-only: this file decides NOTHING. Every answer comes from the same functions the site uses
 * to show or hide the item (groups file + users.json for data sets, lib/access_rules.php for pages
 * and analyses, the track's own level for JBrowse tracks), so the overview cannot disagree with the
 * site. It only puts them side by side, in plain words.
 *
 * ONE PROVIDER PER KIND (moop_access_overview_providers()). A new kind of item that needs permissions
 * -- single-cell data, BLAST-only genomes -- is one function returning rows plus one line in that
 * list; the page, filters and dialog need no change.
 *
 * A row: [
 *   'kind'       => 'gene_set' | 'expression' | 'images' | 'clade' | 'tracks' | 'crosswalk' | 'curations' | 'page',
 *   'group'      => 'data' | 'analysis' | 'page'     (the page's kind filter)
 *   'organism'   => '' when the item spans organisms or has none (a clade, a page)
 *   'item'       => its name,  'detail' => where it lives (org / asm / gene set), plain text
 *   'who'        => who can see it, plain words
 *   'restricted' => bool -- not everyone may see it (the "only restricted" filter)
 *   'why'        => where that answer comes from ("set here", "follows gene set x", "track sheet")
 *   'edit'       => null | ['kind' => 'page'|'analysis', 'key', 'title', 'rule'] -- the shared dialog
 *   'links'      => [['url', 'text'], ...] -- the admin pages where it is changed (or more is said)
 * ]
 *
 * Fails closed in what it SAYS: an unreadable access_rules.json or users.json is reported by the
 * page, and every rule then reads "Admins only" -- what the site itself does.
 */

require_once __DIR__ . '/access_rules.php';

/** Every kind, in display order. Add a kind here. */
function moop_access_overview_providers(): array {
    return [
        'moop_access_overview_gene_sets',
        'moop_access_overview_expression',
        'moop_access_overview_images',
        'moop_access_overview_clades',
        'moop_access_overview_tracks',
        'moop_access_overview_crosswalk',
        'moop_access_overview_curations',
        'moop_access_overview_pages',
    ];
}

/** Labels for the kind column and filter. */
const MOOP_ACCESS_OVERVIEW_KINDS = [
    'gene_set'   => 'Gene set',
    'expression' => 'Expression experiment',
    'images'     => 'Image analysis',
    'clade'      => 'Ortholog run (clade)',
    'tracks'     => 'JBrowse tracks',
    'crosswalk'  => 'ID Translator',
    'curations'  => 'Anatomy curations',
    'page'       => 'Content page',
];

/**
 * All rows, plus what the page must warn about:
 * ['rows' => [...], 'rules_ok' => bool, 'users_ok' => bool, 'errors' => [provider => message]].
 * A provider that throws is reported and skipped -- one broken file never blanks the whole page.
 */
function moop_access_overview(): array {
    $ctx  = moop_access_overview_context();
    $rows = []; $errors = [];
    foreach (moop_access_overview_providers() as $fn) {
        try {
            foreach ($fn($ctx) as $r) $rows[] = $r;
        } catch (Throwable $e) {
            $errors[$fn] = $e->getMessage();
            error_log("MOOP access overview: $fn failed: " . $e->getMessage());
        }
    }
    return ['rows' => $rows, 'rules_ok' => moop_access_rules()['ok'], 'users_ok' => $ctx['users_ok'], 'errors' => $errors];
}

/**
 * What every provider needs, read once: the groups file, users.json, and each gene set's
 * audience (moop_access_overview_gene_set_audience()).
 */
function moop_access_overview_context(): array {
    $cm = ConfigManager::getInstance();
    $groups = [];
    $gfile = rtrim($cm->getPath('metadata_path'), '/') . '/organism_assembly_groups.json';
    foreach ((array) json_decode((string) @file_get_contents($gfile), true) as $e) {
        if (!is_array($e) || empty($e['organism']) || empty($e['assembly'])) continue;
        $groups[$e['organism'] . '/' . $e['assembly'] . '/' . ($e['gene_set'] ?? '')] = $e;
    }
    $ufile = $cm->getPath('users_file');
    $users = json_decode((string) @file_get_contents($ufile), true);
    $users_ok = is_array($users);
    return ['data' => rtrim($cm->getPath('organism_data'), '/'), 'groups' => $groups,
            'users' => $users_ok ? $users : [], 'users_ok' => $users_ok, 'audience' => []];
}

/**
 * Who may see one gene set, as data: ['exists', 'public', 'groups' => [...], 'users' => [names]].
 * Public = its groups entry is public (entry_is_public(), the site's own test). Otherwise only the
 * internal network, admins, and the collaborators whose users.json list grants it
 * (moop_user_access_allows(), the login check's own test).
 */
function moop_access_overview_gene_set_audience(array &$ctx, string $org, string $asm, string $gs): array {
    $k = "$org/$asm/$gs";
    if (isset($ctx['audience'][$k])) return $ctx['audience'][$k];
    $entry = $ctx['groups'][$k] ?? null;
    $users = [];
    foreach ($ctx['users'] as $name => $u) {
        if (!is_array($u) || ($u['role'] ?? '') === 'admin') continue;
        if (moop_user_access_allows($u['access'] ?? [], $org, $asm, $gs)) $users[] = (string) $name;
    }
    sort($users, SORT_NATURAL | SORT_FLAG_CASE);
    return $ctx['audience'][$k] = [
        'exists' => is_dir("{$ctx['data']}/$org/$asm/$gs"),
        'public' => $entry !== null && entry_is_public($entry),
        'groups' => array_values(array_filter((array) ($entry['groups'] ?? []), 'is_string')),
        'users'  => $users,
    ];
}

/** An audience in plain words: "Everyone", or "Internal network + admins + 2 collaborators (a, b)". */
function moop_access_overview_audience_words(array $a): string {
    if ($a['public']) return 'Everyone';
    $n = count($a['users']);
    if (!$n) return 'Internal network + admins';
    return 'Internal network + admins + ' . $n . ($n === 1 ? ' collaborator' : ' collaborators')
         . ' (' . implode(', ', array_slice($a['users'], 0, 5)) . ($n > 5 ? ', …' : '') . ')';
}

/**
 * An analysis's audience: its own setting (narrow-only) on top of its data set's audience.
 * $a = the data set's audience, or null when the analysis spans several (each still applies).
 */
function moop_access_overview_narrowed(string $setting, ?array $a): array {
    switch ($setting) {
        case 'dataset':
            return $a === null ? ['who' => 'Same as each of its gene sets', 'restricted' => false]
                               : ['who' => moop_access_overview_audience_words($a), 'restricted' => !$a['public']];
        case 'logged_in':
            if ($a !== null && !$a['public']) return ['who' => moop_access_overview_audience_words($a), 'restricted' => true];
            return ['who' => 'Logged in' . ($a === null ? ' (and each gene set\'s own access)' : ''), 'restricted' => true];
        case 'internal':
            return ['who' => 'Internal network + admins', 'restricted' => true];
        default:
            return ['who' => 'Admins only', 'restricted' => true];
    }
}

/** "set here" / "default from experiment.json" -- why an analysis has its setting. */
function moop_access_overview_analysis_why(array $rule, string $default_from): string {
    if ($rule['set_here']) return 'Set here';
    return $rule['visible_to'] === 'dataset' ? 'Follows its data set' : "Default ($default_from)";
}

/**
 * The dialog payload for an analysis. 'default' is the setting it has with no stored rule; the save
 * passes it to moop_analysis_rule_set(), so choosing the default clears the stored rule.
 */
function moop_access_overview_analysis_edit(string $key, string $title, array $rule, string $default): array {
    return ['kind' => 'analysis', 'key' => $key, 'title' => $title, 'rule' => ['visible_to' => $rule['visible_to']], 'default' => $default];
}

// ---------------------------------------------------------------------------- providers

/** Gene sets: every gene-set directory under an assembly in the groups file or on disk. */
function moop_access_overview_gene_sets(array &$ctx): array {
    $seen = [];
    foreach ($ctx['groups'] as $k => $e) {
        if (($e['gene_set'] ?? '') !== '') $seen[$k] = [$e['organism'], $e['assembly'], $e['gene_set']];
    }
    // On disk but in no group: invisible to the public and to collaborators alike -- worth seeing.
    $safe = '/^[A-Za-z0-9_.-]+$/';
    foreach (glob($ctx['data'] . '/*/*/*/', GLOB_ONLYDIR) ?: [] as $dir) {
        [$org, $asm, $gs] = array_slice(explode('/', rtrim($dir, '/')), -3);
        if ($org[0] === '.' || $asm[0] === '.' || $gs[0] === '.' || !preg_match($safe, "$org$asm$gs")) continue;
        if (!is_file("{$ctx['data']}/$org/organism.sqlite") || !glob("$dir*.{gff,gff3,fa,fasta,gz}", GLOB_BRACE)) continue;
        $seen["$org/$asm/$gs"] ??= [$org, $asm, $gs];
    }
    ksort($seen, SORT_NATURAL | SORT_FLAG_CASE);
    $rows = [];
    foreach ($seen as [$org, $asm, $gs]) {
        $a = moop_access_overview_gene_set_audience($ctx, $org, $asm, $gs);
        if (!$a['exists']) continue;   // in the groups file only: retired or not yet loaded, nothing to see
        $rows[] = [
            'kind' => 'gene_set', 'group' => 'data', 'organism' => $org,
            'item' => $gs, 'detail' => "$org / $asm",
            'who' => moop_access_overview_audience_words($a), 'restricted' => !$a['public'],
            'why' => $a['groups'] ? 'Groups: ' . implode(', ', $a['groups']) : 'In no group',
            'edit' => null,
            'links' => [['url' => 'manage_groups.php', 'text' => 'Groups'], ['url' => 'manage_users.php', 'text' => 'Users']],
        ];
    }
    return $rows;
}

/** Expression experiments: one per bundle folder, as the expression readers find them. */
function moop_access_overview_expression(array &$ctx): array {
    require_once __DIR__ . '/expression_functions.php';
    $overrides = moop_expression_load_overrides(rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/expression_overrides.json');
    $rows = [];
    foreach (glob($ctx['data'] . '/*/*/*/expression/*/experiment.json') ?: [] as $f) {
        [$org, $asm, $gs, , $slug] = array_slice(explode('/', $f), -6, 5);
        $key = "expression:$org/$asm/$gs/$slug";
        if (!moop_analysis_key_valid($key)) continue;
        $meta = json_decode((string) @file_get_contents($f), true);
        $ov   = is_array($overrides) ? ($overrides["$org/$asm/$gs/$slug"] ?? []) : [];
        // The same default the expression reader uses (moop_expression_bundle_experiments()).
        $default = (is_array($ov) && isset($ov['access_level'])) ? 'admins'
                 : moop_access_level_to_setting(is_array($meta) ? (string) ($meta['access_level'] ?? '') : '');
        $rule = moop_analysis_rule($key, $default);
        $n = $overrides === null ? ['who' => 'Admins only', 'restricted' => true]
                                 : moop_access_overview_narrowed($rule['visible_to'], moop_access_overview_gene_set_audience($ctx, $org, $asm, $gs));
        $title = trim((string) (is_array($ov) && !empty($ov['label']) ? $ov['label'] : ($meta['label'] ?? $slug)));
        $rows[] = [
            'kind' => 'expression', 'group' => 'analysis', 'organism' => $org,
            'item' => $title !== '' ? $title : $slug, 'detail' => "$org / $asm / $gs",
            'who' => $n['who'], 'restricted' => $n['restricted'],
            'why' => $overrides === null ? 'expression_overrides.json unreadable -- all expression hidden'
                                         : moop_access_overview_analysis_why($rule, 'experiment.json access_level'),
            'edit' => moop_access_overview_analysis_edit($key, $title !== '' ? $title : $slug, $rule, $default),
            'links' => [],
        ];
    }
    return $rows;
}

/** Image analyses: one per analysis in each organism's gene_images.sqlite. */
function moop_access_overview_images(array &$ctx): array {
    require_once __DIR__ . '/gene_images.php';
    $rows = [];
    foreach (glob($ctx['data'] . '/*/gene_images.sqlite') ?: [] as $f) {
        $org = basename(dirname($f));
        $db = moop_images_open($org);
        if (!$db) continue;
        $sets = [];
        foreach ($db->query('SELECT DISTINCT analysis, assembly, gene_set FROM image WHERE analysis IS NOT NULL') as $r) {
            $sets[$r['analysis']][] = [(string) $r['assembly'], (string) $r['gene_set']];
        }
        $titles = [];
        foreach ($db->query('SELECT analysis, title FROM analysis') as $r) $titles[$r['analysis']] = (string) $r['title'];
        foreach ($sets as $an => $list) {
            $key = moop_image_analysis_key($org, (string) $an);
            if (!moop_analysis_key_valid($key)) continue;
            $rule = moop_analysis_rule($key);
            // One gene set: its audience applies. Several: say so -- each image keeps its own.
            $a = count($list) === 1 ? moop_access_overview_gene_set_audience($ctx, $org, $list[0][0], $list[0][1]) : null;
            $n = moop_access_overview_narrowed($rule['visible_to'], $a);
            if ($a === null) {
                foreach ($list as [$asm, $gs]) if (!moop_access_overview_gene_set_audience($ctx, $org, $asm, $gs)['public']) $n['restricted'] = true;
            }
            $title = $titles[$an] ?? (string) $an;
            $rows[] = [
                'kind' => 'images', 'group' => 'analysis', 'organism' => $org,
                'item' => $title, 'detail' => $org . ' / ' . implode(', ', array_map(fn($p) => $p[1], $list)),
                'who' => $n['who'], 'restricted' => $n['restricted'],
                'why' => moop_access_overview_analysis_why($rule, 'none'),
                'edit' => moop_access_overview_analysis_edit($key, $title, $rule, 'dataset'), 'links' => [],
            ];
        }
    }
    return $rows;
}

/** Ortholog runs: one per clade; each listed gene still needs its own gene set. */
function moop_access_overview_clades(array &$ctx): array {
    require_once __DIR__ . '/clade_homologs.php';
    $rows = [];
    foreach (moop_clades_open() as $clade => $db) {
        $key = "clade:$clade";
        if (!moop_analysis_key_valid($key)) continue;
        $meta = $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
        $members = $db->query('SELECT organism, assembly, gene_set FROM member')->fetchAll(PDO::FETCH_NUM);
        $rule = moop_analysis_rule($key);
        $n = moop_access_overview_narrowed($rule['visible_to'], null);
        $private = 0;
        foreach ($members as [$o, $a, $g]) if (!moop_access_overview_gene_set_audience($ctx, $o, $a, $g)['public']) $private++;
        if ($private) $n['restricted'] = true;
        $title = (string) ($meta['label'] ?? $clade);
        $rows[] = [
            'kind' => 'clade', 'group' => 'analysis', 'organism' => '',
            'item' => $title, 'detail' => count($members) . ' gene sets' . ($private ? ", $private not public" : ''),
            'who' => $n['who'], 'restricted' => $n['restricted'],
            'why' => moop_access_overview_analysis_why($rule, 'none'),
            'edit' => moop_access_overview_analysis_edit($key, $title, $rule, 'dataset'), 'links' => [],
        ];
    }
    return $rows;
}

/**
 * JBrowse tracks other than gene models, one row per assembly: the assembly's audience, then each
 * track's own level from the track sheet narrows it (api/jbrowse2/config.php loadFilteredTracks()).
 * Changed in the track sheet, not here -- the next sheet sync would overwrite an edit made here.
 * Gene-model tracks follow their gene set exactly and are not listed again.
 */
function moop_access_overview_tracks(array &$ctx): array {
    $base = ConfigManager::getInstance()->getPath('metadata_path') . '/jbrowse2-configs/tracks';
    $by = [];
    foreach (glob("$base/*/*/*/*.json") ?: [] as $f) {
        [$org, $asm] = array_slice(explode('/', $f), -4, 2);
        $t = json_decode((string) @file_get_contents($f), true);
        if (!is_array($t) || (string) ($t['metadata']['gene_set'] ?? '') !== '') continue;
        $setting = moop_access_level_to_setting(isset($t['metadata']['access_level']) ? (string) $t['metadata']['access_level'] : null);
        $by["$org/$asm"][$setting] = ($by["$org/$asm"][$setting] ?? 0) + 1;
    }
    ksort($by, SORT_NATURAL | SORT_FLAG_CASE);
    $words = ['dataset' => 'as the assembly', 'logged_in' => 'logged in', 'internal' => 'internal + admins', 'admins' => 'admins only'];
    $rows = [];
    foreach ($by as $oa => $counts) {
        [$org, $asm] = explode('/', $oa, 2);
        // The assembly is open to whoever may open ANY of its gene sets (moop_jbrowse_can_open_assembly()).
        $public = false;
        foreach ($ctx['groups'] as $e) if ($e['organism'] === $org && $e['assembly'] === $asm && entry_is_public($e)) $public = true;
        $narrow = array_sum($counts) - ($counts['dataset'] ?? 0);
        $parts = [];
        foreach ($words as $s => $w) if (!empty($counts[$s])) $parts[] = "{$counts[$s]} $w";
        $rows[] = [
            'kind' => 'tracks', 'group' => 'analysis', 'organism' => $org,
            'item' => array_sum($counts) . ' tracks', 'detail' => "$org / $asm",
            'who' => ($public ? 'Assembly public' : 'Assembly restricted') . ': ' . implode(', ', $parts),
            'restricted' => !$public || $narrow > 0,
            'why' => 'Track sheet (access_level column)', 'edit' => null,
            'links' => [['url' => 'manage_jbrowse.php', 'text' => 'JBrowse']],
        ];
    }
    return $rows;
}

/** The ID Translator: no rule of its own -- each id follows the gene set it comes from. */
function moop_access_overview_crosswalk(array &$ctx): array {
    require_once __DIR__ . '/crosswalk.php';
    $rows = [];
    foreach (moop_crosswalk_sources() as $org => $cfg) {
        if (!is_file(moop_crosswalk_file($org))) continue;
        $sets = [];
        foreach (array_merge(array_values((array) ($cfg['hubs'] ?? [])), array_values((array) ($cfg['sources'] ?? []))) as $c) {
            if (is_array($c) && !empty($c['gene_set'])) $sets[($c['assembly'] ?? '') . '/' . $c['gene_set']] = [(string) ($c['assembly'] ?? ''), (string) $c['gene_set']];
        }
        $private = [];
        foreach ($sets as [$asm, $gs]) if (!moop_access_overview_gene_set_audience($ctx, $org, $asm, $gs)['public']) $private[] = $gs;
        $rows[] = [
            'kind' => 'crosswalk', 'group' => 'data', 'organism' => $org,
            'item' => (string) ($cfg['name'] ?? 'ID Translator'), 'detail' => count($sets) . ' gene sets mapped',
            'who' => $private ? 'Each ID only to those who may see its gene set (' . count($private) . ' not public: ' . implode(', ', $private) . ')'
                              : 'Everyone (every mapped gene set is public)',
            'restricted' => (bool) $private, 'why' => 'Follows each gene set', 'edit' => null,
            'links' => [['url' => 'manage_crosswalk.php', 'text' => 'ID Translator']],
        ];
    }
    return $rows;
}

/** Anatomy (PAGE) curations: no rule of their own -- each gene follows its gene set via the crosswalk. */
function moop_access_overview_curations(array &$ctx): array {
    require_once __DIR__ . '/anatomy_expression.php';
    $rows = [];
    foreach (array_keys(moop_page_sources()) as $org) {
        $rows[] = [
            'kind' => 'curations', 'group' => 'data', 'organism' => $org,
            'item' => 'Anatomy curations (PAGE)', 'detail' => $org,
            'who' => 'Each gene only to those who may see its gene set', 'restricted' => false,
            'why' => 'Follows each gene set (through the ID Translator)', 'edit' => null, 'links' => [],
        ];
    }
    return $rows;
}

/** Content pages: their own rule (lib/access_rules.php "pages"). */
function moop_access_overview_pages(array &$ctx): array {
    require_once __DIR__ . '/content_pages.php';
    $rows = [];
    foreach (moop_page_list() as $slug => $p) {
        $rule = moop_page_rule((string) $slug);
        $to = $rule['visible_to'] ?? 'admins';
        $who = moop_access_rule_label($rule);
        if ($to === 'dataset' && ($rule['gene_set'] ?? '') !== '') {
            $who = moop_access_overview_audience_words(moop_access_overview_gene_set_audience($ctx, $rule['organism'], $rule['assembly'], $rule['gene_set']));
        }
        $title = (string) ($p['title']['en'] ?? $slug);
        $rows[] = [
            'kind' => 'page', 'group' => 'page', 'organism' => $to === 'dataset' ? (string) $rule['organism'] : '',
            'item' => $title, 'detail' => 'page.php?p=' . $slug,
            'who' => $who, 'restricted' => $to !== 'everyone',
            'why' => $to === 'everyone' ? 'Default' : ($to === 'dataset' ? moop_access_rule_label($rule) : 'Set here'),
            'edit' => ['kind' => 'page', 'key' => (string) $slug, 'title' => $title, 'rule' => $rule],
            'links' => [['url' => 'manage_pages.php?edit=' . rawurlencode((string) $slug), 'text' => 'Page']],
        ];
    }
    return $rows;
}
