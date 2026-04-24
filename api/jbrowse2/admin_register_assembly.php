<?php
/**
 * JBrowse Admin API: Register Assembly
 *
 * Prepares genome files and creates the assembly metadata JSON for a
 * previously unregistered organism/assembly. Equivalent to running
 * setup_jbrowse_assembly.sh + add_assembly_to_jbrowse.sh.
 *
 * POST params:
 *   organism  - organism directory name (e.g. Anoura_caudifer)
 *   assembly  - assembly ID (e.g. GCA_004027475.1)
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism = trim($_POST['organism'] ?? '');
$assembly = trim($_POST['assembly'] ?? '');

if (empty($organism) || empty($assembly)) {
    echo json_encode(['success' => false, 'error' => 'Organism and assembly are required']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $organism) || !preg_match('/^[A-Za-z0-9_\-\.]+$/', $assembly)) {
    echo json_encode(['success' => false, 'error' => 'Invalid organism or assembly name']);
    exit;
}

$config = ConfigManager::getInstance();
$site        = $config->getString('site');
$site_path   = $config->getPath('site_path');
$organisms_dir  = $config->getPath('organism_data');
$metadata_path  = $config->getPath('metadata_path');

$log = [];

// ── Validate source ──────────────────────────────────────────────────────────

$source_dir = "$organisms_dir/$organism/$assembly";
if (!is_dir($source_dir)) {
    echo json_encode(['success' => false, 'error' => "Assembly directory not found: $source_dir"]);
    exit;
}

$assembly_json = "$metadata_path/jbrowse2-configs/assemblies/{$organism}_{$assembly}.json";
if (file_exists($assembly_json)) {
    echo json_encode(['success' => false, 'error' => "Assembly already registered: {$organism}_{$assembly}"]);
    exit;
}

$source_fasta = "$source_dir/genome.fa";
if (!file_exists($source_fasta)) {
    echo json_encode(['success' => false, 'error' => "genome.fa not found in $source_dir"]);
    exit;
}

// ── Step 1: Create data/genomes directory ────────────────────────────────────

$genomes_dir = "$site_path/data/genomes/$organism/$assembly";
if (!is_dir($genomes_dir)) {
    if (!mkdir($genomes_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => "Failed to create directory: $genomes_dir"]);
        exit;
    }
    $log[] = "Created: data/genomes/$organism/$assembly/";
}

// ── Step 2: Symlink reference.fasta ─────────────────────────────────────────

$target_fasta = "$genomes_dir/reference.fasta";
if (!file_exists($target_fasta) && !is_link($target_fasta)) {
    if (!symlink($source_fasta, $target_fasta)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create symlink for reference.fasta']);
        exit;
    }
    $log[] = 'Symlinked reference.fasta';
} else {
    $log[] = 'reference.fasta already exists';
}

// ── Step 3: samtools faidx ───────────────────────────────────────────────────

if (!file_exists("$target_fasta.fai")) {
    $cmd = 'samtools faidx ' . escapeshellarg($target_fasta) . ' 2>&1';
    exec($cmd, $out, $rc);
    if ($rc !== 0) {
        echo json_encode(['success' => false, 'error' => 'samtools faidx failed: ' . implode("\n", $out)]);
        exit;
    }
    $log[] = 'Indexed FASTA (samtools faidx)';
} else {
    $log[] = 'FASTA index already exists';
}

// ── Step 4: GFF (optional) ───────────────────────────────────────────────────

$source_gff = "$source_dir/genomic.gff";
if (file_exists($source_gff)) {
    $target_gff = "$genomes_dir/annotations.gff3";
    $gz_file    = "$genomes_dir/annotations.gff3.gz";
    $tbi_file   = "$gz_file.tbi";

    if (!file_exists($target_gff) && !is_link($target_gff)) {
        symlink($source_gff, $target_gff);
        $log[] = 'Symlinked annotations.gff3';
    }

    if (!file_exists($gz_file)) {
        // Sort before compressing — tabix requires sorted positions.
        // POSIX sort (original approach). Fallback to jbrowse sort-gff if needed.
        $rc = 1; $out = [];
        $sort_cmd = '(grep "^#" ' . escapeshellarg($target_gff)
                  . '; grep -v "^#" ' . escapeshellarg($target_gff)
                  . ' | sort -t"$(printf \'\\t\')" -k1,1 -k4,4n) | bgzip > ' . escapeshellarg($gz_file);
        exec('/bin/bash -c ' . escapeshellarg($sort_cmd), $out, $rc);
        if ($rc !== 0 || !file_exists($gz_file)) {
            $jb_candidates = [
                __DIR__ . '/../../tools/jbrowse-cli/jbrowse-run.sh',
                __DIR__ . '/../../tools/jbrowse-cli/bin/jbrowse',
                '/usr/local/bin/jbrowse', '/usr/bin/jbrowse',
            ];
            foreach ($jb_candidates as $p) {
                if ($p && is_executable($p)) {
                    $sort_cmd = escapeshellarg($p) . ' sort-gff ' . escapeshellarg($target_gff)
                              . ' | bgzip > ' . escapeshellarg($gz_file);
                    exec('/bin/bash -c ' . escapeshellarg($sort_cmd), $out, $rc);
                    if ($rc === 0) break;
                }
            }
        }
        if ($rc !== 0 || !file_exists($gz_file)) {
            $log[] = 'Warning: sort+bgzip failed — annotations will not be available: ' . implode(' ', $out);
        } else {
            $log[] = 'Sorted and compressed GFF (bgzip)';
        }
    } else {
        $log[] = 'Compressed GFF already exists';
    }

    if (file_exists($gz_file) && !file_exists($tbi_file)) {
        $cmd = 'tabix -p gff ' . escapeshellarg($gz_file) . ' 2>&1';
        exec($cmd, $out, $rc);
        if ($rc !== 0) {
            $log[] = 'Warning: tabix failed — annotation search will not be available: ' . implode(' ', $out);
        } else {
            $log[] = 'Indexed GFF (tabix)';
        }
    } else {
        $log[] = 'GFF index already exists';
    }
} else {
    $log[] = 'No genomic.gff found — skipping annotation setup';
}

// ── Step 5: Create assembly metadata JSON ────────────────────────────────────

$assemblies_dir = "$metadata_path/jbrowse2-configs/assemblies";
if (!is_dir($assemblies_dir)) {
    mkdir($assemblies_dir, 0755, true);
}

$assembly_name = "{$organism}_{$assembly}";
$display_name  = str_replace('_', ' ', $organism) . ' (' . $assembly . ')';
$uri_base      = '/' . $site . '/data/genomes/' . $organism . '/' . $assembly;

$assembly_data = [
    'name'               => $assembly_name,
    'displayName'        => $display_name,
    'organism'           => $organism,
    'assemblyId'         => $assembly,
    'aliases'            => [$assembly],
    'defaultAccessLevel' => 'PUBLIC',
    'sequence'           => [
        'type'    => 'ReferenceSequenceTrack',
        'trackId' => $assembly_name . '-ReferenceSequenceTrack',
        'adapter' => [
            'type'         => 'IndexedFastaAdapter',
            'fastaLocation' => [
                'uri'          => $uri_base . '/reference.fasta',
                'locationType' => 'UriLocation',
            ],
            'faiLocation'   => [
                'uri'          => $uri_base . '/reference.fasta.fai',
                'locationType' => 'UriLocation',
            ],
        ],
    ],
    'metadata' => [
        'createdAt'   => date('c'),
        'source'      => 'MOOP admin UI',
        'description' => "Assembly definition for $organism $assembly",
    ],
];

if (file_put_contents($assembly_json, json_encode($assembly_data, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'error' => "Failed to write assembly JSON to $assembly_json"]);
    exit;
}
$log[] = "Created assembly definition: {$organism}_{$assembly}.json";

echo json_encode([
    'success'       => true,
    'output'        => implode("\n", $log),
    'assembly_name' => $assembly_name,
]);
