<?php
/**
 * Group / taxonomy consistency suggestions.
 *
 * MOOP groups organisms two ways: CURATED groups (metadata/organism_assembly_groups.json,
 * hand-assigned per gene set) and TAXONOMY ranks (metadata/taxonomy_tree_config.json,
 * derived from NCBI lineage). The curated side is maintained by hand, so it drifts.
 *
 * This module finds gene-set rows that the taxonomy says *probably* belong to a curated
 * group they are not in, and returns them as SUGGESTIONS.
 *
 * ⚠️ Suggestions only — nothing here ever writes to the groups file. Group membership is
 * editorial: the informal groups (`Sea anemone`, `Corals`, `Bats`) are not required to be
 * taxonomic, and silently auto-adding would make the file untrustworthy. See
 * notes/GROUP_TAXON_CHECKER_PLAN.md.
 *
 * Two bases for a suggestion:
 *
 *   'name'  — the group's NAME is itself a rank in the tree (group `Cnidaria` vs rank
 *             `Cnidaria`). The admin named it after the rank, so intent is explicit and
 *             no size guard is applied.
 *
 *   'cover' — the group's name is informal but every member sits under one rank, exactly
 *             (`Bats` == Chiroptera, 49/49; `Planaria` == Platyhelminthes, 15/15). A
 *             name-based check is blind to these, which matters because they are the
 *             biggest groups and the ones added in bulk.
 *
 * The 'cover' basis is guarded, because an unguarded version is actively harmful:
 *   - MIN_MEMBERS: a 1-2 member group has no reliable cover. `Fish` (2 members) is only
 *     jointly contained by Chordata, which also holds all 49 bats -> 54 false suggestions.
 *   - MAX_EXTRAS: the candidate rank must be close in size to the group.
 *   - deepest-node tie-break: among equally-small candidate ranks, take the most specific.
 *     Sorting by name instead picks `Arthropoda` as the cover for the 1-member `Fly`
 *     group, which would then suggest adding every future arthropod to `Fly`.
 *
 * Reads two JSON files and walks the tree. Touches no organism database (~10ms), so it
 * runs live on page load rather than through housekeeping.
 */

require_once __DIR__ . '/functions_json.php';

/** A 'cover' group needs at least this many members before its cover rank is trusted. */
const MOOP_GT_COVER_MIN_MEMBERS = 3;

/** Absolute floor on tolerated extras, for small-but-valid groups. */
const MOOP_GT_COVER_MIN_EXTRAS = 2;

/** Extras tolerated as a fraction of group size, when that is larger than the floor. */
const MOOP_GT_COVER_EXTRA_RATIO = 0.25;

/**
 * Index the taxonomy tree in one pass.
 *
 * Deliberately NOT getOrganismsAtTaxonomyLevel() — that is access-filtered (wrong for an
 * admin data check, which needs the truth rather than the viewer's slice) and would have
 * to be called once per rank.
 *
 * @param array $tree Root node of the taxonomy tree
 * @return array{ranks: array<string, array<string,bool>>, depth: array<string,int>, organisms: array<string,bool>}
 */
function moop_gt_index_tree(array $tree): array {
    $ranks     = [];   // rank name => [organism => true]
    $depth     = [];   // rank name => depth from root
    $organisms = [];   // organism => true

    $walk = function (array $node, array $path) use (&$walk, &$ranks, &$depth, &$organisms) {
        $name = $node['name'] ?? null;

        // A node carrying an 'organism' key is a leaf for our purposes: it is a species,
        // not a rank you could file other organisms under.
        if (!empty($node['organism'])) {
            $org = $node['organism'];
            $organisms[$org] = true;
            foreach ($path as $ancestor) {
                $ranks[$ancestor][$org] = true;
            }
            return;
        }

        if ($name !== null && $name !== '') {
            if (!isset($depth[$name])) {
                $depth[$name] = count($path);
            }
            if (!isset($ranks[$name])) {
                $ranks[$name] = [];
            }
            $path[] = $name;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $walk($child, $path);
            }
        }
    };

    $walk($tree, []);

    return ['ranks' => $ranks, 'depth' => $depth, 'organisms' => $organisms];
}

/**
 * Pick the taxonomy rank that best "covers" a group's members, or null if none is
 * trustworthy. See the guards documented at the top of this file.
 *
 * @param array<string,bool> $members       organism => true, restricted to organisms in the tree
 * @param array              $index         from moop_gt_index_tree()
 * @param array<string,bool> $known_orgs    organisms present in the groups file
 * @return array{rank:string, extras:array<int,string>}|null
 */
