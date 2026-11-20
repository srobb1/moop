<?php
/**
 * Example chips for the home page search boxes, chosen from the data -- never typed in.
 *
 * The examples used to be hard-coded SIMRbase IDs (Anoura caudifer, GCA_004027475.1,
 * XM_020002978.1 ...). Measured 2026-10-13: all 8 returned "No matches found" for an
 * anonymous visitor, because only Nematostella is public -- a first-time visitor's
 * first click found nothing. They were also site-specific data inside app code.
 *
 * THE RULES (agreed with the user, 2026-10-13). One organism carries the whole story:
 * its scientific name, common name, a group, an assembly and a gene set on the
 * Organisms tab; a gene ID and one of that gene's transcript IDs on the Genes tab.
 *
 *   Never relaxed: the AUDIENCE can open it. Examples are computed per audience --
 *   'all' (admin, lab network) or 'public' (everyone else; a collaborator sees at least
 *   the public set, so it is always openable for them too).
 *
 *   Ranked, most important first -- a candidate organism is scored by these in order
 *   and the best wins, which is exactly "relax the least important rule first":
 *     1. the organism is Complete in Manage Organisms (no empty DB, nothing missing)
 *     2. the chosen gene set has Protein, mRNA, CDS and a genes GFF
 *     3. its assembly has a genome and is registered in JBrowse
 *     4. a rich example gene: a real name, annotations, several isoforms
 *     5. a common name, unique among the audience's organisms
 *     6. a group holding 2+ of the audience's organisms
 *   Ties go alphabetically, so the examples are STABLE -- the same chips every load,
 *   which matters when giving tours.
 *
 *   Each chip must lead to ONE place: an assembly or gene-set name shared by another
 *   organism is left out rather than shown (one gene-set name here is shared by 28
 *   gene sets). A gene "name" that is really another feature's ID (37,913 of Congeria's
 *   43,768 genes are named after their own transcript) does not count as a name.
 *
 * ONE example system for the site: the Genes-tab IDs are also Sequence Retrieval's
 * "Load examples". An admin can override either list in Manage Site Configuration --
 * 'home_search_examples' (Organisms tab) and 'sample_feature_ids' (gene IDs, both pages);
 * entries the audience cannot open are dropped, and an empty list means automatic.
 *
 * Cached in the cache directory; recomputed only when the organism cache, the groups
 * file or an override changes.
 */

/** Bump when the selection rules change: invalidates every cached example. */
const MOOP_EXAMPLES_RULES_VERSION = 2;

/**
 * Example chips for the current viewer.
 *
 * @param array $group_data getGroupData()
 * @return array{organisms: list<array{text:string, kind:string}>, genes: list<array{text:string, kind:string}>}
 */
