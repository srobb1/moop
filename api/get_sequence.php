<?php
/**
 * Get Genomic Sequence for a Region
 *
 * Returns the DNA sequence for a specific genomic region using the genome FASTA
 * and its .fai samtools index. No external tools needed — pure PHP fseek/fread.
 *
 * GET parameters:
 *   organism  - Organism name (required)
 *   assembly  - Assembly accession (required)
 *   seqname   - Chromosome / scaffold name as it appears in the FASTA (required)
 *   start     - 1-based start coordinate, inclusive (required)
 *   end       - 1-based end coordinate, inclusive (required)
 *   strand    - '+' or '-' (default: '+'); reverse-complements the result if '-'
 *
 * FASTA lookup order:
 *   1. organisms/{organism}/{assembly}/genome.fa  +  genome.fa.fai
 *   2. data/genomes/{organism}/{assembly}/reference.fasta  +  reference.fasta.fai
 *
 * Returns JSON: { sequence, start, end, strand, length }
 *           or: { error: "message" }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/moop_functions.php';

header('Content-Type: application/json');

// --- Validate required parameters ---
foreach (['organism', 'assembly', 'seqname', 'start', 'end'] as $p) {
    if (!isset($_GET[$p]) || $_GET[$p] === '') {
        http_response_code(400);
        echo json_encode(['error' => "Missing required parameter: $p"]);
        exit;
    }
}

$organism_data = $config->getPath('organism_data');
$organism_context = setupOrganismDisplayContext($_GET['organism'], $organism_data, true);
$organism_name    = $organism_context['name'];

// Sanitize assembly (accession IDs: letters, digits, dots, underscores, hyphens)
$assembly = preg_replace('/[^a-zA-Z0-9._\-]/', '', $_GET['assembly']);
if (empty($assembly)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid assembly parameter']);
    exit;
}

// Sanitize seqname (scaffold/chromosome names)
$seqname = preg_replace('/[^a-zA-Z0-9._\-: ]/', '', $_GET['seqname']);

$start  = (int)$_GET['start'];
$end    = (int)$_GET['end'];
$strand = ($_GET['strand'] ?? '+') === '-' ? '-' : '+';

// Coordinate sanity checks (max 500 kb to prevent abuse)
if ($start < 1 || $end < $start || ($end - $start + 1) > 500000) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or out-of-range coordinates (max 500,000 bp)']);
    exit;
}

// --- Access control ---
if (!has_assembly_access($organism_name, $assembly)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// --- Locate FASTA + FAI ---
$fasta = null;
$fai   = null;

// 1. Organism data directory (preferred)
$org_fasta = "$organism_data/$organism_name/$assembly/genome.fa";
$org_fai   = "$org_fasta.fai";
if (file_exists($org_fasta) && file_exists($org_fai)) {
    $fasta = $org_fasta;
    $fai   = $org_fai;
}

// 2. JBrowse genome directory (fallback)
if (!$fasta) {
    $genomes_dir = $config->getPath('genomes_directory');
    $jb_fasta    = "$genomes_dir/$organism_name/$assembly/reference.fasta";
    $jb_fai      = "$jb_fasta.fai";
    if (file_exists($jb_fasta) && file_exists($jb_fai)) {
        $fasta = $jb_fasta;
        $fai   = $jb_fai;
    }
}

if (!$fasta) {
    http_response_code(404);
    echo json_encode([
        'error' => 'No indexed genome FASTA found for this assembly. '
                 . 'Run: samtools faidx organisms/' . $organism_name . '/' . $assembly . '/genome.fa'
    ]);
    exit;
}

// --- Extract sequence ---
$sequence = extractFastaRegion($fasta, $fai, $seqname, $start, $end);

if ($sequence === null) {
    http_response_code(404);
    echo json_encode(['error' => "Sequence '$seqname' not found in FASTA index"]);
    exit;
}

if ($strand === '-') {
    $sequence = reverseComplement($sequence);
}

echo json_encode([
    'sequence' => $sequence,
    'start'    => $start,
    'end'      => $end,
    'strand'   => $strand,
    'length'   => strlen($sequence),
]);

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Extract a genomic region from an indexed FASTA file using pure PHP.
 *
 * Uses the samtools .fai index (5-column TSV: name, length, offset,
 * bases_per_line, bytes_per_line) to compute the exact byte position and
 * fseek/fread directly — no external tools required.
 *
 * @param string $fasta_path  Path to the FASTA file
 * @param string $fai_path    Path to the .fai index file
 * @param string $seqname     Sequence name (must match first column of .fai)
 * @param int    $start       1-based, inclusive start coordinate (GFF convention)
 * @param int    $end         1-based, inclusive end coordinate
 * @return string|null        Uppercase sequence string, or null if seqname not found
 */
function extractFastaRegion($fasta_path, $fai_path, $seqname, $start, $end)
{
    // Parse FAI to find the target sequence entry
    $fh    = fopen($fai_path, 'r');
    $entry = null;
    while (($line = fgets($fh)) !== false) {
        $parts = explode("\t", rtrim($line));
        if (count($parts) >= 5 && $parts[0] === $seqname) {
            $entry = [
                'length'         => (int)$parts[1],
                'offset'         => (int)$parts[2],   // byte offset of first base
                'bases_per_line' => (int)$parts[3],
                'bytes_per_line' => (int)$parts[4],   // includes the newline character(s)
            ];
            break;
        }
    }
    fclose($fh);

    if (!$entry || $entry['bases_per_line'] === 0) return null;

    // Convert 1-based GFF coords to 0-based
    $s   = $start - 1;          // 0-based start index
    $len = $end - $start + 1;   // number of bases to read

    // Byte offset formula:
    //   lines_before × bytes_per_line  +  remaining_bases_in_last_partial_line
    $byte_pos = $entry['offset']
              + intdiv($s, $entry['bases_per_line']) * $entry['bytes_per_line']
              + ($s % $entry['bases_per_line']);

    $fh = fopen($fasta_path, 'rb');
    fseek($fh, $byte_pos);

    $seq       = '';
    $remaining = $len;
    while ($remaining > 0) {
        // Read enough bytes to cover remaining bases plus any embedded newlines
        $read_len = $remaining + (int)ceil($remaining / $entry['bases_per_line']) + 4;
        $chunk    = fread($fh, min(65536, $read_len));
        if ($chunk === false || $chunk === '') break;

        // Strip line endings and accumulate bases
        $clean   = str_replace(["\r\n", "\r", "\n"], '', $chunk);
        $take    = min($remaining, strlen($clean));
        $seq    .= substr($clean, 0, $take);
        $remaining -= $take;
    }
    fclose($fh);

    return strtoupper($seq);
}

/**
 * Return the reverse complement of a DNA sequence.
 */
function reverseComplement($seq)
{
    return strrev(strtr(strtoupper($seq), ['A' => 'T', 'T' => 'A', 'C' => 'G', 'G' => 'C']));
}
