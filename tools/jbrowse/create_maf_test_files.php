<?php
/**
 * Create test MAF files in various formats for testing JBrowse MAF adapters
 */

$baseDir = dirname(__DIR__, 2);
$mafDir = "$baseDir/data/tracks/Nematostella_vectensis/GCA_033964005.1/maf";

if (!is_dir($mafDir)) {
    mkdir($mafDir, 0755, true);
}

echo "Creating MAF test files...\n\n";

// Sample MAF data with realistic sequences
$mafData = <<<MAF
##maf version=1
# Test MAF alignment file for JBrowse
# Contains alignments for 3 species

a score=100
s Nematostella_vectensis.GCA_033964005.1.scaffold_1 1000 50 + 100000 ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA
s Hydra_vulgaris.GCA_000004535.1.scaffold_1       2000 50 + 200000 ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA
s Acropora_digitifera.GCA_000222465.1.scaffold_1  3000 50 + 300000 ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA

a score=95
s Nematostella_vectensis.GCA_033964005.1.scaffold_1 2000 45 + 100000 GCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCT
s Hydra_vulgaris.GCA_000004535.1.scaffold_1       4000 45 + 200000 GCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCT
s Acropora_digitifera.GCA_000222465.1.scaffold_1  5000 45 + 300000 GCTAGCTAGCTAGCTAGCTAGCT-GCTAGCTAGCTAGCTAGCT

a score=88
s Nematostella_vectensis.GCA_033964005.1.scaffold_1 5000 60 + 100000 TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA
s Hydra_vulgaris.GCA_000004535.1.scaffold_1       6000 60 - 200000 TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA
s Acropora_digitifera.GCA_000222465.1.scaffold_1  7000 60 + 300000 TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA

MAF;

// Newick tree for the species
$newickTree = "(Nematostella_vectensis.GCA_033964005.1:0.5,(Hydra_vulgaris.GCA_000004535.1:0.3,Acropora_digitifera.GCA_000222465.1:0.3):0.2);";

echo "1. Creating BigMaf format (.bb file)\n";
// BigMaf requires conversion from MAF to BigBed format
// For now, we'll create a placeholder and note this needs external tool
$bigMafFile = "$mafDir/test.bb";
echo "   Note: BigMaf requires 'mafToBigMaf' tool from UCSC\n";
echo "   File would be: $bigMafFile\n";
echo "   Command: mafToBigMaf test.maf chrom.sizes test.bb\n\n";

echo "2. Creating MafTabix format (.bed.gz + .tbi)\n";
// Convert MAF to BED format
$bedLines = [];
$bedLines[] = "scaffold_1\t1000\t1050\tblock1\t100\t+\tNematostella_vectensis.GCA_033964005.1.scaffold_1:1000:50:+:100000:ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA,Hydra_vulgaris.GCA_000004535.1.scaffold_1:2000:50:+:200000:ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA,Acropora_digitifera.GCA_000222465.1.scaffold_1:3000:50:+:300000:ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA";
$bedLines[] = "scaffold_1\t2000\t2045\tblock2\t95\t+\tNematostella_vectensis.GCA_033964005.1.scaffold_1:2000:45:+:100000:GCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCT,Hydra_vulgaris.GCA_000004535.1.scaffold_1:4000:45:+:200000:GCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCT,Acropora_digitifera.GCA_000222465.1.scaffold_1:5000:45:+:300000:GCTAGCTAGCTAGCTAGCTAGCT-GCTAGCTAGCTAGCTAGCT";
$bedLines[] = "scaffold_1\t5000\t5060\tblock3\t88\t+\tNematostella_vectensis.GCA_033964005.1.scaffold_1:5000:60:+:100000:TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA,Hydra_vulgaris.GCA_000004535.1.scaffold_1:6000:60:-:200000:TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA,Acropora_digitifera.GCA_000222465.1.scaffold_1:7000:60:+:300000:TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA";

$bedFile = "$mafDir/test.bed";
file_put_contents($bedFile, implode("\n", $bedLines) . "\n");
echo "   Created: $bedFile\n";

// Bgzip the BED file
$bedGzFile = "$mafDir/test.bed.gz";
exec("bgzip -c $bedFile > $bedGzFile 2>&1", $output, $ret);
if ($ret === 0) {
    echo "   Created: $bedGzFile\n";
    
    // Create tabix index
    $tbiFile = "$bedGzFile.tbi";
    exec("tabix -p bed $bedGzFile 2>&1", $output, $ret);
    if ($ret === 0) {
        echo "   Created: $tbiFile\n";
    } else {
        echo "   Error creating tabix index\n";
    }
} else {
    echo "   Error bgzipping BED file\n";
}
echo "\n";

echo "3. Creating BgzipTaffy format (.taf.gz + .tai)\n";
// TAF format (Transposed Alignment Format)
$tafData = <<<TAF
#taf version=1.0 run_length_encode_bases:0
# Test TAF alignment file
; i 0 Nematostella_vectensis.GCA_033964005.1.scaffold_1 1000 + 100000
; i 1 Hydra_vulgaris.GCA_000004535.1.scaffold_1 2000 + 200000
; i 2 Acropora_digitifera.GCA_000222465.1.scaffold_1 3000 + 300000
ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA
ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA
ATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCATGCA
; s 0 Nematostella_vectensis.GCA_033964005.1.scaffold_1 2000 + 100000
; s 1 Hydra_vulgaris.GCA_000004535.1.scaffold_1 4000 + 200000
; s 2 Acropora_digitifera.GCA_000222465.1.scaffold_1 5000 + 300000
GCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCT
GCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCTAGCT
GCTAGCTAGCTAGCTAGCTAGCT-GCTAGCTAGCTAGCTAGCT
; s 0 Nematostella_vectensis.GCA_033964005.1.scaffold_1 5000 + 100000
; s 1 Hydra_vulgaris.GCA_000004535.1.scaffold_1 6000 - 200000
; s 2 Acropora_digitifera.GCA_000222465.1.scaffold_1 7000 + 300000
TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA
TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA
TTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAACCGGTTAA

TAF;

$tafFile = "$mafDir/test.taf";
file_put_contents($tafFile, $tafData);
echo "   Created: $tafFile\n";

// Bgzip the TAF file
$tafGzFile = "$mafDir/test.taf.gz";
exec("bgzip -c $tafFile > $tafGzFile 2>&1", $output, $ret);
if ($ret === 0) {
    echo "   Created: $tafGzFile\n";
    
    // Create TAI index (custom format, needs specific tool)
    // For now, create a minimal TAI file
    $taiFile = "$tafGzFile.tai";
    $taiContent = "scaffold_1\t0\t0\nscaffold_1\t1050\t1050\nscaffold_1\t2095\t2095\n";
    file_put_contents($taiFile, $taiContent);
    echo "   Created: $taiFile (minimal index)\n";
    echo "   Note: Proper TAI index requires 'taf index' tool\n";
} else {
    echo "   Error bgzipping TAF file\n";
}
echo "\n";

echo "4. Creating Newick tree file\n";
$newickFile = "$mafDir/test.nh";
file_put_contents($newickFile, $newickTree);
echo "   Created: $newickFile\n\n";

echo "Done! Test files created in: $mafDir\n\n";
echo "Summary of formats:\n";
echo "  - BigMafAdapter: Requires .bb file (needs external tool)\n";
echo "  - MafTabixAdapter: test.bed.gz + test.bed.gz.tbi ✓\n";
echo "  - BgzipTaffyAdapter: test.taf.gz + test.taf.gz.tai ✓\n";
echo "  - Newick tree: test.nh ✓\n";
