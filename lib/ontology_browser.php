<?php
/**
 * Ontology browser -- the data behind tools/ontology_browser.php (the tree) and
 * tools/ontology_term.php (a term page, laid out like a gene page). Plan: memory
 * plan_ontology_browser; first ontology PLANA (Planosphere's Molecular Fate Mapping Atlas was the
 * static prototype), GO later.
 *
 * Reads the per-ontology databases of lib/ontologies.php, using their `edge` table: each
 * relation's DIRECT links, worked out at build time. Read-only; nothing here writes.
 *
 * Which relations count: the ones configured for the ontology ('relations' in its entry of the
 * 'ontologies' setting -- for PLANA: is a, part of, contained in, develops from, derives from).
 * The closure also holds broad catch-all relations ("overlaps", "mereotopologically related")
 * that would only add noise; they are never shown.
 * Where the tree starts: 'ontology_browser' => [PREFIX => ['roots' => [term ids]]]; with none
 * set, the terms of that prefix that have no is-a parent of the same prefix.
 */
require_once __DIR__ . '/ontologies.php';

const MOOP_OB_ISA = 'rdfs:subClassOf';

/** Ontologies that can be browsed here: configured AND built. [prefix => name] */
function moop_ob_ontologies(): array {
    $out = [];
    foreach (moop_ontology_sources() as $p => $o) {
        if (moop_ontology_open($p) && !empty($o['closure'])) $out[$p] = (string) ($o['name'] ?? $p);
    }
    return $out;
}

/** The relations shown for this ontology, [id => label]; "is a" first. */
function moop_ob_relations(string $prefix): array {
    $ids = (array) (moop_ontology_sources()[$prefix]['relations'] ?? []);
    if (!in_array(MOOP_OB_ISA, $ids, true)) array_unshift($ids, MOOP_OB_ISA);
    $out = [];
    foreach ($ids as $r) $out[$r] = $r === MOOP_OB_ISA ? 'is a' : (moop_ontology_label($r, [$prefix]) ?: $r);
    return $out;
}

/** Strip the reference list some definitions end with ("... [GO:curators, PMID:1]"). */
function moop_ob_clean_def(?string $d): string {
    $d = trim((string) $d);
    $d = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $d);
    return trim($d, " \t\n\"");
}

/** One term, or null: id, label, definition, obsolete, replaced_by, synonyms [kind => [text]]. */
function moop_ob_term(string $prefix, string $id): ?array {
    $db = moop_ontology_open($prefix);
    if (!$db) return null;
    $st = $db->prepare('SELECT id, label, definition, obsolete, replaced_by FROM term WHERE id = ?');
    $st->execute([$id]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t || $t['label'] === null) return null;
    $st = $db->prepare('SELECT kind, text FROM synonym WHERE term_id = ? ORDER BY kind, text');
    $st->execute([$id]);
    $syn = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $syn[$s['kind']][] = $s['text'];
    return ['id' => $t['id'], 'label' => $t['label'], 'definition' => moop_ob_clean_def($t['definition']),
            'obsolete' => (bool) $t['obsolete'], 'replaced_by' => $t['replaced_by'], 'synonyms' => $syn];
}

/**
 * Terms directly linked to $id, in rows [id, label, definition, relation, children]:
 *   $direction 'down' = terms whose link points AT $id (its subclasses, its parts...)
 *   $direction 'up'   = terms $id points to (its superclasses, what it is part of...)
 * 'children' = how many terms sit directly below that row's term by the same relations (for the
 * tree's count and to know whether it can be opened). Obsolete terms are left out.
 */
function moop_ob_links(string $prefix, string $id, array $relations, string $direction = 'down'): array {
    $db = moop_ontology_open($prefix);
    if (!$db || !$relations) return [];
    $ph = implode(',', array_fill(0, count($relations), '?'));
    [$near, $far] = $direction === 'up' ? ['object', 'subject'] : ['subject', 'object'];
    $st = $db->prepare("SELECT e.$near AS id, e.relation, t.label, t.definition,
                               (SELECT COUNT(*) FROM edge k JOIN term kt ON kt.id = k.subject AND kt.obsolete = 0
                                 WHERE k.object = e.$near AND k.relation IN ($ph)) AS children
                          FROM edge e JOIN term t ON t.id = e.$near
                         WHERE e.$far = ? AND e.relation IN ($ph) AND t.obsolete = 0 AND t.label IS NOT NULL
                         ORDER BY t.label_lc, e.relation");
    $st->execute(array_merge($relations, [$id], $relations));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['definition'] = moop_ob_clean_def($r['definition']);
        $r['children'] = (int) $r['children'];
        $out[] = $r;
    }
    return $out;
}