function moop_home_examples(array $group_data): array {
    $audience = (has_access('ADMIN') || has_access('IP_IN_RANGE')) ? 'all' : 'public';
    $config   = ConfigManager::getInstance();
    $override = [
        'organisms' => $config->getArray('home_search_examples', []),
        'genes'     => $config->getArray('sample_feature_ids', []),
    ];

    $cache_file  = moop_cache_root() . '/home_examples.json';
    $fingerprint = moop_home_examples_fingerprint($override);
    $cached      = is_file($cache_file) ? loadJsonFile($cache_file, []) : [];
    if (($cached['fingerprint'] ?? null) === $fingerprint && isset($cached[$audience])) {
        return $cached[$audience];
    }

    $entries = array_values(array_filter($group_data,
        fn($e) => $audience === 'all' || entry_is_public($e)));
    $result = moop_home_examples_apply_override(moop_home_examples_compute($entries), $override, $entries);

    // A cache we cannot write is a performance problem, never a correctness one.
    $cached = (($cached['fingerprint'] ?? null) === $fingerprint) ? $cached : ['fingerprint' => $fingerprint];
    $cached[$audience] = $result;
    if (moop_ensure_cache_dir(dirname($cache_file))) {
        @file_put_contents($cache_file, json_encode($cached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $result;
}

/** What the examples depend on: the organism cache, the groups file, the override. */
function moop_home_examples_fingerprint(array $override): string {
    $groups = ConfigManager::getInstance()->getPath('metadata_path') . '/organism_assembly_groups.json';
    $org_cache = moop_organism_cache_file();
    return md5(implode('|', [
        is_file($org_cache) ? filemtime($org_cache) : 0,
        is_file($groups) ? filemtime($groups) : 0,
        json_encode($override),
        MOOP_EXAMPLES_RULES_VERSION,
    ]));
}

/**
 * Pick the story organism and build both tabs' chips from it.
 *
 * @param array $entries the audience's group entries
 */
function moop_home_examples_compute(array $entries): array {
    $out = ['organisms' => [], 'genes' => []];
    if (!$entries) return $out;

    $cache = loadJsonFile(moop_organism_cache_file(), [])['data'] ?? [];

    // Audience-wide counts, for the "leads to one place" and group rules.
    $asm_owners = $gs_owners = $common_owners = $group_orgs = [];
    foreach ($entries as $e) {
        $asm_owners[$e['assembly']][$e['organism']] = true;
        $gs_owners[$e['gene_set'] ?? ''][$e['organism'] . '|' . $e['assembly']] = true;
        foreach ($e['groups'] ?? [] as $g) {
            if (strtoupper(trim((string)$g)) === 'PUBLIC') continue;   // legacy access keyword
            $group_orgs[$g][$e['organism']] = true;
        }
    }
    foreach (array_keys(array_column($entries, null, 'organism')) as $org) {
        $cn = trim((string)($cache[$org]['info']['common_name'] ?? ''));
        if ($cn !== '') $common_owners[strtolower($cn)][$org] = true;
    }

    // Score every organism on the rules that need no database (all but rule 4).
    $cands = [];
    foreach ($entries as $e) {
        $org = $e['organism'];
        $asm = $e['assembly'];
        $gs  = $e['gene_set'] ?? '';
        $d   = $cache[$org] ?? null;
        if (!$d || $gs === '') continue;

        $gaps    = $d['fasta_validation']['assemblies'][$asm]['gaps'] ?? null;
        $gs_full = $gaps !== null && ($gaps['gene_sets'][$gs] ?? null) === []
                && is_file("{$d['path']}/$asm/$gs/" . genes_gff_filename());
        $browse  = !empty($gaps['has_genome']) && isJBrowseAssemblyRegistered($org, $asm);

        $sci = trim(($d['info']['genus'] ?? '') . ' ' . ($d['info']['species'] ?? ''));
        if ($sci === '') $sci = str_replace('_', ' ', $org);
        $cn  = trim((string)($d['info']['common_name'] ?? ''));
        $cn_ok = $cn !== '' && strcasecmp($cn, $sci) !== 0 && count($common_owners[strtolower($cn)] ?? []) === 1;

        $best_group = null;
        foreach ($e['groups'] ?? [] as $g) {
            if (!isset($group_orgs[$g])) continue;
            if ($best_group === null || count($group_orgs[$g]) > count($group_orgs[$best_group])
                || (count($group_orgs[$g]) === count($group_orgs[$best_group]) && strcmp($g, $best_group) < 0)) {
                $best_group = $g;
            }
        }

        $c = [
            'organism' => $org, 'assembly' => $asm, 'gene_set' => $gs, 'path' => $d['path'],
            'sci' => $sci, 'common' => $cn_ok ? $cn : '', 'group' => $best_group,
            'complete' => moop_organism_severity($d['overall_status'] ?? []) === 'complete',
            'gs_full' => $gs_full, 'browse' => $browse, 'gene_tier' => -1,
            'group_ok' => $best_group !== null && count($group_orgs[$best_group]) >= 2,
        ];
        // One candidate per organism: its best assembly / gene set.
        if (!isset($cands[$org]) || moop_home_examples_cmp($c, $cands[$org]) < 0) $cands[$org] = $c;
    }
    if (!$cands) return $out;

    // Rule 4 needs the database, so only for the leading few -- it is ranked fourth,
    // after three rules that already separate most organisms.
    usort($cands, 'moop_home_examples_cmp');
    $top = array_slice($cands, 0, 5);
    foreach ($top as &$c) {
        $c['gene'] = moop_home_examples_gene($c);
        $c['gene_tier'] = $c['gene']['tier'] ?? -1;
    }
    unset($c);
    usort($top, 'moop_home_examples_cmp');
    $s = $top[0];

    $out['organisms'][] = ['text' => $s['sci'], 'kind' => 'organism'];
    if ($s['common'] !== '') $out['organisms'][] = ['text' => $s['common'], 'kind' => 'common'];
    if ($s['group'] !== null) $out['organisms'][] = ['text' => $s['group'], 'kind' => 'group'];
    if (count($asm_owners[$s['assembly']] ?? []) === 1) $out['organisms'][] = ['text' => $s['assembly'], 'kind' => 'assembly'];
    if (count($gs_owners[$s['gene_set']] ?? []) === 1) $out['organisms'][] = ['text' => $s['gene_set'], 'kind' => 'geneset'];
    if (!empty($s['gene'])) {
        $out['genes'][] = ['text' => $s['gene']['gene'], 'kind' => 'gene'];
        if ($s['gene']['transcript'] !== '') $out['genes'][] = ['text' => $s['gene']['transcript'], 'kind' => 'transcript'];
    }
    return $out;
}

/** Order two candidates by the rules, best first; ties alphabetically (stable). */
function moop_home_examples_cmp(array $a, array $b): int {
    foreach (['complete', 'gs_full', 'browse', 'gene_tier', 'common', 'group_ok'] as $k) {
        $va = $k === 'common' ? ($a[$k] !== '') : $a[$k];
        $vb = $k === 'common' ? ($b[$k] !== '') : $b[$k];
        if ($va != $vb) return $va > $vb ? -1 : 1;
    }
    return strcmp($a['organism'] . $a['assembly'] . $a['gene_set'], $b['organism'] . $b['assembly'] . $b['gene_set']);
}

/**
 * The richest example gene in a gene set, relaxing step by step:
 *   tier 3  real name, 5+ annotations, 2+ transcripts
 *   tier 2  real name, 5+ annotations
 *   tier 1  real name
 *   tier 0  any gene with a transcript
 * Annotations are counted on the transcripts (the loader attaches them there).
 *
 * @return array{gene:string, transcript:string, tier:int}|null
 */
function moop_home_examples_gene(array $c): ?array {
    $db_file = $c['path'] . '/organism.sqlite';
    if (!is_file($db_file)) return null;
    try {
        [$gs_id] = getGeneSetInfo($c['assembly'], $c['gene_set'], $db_file);
        if ($gs_id === null) return null;
        $dbh = getDbConnection($db_file);

        // A "real" name: present, not the gene's own ID, and not another feature's ID
        // (many loaders fill an unnamed gene's name with its transcript's ID).
        $real_name = "g.feature_name IS NOT NULL AND g.feature_name <> '' AND g.feature_name <> g.feature_uniquename
                      AND NOT EXISTS (SELECT 1 FROM feature x WHERE x.gene_set_id = g.gene_set_id AND x.feature_uniquename = g.feature_name)";
        $tiers = [
            3 => [$real_name, 'iso >= 2 AND ann >= 5'],
            2 => [$real_name, 'ann >= 5'],
            1 => [$real_name, 'iso >= 1'],
            0 => ['1', 'iso >= 1'],
        ];
        foreach ($tiers as $tier => [$where, $having]) {
            $st = $dbh->prepare("SELECT g.feature_id, g.feature_uniquename, COUNT(DISTINCT m.feature_id) AS iso,
                                        COUNT(fa.feature_annotation_id) AS ann
                                 FROM feature g
                                 JOIN feature m ON m.parent_feature_id = g.feature_id
                                 LEFT JOIN feature_annotation fa ON fa.feature_id = m.feature_id
                                 WHERE g.gene_set_id = ? AND g.feature_type = 'gene' AND g.parent_feature_id IS NULL AND $where
                                 GROUP BY g.feature_id HAVING $having
                                 ORDER BY g.feature_id LIMIT 1");
            $st->execute([$gs_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $tx = $dbh->prepare('SELECT feature_uniquename FROM feature WHERE parent_feature_id = ? ORDER BY feature_id LIMIT 1');
                $tx->execute([$row['feature_id']]);
                return ['gene' => $row['feature_uniquename'], 'transcript' => (string)$tx->fetchColumn(), 'tier' => $tier];
            }
        }
    } catch (Throwable $e) {
        error_log('home examples: ' . $c['organism'] . ': ' . $e->getMessage());
    }
    return null;
}

/**
 * Example IDs from ONE gene set -- for a page already filtered to it (Sequence Retrieval
 * arriving from an organism page, or after the user picks a source). Examples from the
 * site-wide story organism would be valid IDs the viewer can open, yet not found in the
 * gene set they are about to extract from.
 *
 * Same gene rules as the home page (moop_home_examples_gene()); the admin's
 * sample_feature_ids are a site-wide choice and apply only when there is no gene set.
 * Cached per gene set, keyed on the database's mtime, so a reload refreshes it.
 *
 * @return list<string> gene ID, then one of its transcript IDs; [] if the viewer cannot
 *                      open the gene set or it has no genes
 */
function moop_gene_set_examples(string $organism, string $assembly, string $gene_set): array {
    if ($organism === '' || $assembly === '' || $gene_set === '') return [];
    if (!has_gene_set_access($organism, $assembly, $gene_set)) return [];

    // The organism cache is the list of real organisms -- never build a path from the request.
    $path = loadJsonFile(moop_organism_cache_file(), [])['data'][$organism]['path'] ?? '';
    $db_file = $path === '' ? '' : "$path/organism.sqlite";
    if ($db_file === '' || !is_file($db_file)) return [];

    $cache_file = moop_cache_root() . '/gene_set_examples.json';
    $key        = "$organism|$assembly|$gene_set";
    $stamp      = filemtime($db_file) . '|' . MOOP_EXAMPLES_RULES_VERSION;
    $cached     = is_file($cache_file) ? loadJsonFile($cache_file, []) : [];
    if (($cached[$key]['stamp'] ?? null) === $stamp) return $cached[$key]['ids'];

    $gene = moop_home_examples_gene(['organism' => $organism, 'assembly' => $assembly, 'gene_set' => $gene_set, 'path' => $path]);
    $ids  = $gene ? array_values(array_filter([$gene['gene'], $gene['transcript']], 'strlen')) : [];

    // A cache we cannot write is a performance problem, never a correctness one.
    $cached[$key] = ['stamp' => $stamp, 'ids' => $ids];
    if (moop_ensure_cache_dir(dirname($cache_file))) {
        @file_put_contents($cache_file, json_encode($cached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $ids;
}

/**
 * Sequence Retrieval's examples: the gene set's gene and transcript, plus that transcript
 * with a range -- so loading the examples SHOWS a range working, next to the whole
 * sequences, rather than leaving the feature to a placeholder nobody reads.
 *
 * @param bool $fallback with no gene set examples, use the home page Genes tab's (the
 *                       page's first load); the browser's per-selection fetch passes false
 *                       and keeps what it was showing instead
 * @return array{ids: list<string>, gene: string, transcript: string} gene/transcript are ''
 *               when unknown (e.g. admin-chosen IDs, whose kind is not recorded)
 */
function moop_retrieval_examples(string $organism, string $assembly, string $gene_set, bool $fallback = true): array {
    $ids  = moop_gene_set_examples($organism, $assembly, $gene_set);
    $gene = $ids[0] ?? '';
    $tx   = $ids[1] ?? '';
    if (!$ids && $fallback) {
        $chips = moop_home_examples(getGroupData())['genes'];
        $ids   = array_column($chips, 'text');
        $kinds = array_column($chips, 'text', 'kind');
        $gene  = $kinds['gene'] ?? '';
        $tx    = $kinds['transcript'] ?? '';
    }
    $base = $tx !== '' ? $tx : (string)end($ids);
    if ($base !== '') $ids[] = "$base:1-60";
    return ['ids' => $ids, 'gene' => $gene, 'transcript' => $tx];
}

/**
 * Apply the admin's chosen examples (Manage Site Configuration), tab by tab. An entry
 * the audience cannot open is dropped; a tab left empty falls back to the automatic set.
 *
 * @param array $auto     moop_home_examples_compute() output
 * @param array $override ['organisms' => [text...], 'genes' => [id...]]
 * @param array $entries  the audience's group entries
 */
function moop_home_examples_apply_override(array $auto, array $override, array $entries): array {
    $cache = null;

    $org_terms = array_values(array_filter(array_map('trim', (array)($override['organisms'] ?? [])), 'strlen'));
    if ($org_terms) {
        $cache = $cache ?? (loadJsonFile(moop_organism_cache_file(), [])['data'] ?? []);
        $known = [];
        foreach ($entries as $e) {
            $d = $cache[$e['organism']] ?? [];
            foreach (array_merge(
                [$e['organism'], str_replace('_', ' ', $e['organism']), $e['assembly'], $e['gene_set'] ?? '',
                 trim(($d['info']['genus'] ?? '') . ' ' . ($d['info']['species'] ?? '')), $d['info']['common_name'] ?? ''],
                $e['groups'] ?? []) as $t) {
                if (trim((string)$t) !== '') $known[strtolower(trim((string)$t))] = true;
            }
        }
        $kept = array_values(array_filter($org_terms, fn($t) => isset($known[strtolower($t)])));
        if ($kept) $auto['organisms'] = array_map(fn($t) => ['text' => $t, 'kind' => 'custom'], $kept);
    }

    $gene_ids = array_values(array_filter(array_map('trim', (array)($override['genes'] ?? [])), 'strlen'));
    if ($gene_ids) {
        $cache = $cache ?? (loadJsonFile(moop_organism_cache_file(), [])['data'] ?? []);
        $kept = [];
        foreach ($gene_ids as $id) {
            if (moop_home_examples_id_visible($id, $entries, $cache)) $kept[] = ['text' => $id, 'kind' => 'custom'];
        }
        if ($kept) $auto['genes'] = $kept;
    }
    return $auto;
}

/** Does a feature with this exact ID exist in one of the audience's gene sets? */
function moop_home_examples_id_visible(string $id, array $entries, array $cache): bool {
    $by_org = [];
    foreach ($entries as $e) $by_org[$e['organism']][] = $e;
    foreach ($by_org as $org => $org_entries) {
        $db_file = ($cache[$org]['path'] ?? '') . '/organism.sqlite';
        if (!is_file($db_file)) continue;
        try {
            $dbh = getDbConnection($db_file);
            $st = $dbh->prepare('SELECT g.genome_accession, g.genome_name, gs.gene_set_name FROM feature f
                                 JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                                 JOIN genome g ON g.genome_id = gs.genome_id WHERE f.feature_uniquename = ?');
            $st->execute([$id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                foreach ($org_entries as $e) {
                    if (($e['gene_set'] ?? '') === $r['gene_set_name']
                        && in_array($e['assembly'], [$r['genome_accession'], $r['genome_name']], true)) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('home examples override: ' . $org . ': ' . $e->getMessage());
        }
    }
    return false;
}

// ---------------------------------------------------------------- curated-data examples

/**
 * Examples for the pages built on an organism's CURATED data -- Expression by Anatomy (its
 * publication and ID searches) and the Rosetta Stone. Chosen from the data, same as the home page
 * (2026-11-20; they were placeholders typed into the templates, which only fit S. mediterranea).
 *
 * THE RULES. One gene carries the story, so the chips teach what the pages are for -- one gene,
 * many IDs:
 *   - the gene: the curated accession recorded by the most papers (then the most records, then
 *     alphabetical, so the chips are STABLE between loads -- tours depend on it);
 *   - its IDs: that accession, the reference gene it maps to, then the other accessions the
 *     curators used for the same gene, most-used first, at most one per ID source (a GenBank
 *     protein next to its own mRNA teaches nothing). Five at most;
 *   - the papers: the three with the most records, as a PubMed ID, an author, and an author with a
 *     year -- each kept only if the publication search really finds it.
 * Never relaxed: crosswalk rows the viewer may not see are never used (moop_crosswalk_genes()).
 * Without a crosswalk the gene is the accession alone; without anatomy data there are no papers
 * and the gene comes from nothing -- the page keeps its placeholder.
 *
 * Cached in the cache directory (~100 ms to compute: a grouping over ~77k curations, then the
 * crosswalk's visibility checks), keyed on both files' dates and on the viewer's access, since what
 * the crosswalk shows depends on who is looking.
 *
 * @return array{ids: list<array{text:string, title:string}>, pubs: list<string>}
 */
function moop_curated_examples(string $organism): array {
    static $memo = [];
    if (isset($memo[$organism])) return $memo[$organism];
    require_once __DIR__ . '/anatomy_expression.php';
    require_once __DIR__ . '/crosswalk.php';
    $out = ['ids' => [], 'pubs' => []];
    if ($organism === '' || !is_file(moop_page_file($organism))) return $memo[$organism] = $out;

    require_once __DIR__ . '/cache_paths.php';
    require_once __DIR__ . '/functions_json.php';
    $cw_file    = moop_crosswalk_file($organism);
    $cache_file = moop_cache_root() . '/curated_examples.json';
    $key        = $organism . '|' . get_access_level() . '|' . (get_username() ?? '');
    $stamp      = filemtime(moop_page_file($organism)) . '|' . (is_file($cw_file) ? filemtime($cw_file) : 0) . '|' . MOOP_EXAMPLES_RULES_VERSION;
    $cached     = is_file($cache_file) ? loadJsonFile($cache_file, []) : [];
    if (($cached[$key]['stamp'] ?? null) === $stamp) return $memo[$organism] = $cached[$key]['examples'];
    $memo[$organism] = $out = moop_curated_examples_compute($organism);

    // A cache we cannot write is a performance problem, never a correctness one. One entry per
    // organism and viewer; the oldest go first past 200 (collaborators are few, but never unbounded).
    unset($cached[$key]);
    $cached[$key] = ['stamp' => $stamp, 'examples' => $out];
    $cached = array_slice($cached, -200, null, true);
    if (moop_ensure_cache_dir(dirname($cache_file))) {
        @file_put_contents($cache_file, json_encode($cached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $out;
}

/** moop_curated_examples() without the cache. */
function moop_curated_examples_compute(string $organism): array {
    $out = ['ids' => [], 'pubs' => []];
    try {
        $page = moop_page_open($organism);
        if (!$page) return $out;

        // The papers
        $top = $page->query("SELECT c.pmid, p.authors, p.year, COUNT(*) AS n FROM curation c
                               LEFT JOIN publication p ON p.pmid = c.pmid
                              WHERE c.pmid IS NOT NULL AND c.pmid <> ''
                              GROUP BY c.pmid ORDER BY n DESC, c.pmid LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $surname = fn($r) => preg_match('/^\s*([^\s,]+)/u', (string) ($r['authors'] ?? ''), $m) ? $m[1] : '';
        $pubs = [$top[0]['pmid'] ?? '', isset($top[1]) ? $surname($top[1]) : '',
                 isset($top[2]) && $surname($top[2]) !== '' ? trim($surname($top[2]) . ' ' . ($top[2]['year'] ?? '')) : ''];
        foreach ($pubs as $q) {
            if ($q !== '' && !in_array($q, $out['pubs'], true) && moop_page_find_publications($organism, $q, 1)) $out['pubs'][] = $q;
        }

        // The gene: the most-studied curated accession that reaches a gene the viewer may see
        $curated = $page->query("SELECT MIN(accession) AS accession, accession_lc, COUNT(*) AS n
                                   FROM curation GROUP BY accession_lc
                                  ORDER BY COUNT(DISTINCT pmid) DESC, n DESC, accession_lc LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
        if (!$curated) return $out;
        $cw = moop_crosswalk_open($organism);
        if (!$cw) {
            $out['ids'][] = ['text' => $curated[0]['accession'], 'title' => ''];
            return $out;
        }
        $hub_order = array_flip(array_keys((array) (moop_crosswalk_sources()[$organism]['hubs'] ?? [])));
        foreach ($curated as $c) {
            $genes = moop_crosswalk_genes($organism, [$c['accession']])[$c['accession_lc']] ?? [];
            if (!$genes) continue;
            usort($genes, fn($a, $b) => ($hub_order[$a['hub']] ?? 99) <=> ($hub_order[$b['hub']] ?? 99));
            ['ref_id' => $ref, 'hub' => $hub] = $genes[0];

            // Every visible accession of that gene, with its source
            $st = $cw->prepare('SELECT DISTINCT accession, accession_lc, source FROM map WHERE ref_id = ? AND hub = ?');
            $st->execute([$ref, $hub]);
            $src_of = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (moop_crosswalk_row_visible($organism, $hub, $r['source'])) $src_of[$r['accession_lc']] ??= [$r['accession'], $r['source']];
            }
            if (!isset($src_of[$c['accession_lc']])) continue;

            // The curated ones, most-used first
            $lc = array_keys($src_of);
            $q  = $page->prepare('SELECT accession_lc, COUNT(*) AS n FROM curation WHERE accession_lc IN ('
                                 . implode(',', array_fill(0, count($lc), '?')) . ') GROUP BY accession_lc ORDER BY n DESC, accession_lc');
            $q->execute($lc);
            $order = array_column($q->fetchAll(PDO::FETCH_ASSOC), 'accession_lc');

            $cfg   = moop_crosswalk_sources()[$organism] ?? [];
            $label = fn($src) => (string) ($cfg['sources'][$src]['label'] ?? $cfg['hubs'][$src]['label'] ?? $src);
            $pick  = [[$c['accession'], $label($src_of[$c['accession_lc']][1])], [$ref, $label($hub)]];
            $used  = [$src_of[$c['accession_lc']][1] => true, $hub => true];
            foreach ($order as $a) {
                [$acc, $src] = $src_of[$a];
                if (count($pick) >= 5) break;
                if (isset($used[$src]) || strcasecmp($acc, $ref) === 0) continue;
                $used[$src] = true;
                $pick[] = [$acc, $label($src)];
            }
            foreach ($pick as [$text, $title]) $out['ids'][] = ['text' => $text, 'title' => $title];
            break;
        }
    } catch (Throwable $e) {
        error_log('curated examples: ' . $organism . ': ' . $e->getMessage());
    }
    return $out;
}

/**
 * The example row under a search box: "e.g. a · b · c", each a button that puts its text into
 * $target (a CSS selector) and, with $submit, sends the form (js/modules/example-fill.js). Same look
 * as the home page's examples (.qs-examples, css/display.css).
 *
 * @param list<array{text:string, title?:string}|string> $items
 * @param string $lead already translated ("e.g." or a sentence)
 */
function moop_example_chips_html(array $items, string $target, bool $submit, string $lead): string {
    if (!$items) return '';
    $html = '<div class="qs-examples mt-1"><span class="text-muted me-1 small">' . htmlspecialchars($lead) . '</span>';
    foreach ($items as $it) {
        $text  = is_array($it) ? (string) $it['text'] : (string) $it;
        $title = is_array($it) ? (string) ($it['title'] ?? '') : '';
        $html .= '<button type="button" class="qs-example-chip" data-example-fill="' . htmlspecialchars($target) . '"'
               . ($submit ? ' data-example-submit' : '') . ($title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '')
               . ' data-value="' . htmlspecialchars($text) . '">' . htmlspecialchars($text) . '</button>';
    }
    return $html . '</div>';
}
