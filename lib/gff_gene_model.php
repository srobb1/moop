<?php
/**
 * ONE reader for "this gene and everything under it" from a gene set's GFF.
 *
 * Replaces three per-gene readers that each matched ids their own way (2026-10-13):
 *   tools/parent.php      gene-model diagram -- two copies of the parse, tabix and grep
 *   api/get_gff.php       the gene's GFF download
 *   lib/gene_isoforms.php Primer Maker's isoform list
 * The whole-file streaming readers (exon_coords.tsv generator, Data Exporter exon mode) are a
 * different job -- every gene in one pass -- and stay where they are.
 *
 * MATCHING IS BY WHOLE VALUE, never substring. Measured over all 72 gene sets with a GFF
 * (300 database ids each), a database id appears in its GFF in exactly one of three ways:
 *   the ID itself, version included        NV2g012457000.1   = ID=NV2g012457000.1   (45 sets)
 *   the ID after one source prefix         XM_014456012.2    = ID=rna-XM_014456012.2 (also gene:, transcript:)
 *   a RefSeq gene's NCBI GeneID            102421115         = Dbxref=...GeneID:102421115...
 * No set needed a version added or removed. The old readers used strpos(), so on gene X1's
 * page an overlapping neighbour X10 could be taken for X1, its transcripts drawn as X1's.
 *
 * PARENT LINKS: Parent= (may list several), or -- Schmidtea lugubris / S. nova as loaded in
 * 2026 -- a transcript carrying geneID= and no Parent. Reading only Parent= gave those two gene
 * sets no gene-structure diagram and no Primer Maker isoforms. (User, 2026-10-13: the incoming
 * reload writes Parent=; geneID stays accepted so the current data works until then.)
 */

require_once __DIR__ . '/jbrowse/gene_track_index.php';
require_once __DIR__ . '/feature_coords.php';

/** Column 9 as key => value. Values are left URL-encoded except for comparison purposes. */
function moop_gff_attrs(string $col9): array {
    $out = [];
    foreach (explode(';', trim($col9)) as $pair) {
        $eq = strpos($pair, '=');
        if ($eq === false) continue;
        $out[trim(substr($pair, 0, $eq))] = trim(substr($pair, $eq + 1));
    }
    return $out;
}

/**
 * A GFF ID without its source prefix: rna-XM_1 -> XM_1 (NCBI), transcript:FBtr1 -> FBtr1
 * (Ensembl). Unchanged when there is none. The database stores the bare form.
 */
function moop_gff_bare_id(string $id): string {
    return preg_replace('/^[A-Za-z]+[-:]/', '', $id);
}

/**
 * Every database id this GFF record could stand for (rules in the file header): its ID, its ID
 * after one source prefix, and any NCBI GeneID. The one place those rules live -- the per-gene
 * matcher below and whole-file readers (the Data Exporter's exon mode) both use it.
 *
 * @return list<string>
 */
function moop_gff_db_id_candidates(array $attrs): array {
    $out = [];
    $id  = $attrs['ID'] ?? '';
    if ($id !== '') {
        $out[] = $id;
        $bare  = moop_gff_bare_id($id);
        if ($bare !== $id) $out[] = $bare;
    }
    foreach (explode(',', $attrs['Dbxref'] ?? '') as $x) {
        if (strncmp($x, 'GeneID:', 7) === 0 && ctype_digit(substr($x, 7))) $out[] = substr($x, 7);
    }
    return $out;
}

/** Does this GFF record stand for the database feature $uniquename? (rules in the file header) */
function moop_gff_id_matches(array $attrs, string $uniquename): bool {
    return in_array($uniquename, moop_gff_db_id_candidates($attrs), true);
}

/** The ids this record hangs under: Parent= (comma list), else geneID= when it is not the record itself. */
function moop_gff_parents(array $attrs): array {
    if (($attrs['Parent'] ?? '') !== '') return explode(',', $attrs['Parent']);
    $g = $attrs['geneID'] ?? '';
    return ($g !== '' && $g !== ($attrs['ID'] ?? '')) ? [$g] : [];
}

/** A transcript-like child: mRNA, transcript, lnc_RNA, tRNA, ... */
function moop_gff_is_transcript_type(string $type): bool {
    $t = strtolower($type);
    return $t === 'transcript' || strpos($t, 'rna') !== false;
}

/**
 * Candidate GFF lines for a feature: the region from the tabix-indexed copy when there is one
 * (one seek), else greps of genes.gff. Exactness is applied afterwards, in PHP -- these only
 * have to be a superset.
 */