/** Where the tree starts: the configured roots, else the prefix's terms with no is-a parent of that prefix. */
function moop_ob_roots(string $prefix): array {
    $db = moop_ontology_open($prefix);
    if (!$db) return [];
    $conf = (array) (ConfigManager::getInstance()->getArray('ontology_browser', [])[$prefix]['roots'] ?? []);
    $ids = array_values(array_filter(array_map('strval', $conf), fn($i) => preg_match(MOOP_ONTOLOGY_ID_RE, $i)));
    if (!$ids) {
        $st = $db->prepare("SELECT t.id FROM term t WHERE t.id LIKE ? AND t.obsolete = 0 AND t.type = 'class'
                               AND NOT EXISTS (SELECT 1 FROM edge e JOIN term p ON p.id = e.object
                                                WHERE e.subject = t.id AND e.relation = ? AND p.id LIKE ?)
                               AND EXISTS (SELECT 1 FROM edge c WHERE c.object = t.id AND c.relation = ?)
                             ORDER BY t.label_lc LIMIT 50");
        $st->execute([$prefix . ':%', MOOP_OB_ISA, $prefix . ':%', MOOP_OB_ISA]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    $out = [];
    foreach ($ids as $id) {
        $t = moop_ob_term($prefix, $id);
        if (!$t) continue;
        $kids = moop_ob_links($prefix, $id, [MOOP_OB_ISA]);
        $out[] = ['id' => $id, 'label' => $t['label'], 'definition' => $t['definition'], 'relation' => MOOP_OB_ISA,
                  'children' => count($kids)];
    }
    return $out;
}

/**
 * The is-a paths from the term up to a top term: lists of [id, label], top first. At most $max
 * paths (an anatomy term with several parents has several); stops at 40 levels (a loop guard).
 */
function moop_ob_paths(string $prefix, string $id, int $max = 12): array {
    $paths = [];
    $walk = function (string $tid, array $trail) use (&$walk, &$paths, $prefix, $max) {
        if (count($paths) >= $max || count($trail) > 40) return;
        $ups = moop_ob_links($prefix, $tid, [MOOP_OB_ISA], 'up');
        if (!$ups) { $paths[] = array_reverse($trail); return; }
        foreach ($ups as $u) {
            if (isset(array_column($trail, null, 'id')[$u['id']])) continue;   // a cycle
            $walk($u['id'], array_merge($trail, [['id' => $u['id'], 'label' => $u['label']]]));
        }
    };
    $t = moop_ob_term($prefix, $id);
    if (!$t) return [];
    $walk($id, [['id' => $id, 'label' => $t['label']]]);
    usort($paths, fn($a, $b) => count($a) <=> count($b));
    return $paths;
}

/**
 * The site's own written page about this term, if it has one: a content page whose address is
 * the term's name or one of its synonyms ("digestive system" -> page "digestive-system").
 * No configuration: name a page after the term and it appears on the term page.
 */
function moop_ob_written_page(array $term): ?array {
    require_once __DIR__ . '/content_pages.php';
    $names = array_merge([$term['label']], ...array_values($term['synonyms'] ?: [[]]));
    foreach ($names as $n) {
        $slug = moop_page_slugify((string) $n);
        if ($slug !== '' && ($page = moop_page_for_visitor($slug))) return ['slug' => $slug] + $page;
    }
    return null;
}

/** The browser's address for a term page. */
function moop_ob_term_url(string $prefix, string $id): string {
    return moop_url('tools/ontology_term.php') . '?' . http_build_query(['o' => $prefix, 'id' => $id]);
}

/**
 * Everything the term page shows besides the term itself: is-a paths, parents and kinds, the
 * other relationships both ways ([relation label => rows]), and the site's written page.
 */
function moop_ob_term_view(string $prefix, array $term): array {
    $rels  = moop_ob_relations($prefix);
    $other = array_values(array_diff(array_keys($rels), [MOOP_OB_ISA]));
    $group = function (array $rows) use ($rels) {
        $out = [];
        foreach ($rows as $r) $out[$rels[$r['relation']] ?? $r['relation']][] = $r;
        return $out;
    };
    return [
        'paths'    => moop_ob_paths($prefix, $term['id']),
        'parents'  => moop_ob_links($prefix, $term['id'], [MOOP_OB_ISA], 'up'),
        'kinds'    => moop_ob_links($prefix, $term['id'], [MOOP_OB_ISA], 'down'),
        'outgoing' => $group(moop_ob_links($prefix, $term['id'], $other, 'up')),     // part of X, develops from Y
        'incoming' => $group(moop_ob_links($prefix, $term['id'], $other, 'down')),   // X is part of this
        'written'  => moop_ob_written_page($term),
    ];
}


/**
 * Where this site has curated expression records for this ontology's terms (an anatomy ontology +
 * lib/anatomy_expression.php data this visitor may see): that organism, else ''.
 */
function moop_ob_records_organism(string $prefix): string {
    if ((moop_ontology_sources()[$prefix]['purpose'] ?? '') !== 'anatomy') return '';
    require_once __DIR__ . '/anatomy_expression.php';
    static $org = null;
    return $org ??= moop_page_visible_organism();
}

/**
 * A tree row's two numbers, both ways (the tree shows the totals while a row is closed and its
 * own share once it is opened):
 *   terms_all   -- every term below it (at any depth, by the tree's relations)
 *   records_all -- records on it AND everything below it      (null: no records on this site)
 *   records_own -- records on the term itself                  (null: no records on this site)
 * $parts: the tree also follows the ontology's other relations ("show parts too").
 */
function moop_ob_rollup(string $prefix, string $id, bool $parts, string $org): array {
    $purpose = (string) (moop_ontology_sources()[$prefix]['purpose'] ?? '');
    $rels = $parts ? array_values(array_diff(array_keys(moop_ob_relations($prefix)), [MOOP_OB_ISA])) : [];
    $ids  = moop_ontology_expand_in($purpose, $id, $rels);
    $out  = ['terms_all' => count($ids) - 1, 'records_all' => null, 'records_own' => null];
    if ($org !== '') {
        require_once __DIR__ . '/anatomy_expression.php';
        $spec = fn(array $t) => ['ok' => true, 'mode' => 'term', 'term_ids' => $t, 'filters' => [], 'accessions' => null, 'meta' => []];
        $out['records_all'] = moop_page_records($org, $spec($ids), '', 'paper', 'desc', 0, 1)['total'];
        $out['records_own'] = moop_page_records($org, $spec([$id]), '', 'paper', 'desc', 0, 1)['total'];
    }
    return $out;
}

/**
 * How many terms sit DIRECTLY below $id, by relationship: [relation label => n], "is a" first.
 * Explains a row's term count ("9 below: 3 is a, 6 part of").
 */
function moop_ob_direct_breakdown(string $prefix, string $id, array $relations): array {
    $db = moop_ontology_open($prefix);
    if (!$db || !$relations) return [];
    $ph = implode(',', array_fill(0, count($relations), '?'));
    $st = $db->prepare("SELECT e.relation, COUNT(DISTINCT e.subject) FROM edge e JOIN term t ON t.id = e.subject AND t.obsolete = 0
                         WHERE e.object = ? AND e.relation IN ($ph) GROUP BY e.relation");
    $st->execute(array_merge([$id], $relations));
    $names = moop_ob_relations($prefix);
    $out = [];
    foreach ($relations as $r) $out[$r] = 0;
    foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $r => $n) $out[$r] = (int) $n;
    $named = [];
    foreach (array_filter($out) as $r => $n) $named[$names[$r] ?? $r] = $n;
    return $named;
}

/**
 * Everything a tree row needs, for the API and the page's top level alike: the row, its two
 * counts both ways (moop_ob_rollup), the breakdown of terms directly below it, and the link to its
 * records in the Anatomical Expression Search (with the relationships the tree is following, so
 * the number there is the number here: "is a" alone, or "any of these" with parts).
 */
function moop_ob_tree_row(string $prefix, array $r, bool $parts, string $org): array {
    $rels = $parts ? array_keys(moop_ob_relations($prefix)) : [MOOP_OB_ISA];
    $row  = $r + moop_ob_rollup($prefix, $r['id'], $parts, $org);
    $row['below'] = moop_ob_direct_breakdown($prefix, $r['id'], $rels);
    $row['records_url'] = $org === '' ? '' : moop_ob_records_link($prefix, $org, $r['id'], $parts ? MOOP_ONTOLOGY_ANY_REL : MOOP_OB_ISA);
    return $row;
}

/**
 * Where a term's records are listed: this site's Anatomical Expression Search when it is switched
 * on here, else the site named by ontology_browser.<prefix>.records_url ({id}, {rel}) -- Planosphere
 * counts its own copy of the curations but lists them on SIMRbase. '' = nowhere.
 */
function moop_ob_records_link(string $prefix, string $org, string $id, string $rel): string {
    if (ConfigManager::getInstance()->isToolEnabled('anatomy_search')) {
        return moop_url('tools/anatomy_search.php') . '?' . http_build_query(['organism' => $org, 'term' => $id, 'rel' => $rel]);
    }
    $tpl = (string) (ConfigManager::getInstance()->getArray('ontology_browser', [])[$prefix]['records_url'] ?? '');
    if (!preg_match('#^https://[^\s"<>]+$#', $tpl) || strpos($tpl, '{id}') === false) return '';
    return str_replace(['{id}', '{rel}'], [rawurlencode($id), rawurlencode($rel)], $tpl);
}