function moop_gt_cover_rank(array $members, array $index, array $known_orgs): ?array {
    $n = count($members);
    if ($n < MOOP_GT_COVER_MIN_MEMBERS) {
        return null;
    }

    $best = null;
    foreach ($index['ranks'] as $rank => $rank_orgs) {
        // Only organisms we actually hold count toward the comparison; a rank listing
        // organisms absent from the groups file should not be penalised for them.
        $held = array_intersect_key($rank_orgs, $known_orgs);
        if (count($held) < $n) {
            continue;                       // too small to contain every member
        }
        foreach ($members as $org => $_) {
            if (!isset($held[$org])) {
                continue 2;                 // does not contain them all
            }
        }
        $size  = count($held);
        $depth = $index['depth'][$rank] ?? 0;
        // Smallest containing rank wins; ties go to the DEEPEST (most specific) node.
        if ($best === null || $size < $best['size'] || ($size === $best['size'] && $depth > $best['depth'])) {
            $best = ['rank' => $rank, 'size' => $size, 'depth' => $depth, 'held' => $held];
        }
    }

    if ($best === null) {
        return null;                        // polyphyletic: no single rank contains them
    }

    $extras = array_keys(array_diff_key($best['held'], $members));
    $allowed = max(MOOP_GT_COVER_MIN_EXTRAS, (int) ceil(MOOP_GT_COVER_EXTRA_RATIO * $n));
    if (count($extras) > $allowed) {
        return null;                        // rank is far bigger than the group
    }

    sort($extras);
    return ['rank' => $best['rank'], 'extras' => $extras];
}

/**
 * Compute group/taxonomy suggestions.
 *
 * @param array $group_data  decoded organism_assembly_groups.json
 * @param array $tree        decoded taxonomy_tree_config.json['tree']
 * @param array $exceptions  dismissed suggestions (see moop_gt_load_exceptions())
 * @return array{
 *   suggestions: array<int, array{organism:string,assembly:string,gene_set:string,group:string,rank:string,basis:string,group_size:int,rank_size:int}>,
 *   dismissed: array<int, array>,
 *   groups_checked: array<string, array{basis:string,rank:string,members:int,rank_size:int}>,
 *   row_count: int
 * }
 */
function moop_gt_compute(array $group_data, array $tree, array $exceptions = []): array {
    $index = moop_gt_index_tree($tree);

    // group => [organism => true]; organism => list of rows
    $group_members = [];
    $known_orgs    = [];
    $rows          = [];
    foreach ($group_data as $entry) {
        $org = $entry['organism'] ?? null;
        if ($org === null || $org === '') {
            continue;
        }
        $known_orgs[$org] = true;
        $groups = $entry['groups'] ?? [];
        if (!is_array($groups)) {
            $groups = [];
        }
        $rows[] = [
            'organism' => $org,
            'assembly' => $entry['assembly'] ?? '',
            'gene_set' => $entry['gene_set'] ?? 'v1',
            'groups'   => array_flip($groups),
        ];
        foreach ($groups as $g) {
            $group_members[$g][$org] = true;
        }
    }

    // Which rank, if any, each curated group corresponds to.
    $group_rank = [];
    foreach ($group_members as $group => $members) {
        // Organisms absent from the taxonomy tree cannot inform a cover; they are already
        // reported separately as the 'not_in_tree' health alert.
        $in_tree = array_intersect_key($members, $index['organisms']);

        if (isset($index['ranks'][$group])) {
            $held = array_intersect_key($index['ranks'][$group], $known_orgs);
            $group_rank[$group] = [
                'rank'      => $group,
                'basis'     => 'name',
                'members'   => count($members),
                'rank_size' => count($held),
                'rank_orgs' => $held,
            ];
            continue;
        }

        if (empty($in_tree)) {
            continue;
        }
        $cover = moop_gt_cover_rank($in_tree, $index, $known_orgs);
        if ($cover === null) {
            continue;
        }
        $held = array_intersect_key($index['ranks'][$cover['rank']], $known_orgs);
        $group_rank[$group] = [
            'rank'      => $cover['rank'],
            'basis'     => 'cover',
            'members'   => count($members),
            'rank_size' => count($held),
            'rank_orgs' => $held,
        ];
    }

    // Index dismissals by organism + group (the editorial unit: "this organism does not
    // belong in that group", independent of how many gene sets it has).
    $dismissed_key = [];
    foreach ($exceptions as $ex) {
        $k = ($ex['organism'] ?? '') . "\0" . ($ex['group'] ?? '');
        $dismissed_key[$k] = $ex;
    }

    $suggestions = [];
    $dismissed   = [];
    foreach ($rows as $row) {
        foreach ($group_rank as $group => $info) {
            if (isset($row['groups'][$group])) {
                continue;                                   // already a member
            }
            if (!isset($info['rank_orgs'][$row['organism']])) {
                continue;                                   // not under that rank
            }
            $item = [
                'organism'   => $row['organism'],
                'assembly'   => $row['assembly'],
                'gene_set'   => $row['gene_set'],
                'group'      => $group,
                'rank'       => $info['rank'],
                'basis'      => $info['basis'],
                'group_size' => $info['members'],
                'rank_size'  => $info['rank_size'],
            ];
            $k = $row['organism'] . "\0" . $group;
            if (isset($dismissed_key[$k])) {
                $item['reason']       = $dismissed_key[$k]['reason'] ?? '';
                $item['dismissed_by'] = $dismissed_key[$k]['by'] ?? '';
                $item['dismissed_at'] = $dismissed_key[$k]['at'] ?? '';
                $dismissed[] = $item;
            } else {
                $suggestions[] = $item;
            }
        }
    }

    usort($suggestions, function ($a, $b) {
        return [$a['group'], $a['organism'], $a['gene_set']] <=> [$b['group'], $b['organism'], $b['gene_set']];
    });

    $checked = [];
    foreach ($group_rank as $group => $info) {
        unset($info['rank_orgs']);
        $checked[$group] = $info;
    }

    return [
        'suggestions'    => $suggestions,
        'dismissed'      => $dismissed,
        'groups_checked' => $checked,
        'row_count'      => count($rows),
    ];
}