function moop_gff_candidate_lines(string $organism, string $assembly, string $gene_set, string $uniquename): array {
    $config  = ConfigManager::getInstance();
    $gs_dir  = $config->getPath('organism_data') . "/$organism/$assembly/$gene_set";
    $gff     = "$gs_dir/" . genes_gff_filename();
    $tabix   = $config->getPath('genomes_directory') . "/$organism/$assembly/$gene_set/annotations.gff3.gz";

    // The tabix copy only while it is sound and at least as new as genes.gff -- the same rule
    // housekeeping and JBrowse registration use (gene_track_index_problem()). It is a derived
    // copy (made for JBrowse), and on 2026-10-13 43 of them were older than their genes.gff --
    // three with a different id scheme entirely (Bradypodion_pumilum_... vs BraPum_...). The
    // gene page had been drawing those genes from the stale copy. A copy that is empty or
    // truncated is skipped too; it used to pass on age alone.
    $tabix_bin = moop_tool('tabix');
    $has_tabix = $tabix_bin !== '' && is_file($tabix)
              && gene_track_index_problem(substr($tabix, 0, -3)) === null;
    $loc = $has_tabix ? moop_feature_coords_loc($gs_dir, $uniquename) : null;
    if ($loc) {
        $lines = [];
        exec(escapeshellarg($tabix_bin) . ' ' . escapeshellarg($tabix) . ' ' . escapeshellarg($loc['loc_string']), $lines);
        // Only if the region really holds the feature; otherwise read genes.gff below.
        foreach ($lines as $line) {
            $p = explode("\t", $line, 9);
            if (isset($p[8]) && moop_gff_id_matches(moop_gff_attrs($p[8]), $uniquename)) return $lines;
        }
    }

    if (!is_file($gff) || filesize($gff) === 0) return [];
    // No index: every line naming the id (the feature, and anything whose attributes mention
    // it), then -- once the GFF id is known -- its children and theirs.
    $lines = [];
    exec('grep -F -e ' . escapeshellarg($uniquename) . ' ' . escapeshellarg($gff), $lines);
    return $lines;
}

/**
 * The gene (or other top-level feature) $uniquename: its record, its transcript-level children,
 * their children, and the drawable model.
 *
 * @param string $type the database feature_type ('gene', 'pseudogene', ...) -- the record must
 *                     be of this type; '' accepts any top-level record
 * @return array|null null when the GFF does not have it. Otherwise:
 *   gff_id   the record's own GFF ID (may differ from $uniquename: rna-, gene:, GeneID)
 *   loc      [seqname, start, end, strand, loc_string]
 *   lines    raw GFF lines, parent before child: the record, its children, theirs
 *   children [id, type, start, end, strand, lines] for each direct child
 *   isoforms the diagram's model: transcript-type children with exons/CDS (or, for a gene with
 *            CDS directly under it, each CDS id) --
 *            [id, type, start, end, strand, exons [start,end,type], cds [start,end]]
 */
