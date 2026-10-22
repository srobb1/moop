<?php
/**
 * feature_coords.tsv -- where each feature of a gene set sits -- read in ONE place.
 *
 * One row per feature, written at JBrowse registration from the GFF:
 *   feature_id  gene_id  seqname  start  end  strand
 * EVERY row carries the GENE's span, the transcript rows too, so a lookup by either column
 * gives the gene's locus (a transcript's own span is in exon_coords.tsv; see
 * lib/genomic_regions.php). Derived ids such as "X:cds" / "X:pep" appear in column 1.
 *
 * Six functions used to read this file on their own -- by column 1, by column 2, the
 * distinct column 3 -- with their own trimming (one trimmed the strand and its twin did not)
 * and their own idea of a short row. They are now the lookups below; callers pick one.
 *
 * Every lookup streams the file (the largest is 74 MB, 1.1 M rows) and skips a line with a
 * substring test before splitting it: one id on Myotis takes 0.12 s, against 0.90 s for the
 * awk the gene page used to shell out to.
 */

function moop_feature_coords_file(string $gene_set_dir): string {
    return "$gene_set_dir/feature_coords.tsv";
}

/**
 * One line, split. Strand is optional (".") -- every producer writes it, but a five-column
 * row still says where the feature is.
 *
 * @return array{id: string, gene_id: string, chr: string, start: int, end: int, strand: string}|null
 */
function moop_feature_coords_parse(string $line): ?array {
    $p = explode("\t", rtrim($line, "\r\n"));
    if (count($p) < 5 || $p[0] === '' || $p[2] === '') return null;
    $strand = trim($p[5] ?? '');
    return ['id' => $p[0], 'gene_id' => $p[1], 'chr' => trim($p[2]), 'start' => (int)$p[3], 'end' => (int)$p[4],
            'strand' => $strand === '' ? '.' : $strand];
}

/** Just the place: what callers merge into their own rows. */
function moop_feature_coords_locus(array $row): array {
    return ['chr' => $row['chr'], 'start' => $row['start'], 'end' => $row['end'], 'strand' => $row['strand']];
}

/**
 * Rows for these feature ids (column 1) -- BLAST hits, which may be transcripts or proteins.
 * Stops reading once every id is found.
 *
 * @param list<string> $ids
 * @return array<string, array{gene_id: string, chr: string, start: int, end: int, strand: string}>
 */
function moop_feature_coords_by_feature(string $gene_set_dir, array $ids): array {
    $want = array_flip($ids);
    $out  = [];
    if (!$want || !($fh = @fopen(moop_feature_coords_file($gene_set_dir), 'r'))) return $out;
    while (($line = fgets($fh)) !== false) {
        $tab = strpos($line, "\t");
        if ($tab === false || !isset($want[$id = substr($line, 0, $tab)]) || isset($out[$id])) continue;
        $row = moop_feature_coords_parse($line);
        if (!$row) continue;
        $out[$id] = ['gene_id' => $row['gene_id']] + moop_feature_coords_locus($row);
        if (count($out) === count($want)) break;
    }
    fclose($fh);
    return $out;
}

/**
 * The locus of each of these genes (column 2; the first row for a gene wins -- they all
 * carry the same span). An empty list asks for nothing and reads nothing.
 *
 * @param list<string> $gene_ids
 * @return array<string, array{chr: string, start: int, end: int, strand: string}>
 */
function moop_feature_coords_by_gene(string $gene_set_dir, array $gene_ids): array {
    $want = array_flip($gene_ids);
    $out  = [];
    if (!$want || !($fh = @fopen(moop_feature_coords_file($gene_set_dir), 'r'))) return $out;
    while (($line = fgets($fh)) !== false) {
        $p = explode("\t", $line, 3);
        if (!isset($p[2]) || !isset($want[$p[1]]) || isset($out[$p[1]])) continue;
        $row = moop_feature_coords_parse($line);
        if ($row) $out[$row['gene_id']] = moop_feature_coords_locus($row);
    }
    fclose($fh);
    return $out;
}

/**
 * The first row whose feature id OR gene id is $id -- one feature, for a page load.
 *
 * @return array{id: string, gene_id: string, chr: string, start: int, end: int, strand: string}|null
 */
function moop_feature_coords_find(string $gene_set_dir, string $id): ?array {
    if ($id === '' || !($fh = @fopen(moop_feature_coords_file($gene_set_dir), 'r'))) return null;
    $hit = null;
    while (($line = fgets($fh)) !== false) {
        if (strpos($line, $id) === false) continue;
        $row = moop_feature_coords_parse($line);
        if ($row && ($row['id'] === $id || $row['gene_id'] === $id)) { $hit = $row; break; }
    }
    fclose($fh);
    return $hit;
}

/**
 * The first row of the file that is not a derived id ("X:cds", "X:pep") -- somewhere to
 * open the genome browser.
 */
function moop_feature_coords_first(string $gene_set_dir): ?array {
    if (!($fh = @fopen(moop_feature_coords_file($gene_set_dir), 'r'))) return null;
    $hit = null;
    while (($line = fgets($fh)) !== false) {
        $row = moop_feature_coords_parse($line);
        if ($row && strpos($row['id'], ':') === false) { $hit = $row; break; }
    }
    fclose($fh);
    return $hit;
}

/**
 * The distinct sequence names the coordinates use (column 3), in natural order -- the Data
 * Exporter's chromosome list (moopmartGetChrNames() caches it) and the chromosome-name
 * health check (moop_gene_set_seqname_gap()).
 *
 * @return list<string>|null null when the gene set has no feature_coords.tsv
 */
function moop_feature_coords_seqnames(string $gene_set_dir): ?array {
    if (!($fh = @fopen(moop_feature_coords_file($gene_set_dir), 'r'))) return null;
    $chrs = [];
    while (($line = fgets($fh)) !== false) {
        $p = explode("\t", $line, 4);
        if (isset($p[2]) && ($c = trim($p[2])) !== '') $chrs[$c] = true;
    }
    fclose($fh);
    $out = array_map('strval', array_keys($chrs));
    sort($out, SORT_NATURAL);
    return $out;
}