/**
 * Path of the dismissal file.
 *
 * ⚠️ If you move this, update BOTH lib/permission_check.php (the 'Metadata Configuration
 * Files' rule lists metadata files INDIVIDUALLY — a missing entry means the writability
 * check never runs and a failed write is reported as success) and the snapshot list in
 * lib/housekeeping.php.
 */
function moop_gt_exceptions_file(): string {
    return ConfigManager::getInstance()->getPath('metadata_path') . '/group_taxon_exceptions.json';
}

/**
 * Dismissed suggestions — "yes, I meant that".
 *
 * @return array<int, array{organism:string,group:string,reason:string,by:string,at:string}>
 */
function moop_gt_load_exceptions(): array {
    $data = loadJsonFile(moop_gt_exceptions_file(), []);
    return is_array($data) ? $data : [];
}

/**
 * Record or clear a dismissal. Keyed by organism + group.
 *
 * @param string $organism
 * @param string $group
 * @param string $reason  free text; '' is allowed
 * @param string $by      username for the audit trail
 * @param bool   $remove  true to restore a previously dismissed suggestion
 * @return array{ok:bool, error:string}
 */
function moop_gt_set_exception(string $organism, string $group, string $reason, string $by, bool $remove = false): array {
    $file = moop_gt_exceptions_file();
    $list = moop_gt_load_exceptions();

    $kept = [];
    foreach ($list as $ex) {
        if (($ex['organism'] ?? '') === $organism && ($ex['group'] ?? '') === $group) {
            continue;                        // drop any existing entry; re-added below
        }
        $kept[] = $ex;
    }

    if (!$remove) {
        $kept[] = [
            'organism' => $organism,
            'group'    => $group,
            'reason'   => $reason,
            'by'       => $by,
            'at'       => date('c'),
        ];
    }

    usort($kept, function ($a, $b) {
        return [$a['organism'] ?? '', $a['group'] ?? ''] <=> [$b['organism'] ?? '', $b['group'] ?? ''];
    });

    // A write the web server cannot do, unchecked and reported as success, is THE
    // recurring failure shape in this codebase. Check the return value.
    $bytes = saveJsonFile($file, $kept);
    if ($bytes === false) {
        $dir = dirname($file);
        $hint = !is_dir($dir)
            ? "metadata directory $dir does not exist"
            : (file_exists($file)
                ? "cannot write $file — check owner/mode and the SELinux label (httpd_sys_rw_content_t)"
                : "cannot create files in $dir — check owner/mode and the SELinux label");
        return ['ok' => false, 'error' => $hint];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Load both metadata files and compute suggestions. The convenient entry point.
 *
 * Reads the LIVE groups file (not the organism cache) so the result is accurate
 * immediately after a group edit, matching computeDataHealthAlerts()'s grouping checks.
 *
 * @return array same shape as moop_gt_compute()
 */
function moop_gt_suggestions(): array {
    $metadata_path = ConfigManager::getInstance()->getPath('metadata_path');
    $group_data    = loadJsonFile("$metadata_path/organism_assembly_groups.json", []);
    $tree_config   = loadJsonFile("$metadata_path/taxonomy_tree_config.json", []);
    $tree          = $tree_config['tree'] ?? [];

    if (!is_array($group_data) || empty($tree)) {
        return ['suggestions' => [], 'dismissed' => [], 'groups_checked' => [], 'row_count' => 0];
    }

    return moop_gt_compute($group_data, $tree, moop_gt_load_exceptions());
}