function moop_gene_model(string $organism, string $assembly, string $gene_set, string $uniquename, string $type = 'gene'): ?array {
    $gff = ConfigManager::getInstance()->getPath('organism_data') . "/$organism/$assembly/$gene_set/" . genes_gff_filename();
    $parse = static function (array $lines): array {
        $out = [];
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === '#') continue;
            $p = explode("\t", $line);
            if (count($p) < 9) continue;
            $out[] = ['raw' => $line, 'p' => $p, 'a' => moop_gff_attrs($p[8])];
        }
        return $out;
    };

    $recs = $parse(moop_gff_candidate_lines($organism, $assembly, $gene_set, $uniquename));
    $gene = null;
    foreach ($recs as $r) {
        if ($type !== '' && strcasecmp($r['p'][2], $type) !== 0) continue;
        if ($type === '' && moop_gff_parents($r['a'])) continue;
        if (moop_gff_id_matches($r['a'], $uniquename)) { $gene = $r; break; }
    }
    if ($gene === null) return null;
    $gid = $gene['a']['ID'] ?? $uniquename;

    $children = array_values(array_filter($recs, fn($r) => in_array($gid, moop_gff_parents($r['a']), true)));
    // Without tabix the candidates were only lines naming $uniquename; children are named by
    // the GFF id instead (rna-X under gene-SYMBOL), so fetch them by it.
    if (!$children && is_file($gff)) {
        $more = [];
        exec('grep -F -e ' . escapeshellarg("Parent=$gid") . ' -e ' . escapeshellarg("geneID=$gid") . ' ' . escapeshellarg($gff), $more);
        $children = array_values(array_filter($parse($more), fn($r) => in_array($gid, moop_gff_parents($r['a']), true)));
        $recs = array_merge($recs, $children);
    }
    $child_ids = [];
    foreach ($children as $c) if (isset($c['a']['ID'])) $child_ids[$c['a']['ID']] = true;

    $in_children = fn($r) => (bool)array_intersect(moop_gff_parents($r['a']), array_keys($child_ids));
    $grand = array_values(array_filter($recs, $in_children));
    if ($child_ids && !$grand && is_file($gff)) {
        $more = [];
        $pats = '';
        foreach (array_keys($child_ids) as $cid) $pats .= ' -e ' . escapeshellarg("Parent=$cid");
        exec('grep -F' . $pats . ' ' . escapeshellarg($gff), $more);
        $grand = array_values(array_filter($parse($more), $in_children));
    }

    $gp  = $gene['p'];
    $loc = ['seqname' => $gp[0], 'start' => (int)$gp[3], 'end' => (int)$gp[4], 'strand' => $gp[6],
            'loc_string' => $gp[0] . ':' . $gp[3] . '-' . $gp[4]];

    $isoforms = [];
    $out_children = [];
    foreach ($children as $c) {
        $id = $c['a']['ID'] ?? '';
        $out_children[] = ['id' => $id, 'type' => $c['p'][2], 'start' => (int)$c['p'][3], 'end' => (int)$c['p'][4], 'strand' => $c['p'][6]];
        if ($id === '' || !moop_gff_is_transcript_type($c['p'][2])) continue;
        $isoforms[$id] = ['id' => $id, 'type' => $c['p'][2], 'start' => (int)$c['p'][3], 'end' => (int)$c['p'][4],
                          'strand' => $c['p'][6], 'exons' => [], 'cds' => []];
    }
    // A gene whose CDS hangs directly off it, with no transcript between: NCBI's prokaryotic
    // annotation (PGAP), and bacterial GFFs generally. The CDS IS the transcript level -- the
    // loader stores it as one (gene -> cds -> protein) -- so draw each CDS id as an isoform whose
    // exons are its own segments: no UTRs or introns are known. Several lines with one id (a
    // programmed frameshift) are one isoform with several segments.
    if (!$isoforms) {
        foreach ($children as $c) {
            $id = $c['a']['ID'] ?? '';
            if ($id === '' || strtolower($c['p'][2]) !== 'cds') continue;
            $seg = ['start' => (int)$c['p'][3], 'end' => (int)$c['p'][4]];
            $isoforms[$id] ??= ['id' => $id, 'type' => $c['p'][2], 'start' => $seg['start'], 'end' => $seg['end'],
                                'strand' => $c['p'][6], 'exons' => [], 'cds' => []];
            $isoforms[$id]['start'] = min($isoforms[$id]['start'], $seg['start']);
            $isoforms[$id]['end']   = max($isoforms[$id]['end'], $seg['end']);
            $isoforms[$id]['cds'][]   = $seg;
            $isoforms[$id]['exons'][] = $seg + ['type' => 'exon'];
        }
    }
    foreach ($grand as $g) {
        $ft = strtolower($g['p'][2]);
        $coord = ['start' => (int)$g['p'][3], 'end' => (int)$g['p'][4]];
        foreach (moop_gff_parents($g['a']) as $par) {
            if (!isset($isoforms[$par])) continue;
            if ($ft === 'cds') $isoforms[$par]['cds'][] = $coord;
            elseif (in_array($ft, ['exon', 'five_prime_utr', 'three_prime_utr', 'utr'], true))
                $isoforms[$par]['exons'][] = $coord + ['type' => $g['p'][2]];
        }
    }

    return [
        'gff_id'   => $gid,
        'loc'      => $loc,
        'lines'    => array_merge([$gene['raw']], array_column($children, 'raw'), array_column($grand, 'raw')),
        'children' => $out_children,
        'isoforms' => array_values(array_filter($isoforms, fn($i) => $i['exons'] || $i['cds'])),
    ];
}

/**
 * A feature's location from feature_coords.tsv: col 1 a feature id, col 2 its gene, then the
 * GENE's span (every row carries the gene's span), so either column finds it. Used to aim the
 * tabix fetch, and as the overview's Location when the GFF yields no record -- so the Location
 * never disappears just because the model did.
 *
 * @return array{seqname:string,start:int,end:int,strand:string,loc_string:string}|null
 */
function moop_feature_coords_loc(string $gene_set_dir, string $uniquename): ?array {
    $r = moop_feature_coords_find($gene_set_dir, $uniquename);
    if (!$r) return null;
    return ['seqname' => $r['chr'], 'start' => $r['start'], 'end' => $r['end'], 'strand' => $r['strand'],
            'loc_string' => "{$r['chr']}:{$r['start']}-{$r['end']}"];
}
