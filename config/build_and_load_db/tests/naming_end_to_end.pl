#!/usr/bin/perl
use strict;
use warnings;
use File::Temp qw(tempdir);
use FindBin;
use IO::Compress::Gzip qw(gzip $GzipError);

# naming_end_to_end.pl [path/to/assign_gene_names_v2.pl]
#
# Hermetic end-to-end test of gene naming: builds a tiny made-up gene set in a temp dir -- nine
# genes, each made to hit exactly one naming rule -- runs assign_gene_names_v2.pl on it, and
# asserts the exact names, provenance (Gene Name Source) and closest genes. No site data needed,
# so CI runs it on every push. Exit 0 = all pass.
#
# Why it exists: on 2026-09-25 three rules broke SILENTLY -- the script ran, the output looked
# fine, and it was wrong (a lookup table read before it was filled). A test that only checks
# "it ran" cannot see that; these assertions pin what each rule must produce. Prove a change to
# a rule by making this fail first.

my $script = shift // "$FindBin::Bin/../analysis_parsers/assign_gene_names_v2.pl";
my ($passed, $failed) = (0, 0);
# A regex match passed straight to check() is wrapped in scalar(): in list context a FAILED match
# returns an empty list, which shifts the arguments and makes the description the (true) result.
sub check {
  my ($ok, $what, $detail) = @_;
  if ($ok) { $passed++; }
  else { $failed++; print "FAIL: $what\n" . (defined $detail ? "      got: $detail\n" : ''); }
}

my $dir = tempdir(CLEANUP => 1);
sub write_file {
  my ($path, $text) = @_;
  (my $parent = $path) =~ s{/[^/]+$}{};
  system('mkdir', '-p', $parent) == 0 or die "mkdir $parent\n";
  open my $fh, '>', $path or die "cant write $path: $!\n";
  print $fh $text;
  close $fh;
}

# ---- the gene set: one protein per gene, G1..G25 (G2/G3 are two copies of one human gene; G18-G22 five)
my %length = (T1 => 300, T2 => 250, T3 => 250, T4 => 400, T5 => 100, T6 => 100, T7 => 200, T8 => 150, T9 => 120,
              T10 => 300, T11 => 200, T12 => 150, T13 => 300, T14 => 500, T15 => 300, T16 => 200,
              T17 => 400, T18 => 400, T19 => 400, T20 => 400, T21 => 400, T22 => 400, T23 => 300, T24 => 200,
              T25 => 250);
write_file("$dir/isoforms.tsv", join('', map { my $n = substr($_, 1); "$_.1\tNone\tG$n\n" } sort keys %length));
write_file("$dir/protein.aa.fa", join('', map { ">$_.1\n" . ('M' x $length{$_}) . "\n" } sort keys %length));

# ---- human genes (HGNC)
write_file("$dir/hgnc/hgnc_complete_set.txt", join("\t", qw(hgnc_id symbol name gene_group gene_group_id ensembl_gene_id uniprot_ids prev_symbol)) . "\n" . join('', map { join("\t", @$_) . "\n" }
  ['HGNC:1', 'ALPHA', 'alpha synthase', '', '', 'ENSG01', '', ''],   # not "alpha protein": a name that only repeats the symbol is uninformative
  ['HGNC:2', 'BETA1', 'beta protein 1', 'Beta proteins', '10', 'ENSG02', '', ''],
  ['HGNC:3', 'BETA2', 'beta protein 2', 'Beta proteins', '10', 'ENSG03', '', ''],
  ['HGNC:4', 'GAMMA', 'gamma transferase', '', '', 'ENSG04', '', ''],
  ['HGNC:5', 'DELTA', 'delta kinase', '', '', 'ENSG05', '', ''],
  ['HGNC:6', 'EPS', 'epsilon protein', '', '', 'ENSG06', '', ''],
  ['HGNC:7', 'ANO1', 'anoctamin 1', 'Anoctamins', '20', 'ENSG07', '', ''],
  ['HGNC:8', 'ANO2', 'anoctamin 2', 'Anoctamins', '20', 'ENSG08', '', ''],
  ['HGNC:9', 'KAPPA1', 'kappa channel 1', 'Kappa channels', '21', 'ENSG09', '', ''],
  ['HGNC:10', 'KAPPA2', 'kappa channel 2', 'Kappa channels', '21', 'ENSG10', '', ''],
  ['HGNC:11', 'WDR90', 'WD repeat domain 90', '', '', 'ENSG11', '', ''],
  ['HGNC:12', 'CFAP52', 'cilia and flagella associated protein 52', '', '', 'ENSG12', '', ''],
  ['HGNC:13', 'HDA1', 'histone deacetylase 1', 'Class I HDACs', '30', 'ENSG13', '', ''],
  ['HGNC:14', 'HDA2', 'histone deacetylase 2', 'Class I HDACs', '30', 'ENSG14', '', ''],
  ['HGNC:15', 'HARB1', 'harbinger transposase derived 1', '', '', 'ENSG15', '', ''],
  ['HGNC:16', 'CENPQ', 'centromere protein Q', '', '', 'ENSG16', '', ''],
));
# ---- Swiss-Prot cross-references: the PANTHER families of human genes (orthology support)
my $xrefs = join("\t", qw(accession taxid gene_name hgnc_ids ensembl_genes ensembl_proteins panther_ids secondary_accessions)) . "\n"
  . "P00001\t9606\tALPHA\tHGNC:1\tENSG01\tENSP01\tPTHR00001:SF3\t\n"
  . "P00004\t9606\tGAMMA\tHGNC:4\tENSG04\tENSP04\tPTHR00004\t\n";
system('mkdir', '-p', "$dir/uniprot") == 0 or die;
gzip(\$xrefs => "$dir/uniprot/sprot_xrefs.tsv.gz") or die $GzipError;

# ---- OMA: pairwise orthologs to HUMAN (1:1, many:1, 1:many) and to NEMVE
my $human = sub { my ($n) = @_; my %name = (1 => 'alpha synthase', 2 => 'beta protein 1', 3 => 'beta protein 2', 4 => 'gamma transferase',
                                            13 => 'histone deacetylase 1', 14 => 'histone deacetylase 2',
                                            15 => 'harbinger transposase derived 1', 16 => 'centromere protein Q', 6 => 'epsilon protein');
  return "HUMAN0000$n | ENSP0$n | ENSG0$n | $name{$n} [Source:HGNC Symbol;Acc:HGNC:$n]" };
write_file("$dir/oma/Output/PairwiseOrthologs/TEST-HUMAN.txt", join('', map { join("\t", @$_) . "\n" }
  [1, 1, 'T1.1', $human->(1), '1:1'],
  [2, 4, 'T2.1', $human->(4), 'many:1'],
  [3, 4, 'T3.1', $human->(4), 'many:1'],
  [4, 2, 'T4.1', $human->(2), '1:many'],
  [4, 3, 'T4.1', $human->(3), '1:many'],
  [13, 13, 'T13.1', $human->(13), '1:1'],
  # T18-T22: five copies OMA pairs many:1 with HARB1, each a Harbinger transposase -> a TE family
  (map { [$_, 15, "T$_.1", $human->(15), 'many:1'] } 18 .. 22),
  # T23: a 1:1 ortholog that carries a transposase domain keeps its OMA name (flagged te)
  [23, 16, 'T23.1', $human->(16), '1:1'],
  # T25: an OMA 1:1 pair to EPS that nothing else supports (no similarity, no PANTHER family) -> set aside
  [25, 6, 'T25.1', $human->(6), '1:1'],
));
write_file("$dir/oma/Output/PairwiseOrthologs/TEST-NEMVE.txt", join('', map { join("\t", @$_) . "\n" }
  [1, 11, 'T1.1', 'NEMVE00011 | XP_000011.1 | LOC11 | anemone alpha', '1:1'],
  [4, 12, 'T4.1', 'NEMVE00012 | XP_000012.1 | LOC12 | anemone beta A', '1:many'],
  [4, 13, 'T4.1', 'NEMVE00013 | XP_000013.1 | LOC13 | anemone beta B', '1:many'],
));

# ---- OMA HOGs (fixed species tree): T13 is paired 1:1 with HDA1 above, but its HOG makes it
# co-ortholog of HDA1 and HDA2 (a duplication in the human lineage) -> the family
write_file("$dir/oma/parameters.drw", "SpeciesTree := '(TEST,HUMAN);';\n");
write_file("$dir/oma/Output/HierarchicalGroups.orthoxml", join("\n",
  '<orthoXML>', '<species name="TEST">', '<database>', '<genes>', '<gene id="1" protId="T13.1" />', '</genes>', '</database>', '</species>',
  '<species name="HUMAN">', '<database>', '<genes>',
  '<gene id="2" protId="' . $human->(13) . '" />', '<gene id="3" protId="' . $human->(14) . '" />',
  '</genes>', '</database>', '</species>', '<groups>',
  '<orthologGroup id="1">', '<geneRef id="1" />', '<paralogGroup>', '<geneRef id="2" />', '<geneRef id="3" />', '</paralogGroup>', '</orthologGroup>',
  '</groups>', '</orthoXML>', ''));

# ---- MMseqs2 reciprocal best hits to Ensembl human, and that proteome (--ref-db)
# T5: full length (95% / 95%) -> may name a gene; T6: 90% / 60% -> closest-human evidence only
write_file("$dir/mmseqs/ENS_homo_sapiens/db_version.txt", "ENS_homo_sapiens\trelease-113\n");
write_file("$dir/mmseqs/ENS_homo_sapiens/rbh_mmseq_results.tsv", "query\ttarget\tpident\talnlen\tmismatch\tgapopen\tqstart\tqend\ttstart\ttend\tevalue\tbits\n"
  . "T5.1\tENSP05.1\t0.62\t95\t30\t0\t1\t95\t1\t95\t1e-50\t300\n"
  . "T6.1\tENSP06.1\t0.40\t90\t50\t2\t1\t90\t1\t120\t1e-20\t120\n"
  . "T14.1\tENSP08.1\t0.50\t480\t200\t2\t10\t490\t5\t495\t1e-150\t480\n");
my $pep = '';
foreach my $row (['ENSP05.1', 'ENSG05.1', 'DELTA', 'delta kinase', 5, 100], ['ENSP06.1', 'ENSG06.1', 'EPS', 'epsilon protein', 6, 200],
                 ['ENSP08.1', 'ENSG08.1', 'ANO2', 'anoctamin 2', 8, 500]) {
  my ($protein, $gene, $symbol, $name, $n, $len) = @$row;
  $pep .= ">$protein pep chromosome:GRCh38:1:1:100:1 gene:$gene transcript:ENST0$n gene_biotype:protein_coding "
        . "transcript_biotype:protein_coding gene_symbol:$symbol description:$name [Source:HGNC Symbol;Acc:HGNC:$n]\n" . ('M' x $len) . "\n";
}
system('mkdir', '-p', "$dir/refdb/ENS_homo_sapiens/current") == 0 or die;
gzip(\$pep => "$dir/refdb/ENS_homo_sapiens/current/Homo_sapiens.test.pep.all.fa.gz") or die $GzipError;

# ---- DIAMOND against Ensembl human, the 17 naming columns (qcovhsp, scovhsp last)
#   T1  -> ALPHA, its best human hit: the OMA name is supported (sim+); likewise T2/T3 -> GAMMA,
#          T4 -> BETA1, T18-T22 -> HARB1, T23 -> CENPQ (their OMA pairs must be supported to be used)
#   T11 -> DELTA over part of its length: a domain name, but the gene has a partial homolog (sim~)
#   T13 -> HDA1: supports the HOG family
#   T14 -> ANO1 500 bits, ANO2 490 (within 5%): a paralog tie; MMseqs2 above has the RBH to ANO2 -> ANO2-like
#   T15 -> KAPPA1 300, KAPPA2 295: a tie, no RBH -> their HGNC group
#   T16 -> WDR90 562 bits over 30% of WDR90, CFAP52 119 full-length: the best human gene is not
#          full-length, so no -like name at all (never "CFAP52-like" from the weaker hit)
my $title = sub { my ($n, $symbol, $name) = @_;
  return "ENSP$n.1 pep chromosome:GRCh38:1:1:100:1 gene:ENSG$n.1 transcript:ENST$n gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:$symbol description:$name [Source:HGNC Symbol;Acc:HGNC:" . ($n + 0) . "]" };
my $dhit = sub { my ($q, $n, $symbol, $name, $evalue, $bits, $qlen, $slen, $qcov, $scov) = @_;
  return join("\t", "$q.1", "ENSP$n.1", $title->($n, $symbol, $name), $evalue, 50, 100, 40, 1, 1, 100, 1, 100, $bits, $qlen, $slen, $qcov, $scov) . "\n" };
write_file("$dir/diamond/ENS_homo_sapiens/diamond_results.tsv",
  join("\t", qw(qseqid sseqid stitle evalue pident length mismatch gapopen qstart qend sstart send bitscore qlen slen qcovhsp scovhsp)) . "\n"
  . $dhit->('T1', '01', 'ALPHA', 'alpha synthase', '1e-100', 400, 300, 300, 95, 95)
  . $dhit->('T2', '04', 'GAMMA', 'gamma transferase', '1e-60', 250, 250, 250, 90, 90)
  . $dhit->('T3', '04', 'GAMMA', 'gamma transferase', '1e-60', 250, 250, 250, 90, 90)
  . $dhit->('T4', '02', 'BETA1', 'beta protein 1', '1e-60', 250, 400, 400, 90, 90)
  . join('', map { $dhit->("T$_", '15', 'HARB1', 'harbinger transposase derived 1', '1e-40', 150, 400, 350, 60, 70) } 18 .. 22)
  . $dhit->('T23', '16', 'CENPQ', 'centromere protein Q', '1e-50', 200, 300, 300, 90, 90)
  . $dhit->('T11', '05', 'DELTA', 'delta kinase', '1e-12', 60, 200, 100, 30, 60)
  . $dhit->('T13', '13', 'HDA1', 'histone deacetylase 1', '1e-120', 450, 300, 300, 95, 95)
  . $dhit->('T14', '07', 'ANO1', 'anoctamin 1', '1e-160', 500, 500, 500, 96, 96)
  . $dhit->('T14', '08', 'ANO2', 'anoctamin 2', '1e-155', 490, 500, 500, 96, 96)
  . $dhit->('T15', '09', 'KAPPA1', 'kappa channel 1', '1e-90', 300, 300, 300, 95, 95)
  . $dhit->('T15', '10', 'KAPPA2', 'kappa channel 2', '1e-88', 295, 300, 300, 95, 95)
  . $dhit->('T16', '11', 'WDR90', 'WD repeat domain 90', '1e-160', 562, 200, 700, 90, 30)
  . $dhit->('T16', '12', 'CFAP52', 'cilia and flagella associated protein 52', '1e-30', 119, 200, 210, 85, 85));

# ---- InterProScan TSV (PANTHER families and InterPro domains), InterPro entry list, PANTHER model lengths
# PANTHER names a gene only when its match covers >= 80% of the family model:
#   T6: 95 of a 100-residue model, not in InterPro -> PANTHER's own name
#   T7: PANTHER's "-" (no description) -> skipped; T7's domains: the LOWER E-value one (SMART,
#       IPR000002) must name it, not the lower accession
#   T9: a locus-id family ("PROTEIN CBG12345") -> None
#   T10: two regions (1-100, 150-240, overlapping 90-110) = 240 of a 250 model, integrated in InterPro
#        Family IPR000010 -> InterPro's curated name, not PANTHER's
#   T11: 60 of a 200 model (one shared domain) -> NOT the family; falls to its InterPro domain
#   T12: a fly clone-id family ("LD39211P") -> None
my $row = sub { my ($id, $len, $analysis, $sig, $desc, $start, $end, $score, $ipr, $ipr_desc) = @_;
  return join("\t", "$id.1", 'md5', $len, $analysis, $sig, $desc, $start, $end, $score, 'T', '04-08-2026', $ipr, $ipr_desc, '-', '-') . "\n" };
write_file("$dir/iprscan.tsv", join('',
  $row->('T6',  100, 'PANTHER', 'PTHR00006', 'WIDGET PROTEIN',   3,  97,  '1.0E-30', '-', '-'),
  $row->('T7',  200, 'PANTHER', 'PTHR00007', '-',                1,  190, '1.0E-25', '-', '-'),
  $row->('T7',  200, 'Pfam',    'PF00001',   'kinase',           1,  100, '1.0E-5',  'IPR000001', 'Kinase domain'),
  $row->('T7',  200, 'SMART',   'SM00002',   'RING',             120, 180, '1.0E-20', 'IPR000002', 'Zinc finger, RING-type'),
  $row->('T8',  150, 'Pfam',    'PF00003',   'duf',              1,  50,  '1.0E-9',  'IPR000003', 'Domain of unknown function DUF1'),
  $row->('T9',  120, 'PANTHER', 'PTHR00009', 'PROTEIN CBG12345', 1,  115, '1.0E-20', '-', '-'),
  $row->('T10', 300, 'PANTHER', 'PTHR00010', 'GADGET PROTEIN 3-RELATED', 1, 110, '1.0E-60', 'IPR000010', 'Gadget family'),
  $row->('T10', 300, 'PANTHER', 'PTHR00010', 'GADGET PROTEIN 3-RELATED', 90, 240, '1.0E-60', 'IPR000010', 'Gadget family'),
  $row->('T11', 200, 'PANTHER', 'PTHR00011', 'HISTONE-LYSINE N-METHYLTRANSFERASE KMT5A', 20, 79, '1.0E-40', '-', '-'),
  $row->('T11', 200, 'Pfam',    'PF00856',   'SET',              20, 79,  '1.0E-15', 'IPR001214', 'SET domain'),
  $row->('T12', 150, 'PANTHER', 'PTHR00012', 'LD39211P',         1,  150, '1.0E-50', '-', '-'),
  # a PROSITE pattern never names a gene (T12 stays None); a Pfam match at E = 0.08 does (T9):
  # InterProScan reports only matches past the member database's own threshold, and no E floor is added
  $row->('T12', 150, 'ProSitePatterns', 'PS00028', 'ZINC_FINGER_C2H2_1', 10, 32, '-', 'IPR013087', 'Zinc finger C2H2-type'),
  $row->('T9',  120, 'Pfam',    'PF00084',   'Sushi',            5,  60,  '8.0E-2',  'IPR000436', 'Sushi/SCR/CCP domain'),
  # orthology support: T1 is in its human gene's PANTHER family (PTHR00001, any coverage);
  # T2/T3 are in a different family from GAMMA's (PTHR00004)
  $row->('T1',  300, 'PANTHER', 'PTHR00001', 'ALPHA SYNTHASE',   1,  60,  '1.0E-20', '-', '-'),
  $row->('T2',  250, 'PANTHER', 'PTHR00099', 'OTHER FAMILY',     1,  60,  '1.0E-20', '-', '-'),
  $row->('T3',  250, 'PANTHER', 'PTHR00099', 'OTHER FAMILY',     1,  60,  '1.0E-20', '-', '-'),
  # transposases (Pfam DDE_Tnp_4, the PIF/Harbinger transposase): T17 has no OMA ortholog
  (map { $row->("T$_", 400, 'Pfam', 'PF13359', 'DDE superfamily endonuclease', 50, 250, '1.0E-30', 'IPR027806', 'Harbinger transposase-derived nuclease domain') } 17 .. 23),
  # T24: a "KRAB AND ZINC FINGER" family match (95% of its model) that is 44% C2H2 repeats -> named for the repeat
  $row->('T24', 200, 'PANTHER', 'PTHR00024', 'KRAB AND ZINC FINGER DOMAIN-CONTAINING', 1, 190, '1.0E-40', '-', '-'),
  # T25: its unsupported OMA pair is set aside; its kinase domain names it
  $row->('T25', 250, 'Pfam',    'PF00001',   'kinase',           10, 200, '1.0E-30', 'IPR000001', 'Kinase domain'),
  (map { $row->('T24', 200, 'SMART', 'SM00355', 'ZnF_C2H2', $_, $_ + 20, '1.0E-3', 'IPR013087', 'Zinc finger C2H2-type') } 20, 60, 100, 140),
));
write_file("$dir/entry.list", "ENTRY_AC\tENTRY_TYPE\tENTRY_NAME\nIPR000001\tDomain\tKinase domain\n"
  . "IPR000002\tDomain\tZinc finger, RING-type\nIPR000003\tDomain\tDomain of unknown function DUF1\n"
  . "IPR000010\tFamily\tGadget family\nIPR001214\tDomain\tSET domain\n"
  . "IPR013087\tDomain\tZinc finger C2H2-type\nIPR000436\tDomain\tSushi/SCR/CCP domain\n"
  . "IPR027806\tDomain\tHarbinger transposase-derived nuclease domain\n");
write_file("$dir/hmm_lengths.tsv", join('', map { "$_->[0]\t$_->[1]\n" }
  ['PTHR00006', 100], ['PTHR00007', 200], ['PTHR00009', 120], ['PTHR00010', 250], ['PTHR00011', 200], ['PTHR00012', 150],
  ['PTHR00001', 300], ['PTHR00099', 250], ['PTHR00024', 200]));

# ---- run it (twice, with different hash seeds: the output must not depend on hash order)
my @arguments = ('--isoforms', "$dir/isoforms.tsv", '--protein-fasta', "$dir/protein.aa.fa", '--hgnc-dir', "$dir/hgnc",
  '--oma-dir', "$dir/oma", '--oma-code', 'TEST', '--mmseqs-dir', "$dir/mmseqs", '--ref-db', "$dir/refdb",
  '--diamond-dir', "$dir/diamond", '--uniprot-dir', "$dir/uniprot",
  '--interproscan', "$dir/iprscan.tsv", '--interpro-entries', "$dir/entry.list", '--panther-hmm-lengths', "$dir/hmm_lengths.tsv",
  '--closest-species', 'species=Nematostella vectensis|tag=Nvec|label=sea anemone|oma_code=NEMVE|hits=|use_for_names=0|same_species=0');
foreach my $seed (1, 2) {
  my $out = "$dir/out$seed";
  system('mkdir', '-p', $out) == 0 or die;
  local $ENV{PERL_HASH_SEED} = $seed;
  my $status = system("\Q$^X\E \Q$script\E " . join(' ', map { "\Q$_\E" } @arguments)
                      . " --out-names \Q$out/geneNames.tsv\E --out-dir \Q$out\E > \Q$out.log\E 2>&1");
  check($status == 0, "assign_gene_names_v2.pl runs (seed $seed)", `tail -3 \Q$out.log\E`);
}
my $out = "$dir/out1";

# ---- read the outputs
my %name;
foreach my $row (read_tsv("$out/geneNames.tsv")) { $name{$row->[2]} //= $row->[3]; }
my %source;   # gene -> [ accession, description, step, file kind ]
foreach my $file (glob "$out/gene_name_source.*.moop.tsv") {
  my ($kind) = $file =~ /gene_name_source\.(\w+)\.moop/;
  foreach my $row (read_tsv($file)) { $source{$row->[0]} = [ @$row[1 .. 3], $kind ] if $row->[0] =~ /^G\d+$/; }
}
my %closest_human = map { $_->[1] => $_ } read_tsv("$out/closest_human.tsv");
my %closest_nvec  = map { $_->[1] => $_ } read_tsv("$out/closest_nvec.tsv");

# ---- names: one rule per gene, each ending in its evidence tag
my %expect = (
  G1  => ['ALPHA: alpha synthase [ISO|1to1|sim+|pthr=]', 'OMA 1:1, backed by the best human hit and the same PANTHER family'],
  G2  => ['GAMMA: gamma transferase [ISO|2to1|sim+|pthrX]', 'OMA many:1: every copy the plain name, copies in the tag; similar, but a conflicting family'],
  G3  => ['GAMMA: gamma transferase [ISO|2to1|sim+|pthrX]', 'the other copy, same name'],
  G4  => ['Beta proteins family member [ISO|fam|sim+]', 'OMA 1:many -> the HGNC group, no symbol, no member picked'],
  G5  => ['DELTA-like: delta kinase-like [ISS|rbh]', 'full-length reciprocal hit -> "-like", never plain'],
  G6  => ['WIDGET PROTEIN family member [ISM|pthr]', 'partial hit cannot name; PANTHER family (95% of its model) does'],
  G7  => ['Zinc finger RING-type domain-containing protein [ISM|ipr]', 'PANTHER "-" skipped; the LOWER E-value InterPro domain names it, comma dropped'],
  G8  => ['None', 'only a "unknown function" domain -> None'],
  G9  => ['Sushi/SCR/CCP domain-containing protein [ISM|ipr]', 'a locus-id PANTHER family is skipped; a Pfam match (E=0.08, past Pfam\'s own threshold) names it'],
  G10 => ['Gadget family member [ISM|pthr]', 'family integrated in InterPro -> InterPro\'s name; overlapping regions merged (240/250)'],
  G11 => ['SET domain-containing protein [ISM|ipr|sim~]', 'family match covers 30% of the model -> its domain names it; a partial human homolog is flagged'],
  G12 => ['None', 'a fly clone-id family ("LD39211P") and a PROSITE pattern -> None'],
  G13 => ['Class I HDACs family member [ISO|fam|sim+|hog]', 'OMA pairs it 1:1 with HDA1, its HOG with HDA1 and HDA2 -> the family'],
  G14 => ['ANO2-like: anoctamin 2-like [ISS|rbh|tie-rbh]', 'paralog tie (ANO1 500, ANO2 490 bits) decided by the one reciprocal best hit'],
  G15 => ['Kappa channels family member [ISS|bh|tie-grp]', 'paralog tie without a reciprocal hit -> the shared HGNC group'],
  G16 => ['None', 'best human gene (WDR90) not full-length -> no -like from the weaker CFAP52 hit'],
  G17 => ['PIF/Harbinger transposase domain-containing protein [ISM|te]', 'a transposase, no ortholog -> named for its TE class'],
  (map { ("G$_" => ['PIF/Harbinger transposase domain-containing protein [ISM|te]', 'OMA many:1 x5 to a transposon-derived human gene, each a transposase -> TE, not the human name']) } 18 .. 22),
  G23 => ['CENPQ: centromere protein Q [ISO|1to1|sim+|te]', 'a 1:1 OMA ortholog with a transposase domain keeps its name, flagged te'],
  G25 => ['Kinase domain-containing protein [ISM|ipr|omaX]', 'an OMA pair nothing supports is set aside (omaX); the next evidence names it'],
  G24 => ['Zinc finger C2H2-type domain-containing protein [ISM|rpt]', 'a PANTHER family match that is 44% C2H2 repeats -> named for the repeat, not "KRAB"'],
);
foreach my $gene (sort { substr($a, 1) <=> substr($b, 1) } keys %expect) {
  check(($name{$gene} // '') eq $expect{$gene}[0], "$gene: $expect{$gene}[1]", $name{$gene});
}
check(!grep({ / \(\d+ of \d+\)$|^- family member$|  / } values %name), 'no "(k of n)", "- family member" or double spaces anywhere');
check(!grep({ my $symbol = /^([^:]*):/ ? $1 : ''; $symbol =~ /[\[|]/ } values %name), 'no colon inside a tag (the symbol is what precedes the first colon)');

# ---- provenance: why each name, as loaded into MOOP (accession, description, step, source)
my $p = sub { my ($gene) = @_; return $source{$gene} ? join(' | ', @{$source{$gene}}) : '(no row)'; };
check(($source{G1}[0] // '') eq 'HGNC:1' && ($source{G1}[2] // '') eq '3'
      && ($source{G1}[1] // '') eq 'Ortholog of human ALPHA (OMA, 1:1); ALPHA is its best human similarity hit; same PANTHER family (PTHR00001)',
      'G1 provenance: HGNC:1, OMA 1:1, its support, step 3', $p->('G1'));
check(($source{G2}[1] // '') eq 'Ortholog of human GAMMA (OMA, many:1), one of 2 copies in this genome; GAMMA is its best human similarity hit; '
      . 'a different PANTHER family (PTHR00099; human: PTHR00004)',
      'G2 provenance: copy count, and each kind of support', $p->('G2'));
check(scalar(($source{G25}[1] // '') =~ /; OMA pairs it with human EPS \(1:1\), but no similarity hit or PANTHER family supports that pair, so it does not name the gene$/),
      'G25 provenance: the set-aside OMA pair is stated', $p->('G25'));
check(($source{G4}[3] // '') eq 'hgnc_group' && ($source{G4}[0] // '') eq '10'
      && ($source{G4}[1] // '') =~ /^Co-ortholog of 2 human genes in the HGNC group "Beta proteins" \(OMA, 1:many\); no single ortholog; one of these genes is its best human similarity hit$/,
      'G4 provenance: HGNC gene group id 10, linked as a group', $p->('G4'));
check(($source{G5}[1] // '') eq 'Similar to human DELTA along its length: reciprocal best hit, 95% of this protein and 95% of DELTA aligned, E=1e-50 (MMseqs2)'
      && ($source{G5}[2] // '') eq '4', 'G5 provenance: coverage and E-value, step 4', $p->('G5'));
check(($source{G6}[3] // '') eq 'panther' && ($source{G6}[0] // '') eq 'PTHR00006' && ($source{G6}[2] // '') eq '5'
      && ($source{G6}[1] // '') eq 'Member of PANTHER family PTHR00006 ("WIDGET PROTEIN", not in InterPro): 95% of the family model aligned, E=1e-30 (InterProScan)',
      'G6 provenance: PANTHER family, model coverage, step 5', $p->('G6'));
check(($source{G10}[1] // '') eq 'Member of PANTHER family PTHR00010 (InterPro IPR000010 "Gadget family"): 96% of the family model aligned, E=1e-60 (InterProScan)',
      'G10 provenance: InterPro name, merged model coverage', $p->('G10'));
check(($source{G7}[0] // '') eq 'IPR000002' && ($source{G7}[1] // '') =~ /\(SMART SM00002, E=1e-20\); no ortholog, full-length homolog or family to name it by$/ && ($source{G7}[2] // '') eq '6',
      'G7 provenance: the chosen domain with its E-value, step 6', $p->('G7'));
check(scalar(($source{G11}[1] // '') =~ /; similar to human DELTA over part of its length only \(30% of this protein, 60% of DELTA, E=1e-12\)$/),
      'G11 provenance: the partial human homolog is stated, not denied', $p->('G11'));
check(scalar(($source{G13}[1] // '') =~ /; one of these genes is its best human similarity hit/), 'G13 provenance: support of a family is said of "one of these genes"', $p->('G13'));
check(($source{G13}[3] // '') eq 'hgnc_group' && ($source{G13}[0] // '') eq '30'
      && ($source{G13}[1] // '') =~ /^OMA pairs it 1:1 with human HDA1, but OMA's HOG makes it co-ortholog of 2 human genes in the HGNC group "Class I HDACs"/,
      'G13 provenance: pairwise 1:1 vs HOG, named for the group', $p->('G13'));
check(($source{G14}[0] // '') eq 'HGNC:8' && ($source{G14}[1] // '') =~ /ANO1, ANO2 score within 5% of each other, and only ANO2 is a reciprocal best hit$/,
      'G14 provenance: the tie and what decided it', $p->('G14'));
check(($source{G15}[0] // '') eq '21' && ($source{G15}[2] // '') eq '4', 'G15 provenance: HGNC group 21, step 4', $p->('G15'));
check(!exists $source{G8} && !exists $source{G12} && !exists $source{G16}, 'unnamed genes have no provenance row');
check(($source{G18}[3] // '') eq 'pfam' && ($source{G18}[0] // '') eq 'PF13359'
      && ($source{G18}[1] // '') =~ /; OMA pairs it with human HARB1 \(many:1\) together with 4 other copies in this genome -- a transposon family, not one ortholog$/,
      'G18 provenance: Pfam link, and why the OMA name was not used', $p->('G18'));
check(($source{G24}[0] // '') eq 'PTHR00024' && ($source{G24}[1] // '') =~ /match is 44% repeat units \(Zinc finger C2H2-type\)/,
      'G24 provenance: the repeat fraction and the family it replaced', $p->('G24'));

# ---- closest genes: one entry per gene; families as families
check(($closest_human{G1}[2] // '') eq 'HGNC:1', 'closest human G1: HGNC:1');
check(($closest_human{G25}[2] // 'x') eq '' && ($closest_human{G25}[5] // 'x') eq '', 'closest human G25: the set-aside OMA pair is not reported', join(' | ', @{$closest_human{G25} // []}));
check(($closest_human{G4}[2] // 'x') eq '' && ($closest_human{G4}[3] // '') eq 'Beta proteins family' && ($closest_human{G4}[5] // '') =~ /family of 2$/,
      'closest human G4: the family, no gene id', join(' | ', @{$closest_human{G4} // []}));
check(($closest_human{G6}[2] // '') eq 'HGNC:6' && ($closest_human{G6}[5] // '') =~ /reciprocal best hit/,
      'closest human G6: a partial RBH still counts as evidence (normal filter)', join(' | ', @{$closest_human{G6} // []}));
check(($closest_nvec{G1}[2] // '') eq 'XP_000011.1' && ($closest_nvec{G1}[5] // '') eq 'OMA ortholog (1:1)',
      'closest Nvec G1: from the OMA pairs (the check that caught the empty-ranks bug)', join(' | ', @{$closest_nvec{G1} // []}));
check(($closest_nvec{G4}[2] // 'x') eq '' && ($closest_nvec{G4}[5] // '') =~ /family of 2$/, 'closest Nvec G4: a family');
check(($closest_human{G13}[3] // '') eq 'Class I HDACs family' && ($closest_human{G13}[5] // '') eq 'OMA ortholog (1:1) of HDA1; OMA HOG co-ortholog of 2 human genes, family of 2',
      'closest human G13: the HOG family, with the pairwise gene named in the evidence', join(' | ', @{$closest_human{G13} // []}));

# ---- moop files load cleanly: 4 columns, the headers MOOP requires, one type per family
foreach my $file (glob("$out/*.moop.tsv")) {
  open my $fh, '<', $file or die;
  my ($type, $bad) = ('', 0);
  while (my $line = <$fh>) {
    $type = $1 if $line =~ /^## Annotation Type: (.+)$/;
    next if $line =~ /^#/;
    chomp $line;
    $bad++ if (split /\t/, $line, -1) != 4;
  }
  close $fh;
  (my $short = $file) =~ s{.*/}{};
  check($bad == 0 && ($type eq 'Closest Gene' || $type eq 'Gene Name Source'), "$short: 4 columns, type $type", "$bad bad rows");
}

# ---- same output whatever the hash seed
my $differs = 0;
foreach my $file (map { s{.*/}{}r } glob "$dir/out1/*") {
  next if $file =~ /\.log$/;
  $differs++ if `grep -v 'Creation Date' \Q$dir/out1/$file\E` ne `grep -v 'Creation Date' \Q$dir/out2/$file\E`;
}
check($differs == 0, 'identical output under two hash seeds', "$differs file(s) differ");

# ---- informative-name rules applied to single names (native RefSeq names are kept unless these say no)
{
  use lib "$FindBin::Bin/../analysis_parsers";
  require GeneNamingV2;
  my $informative = sub { GeneNamingV2::is_informative_hit('', $_[0], 'x') ? 1 : 0 };
  check($informative->('UPF0565 protein C2orf69 homolog') == 1, 'a UPF name that names a gene stays informative (RefSeq native)');
  check($informative->('UPF0764 PROTEIN C16ORF89') == 1, 'a UPF PANTHER name that names a gene stays informative');
  check($informative->('DUF4605 DOMAIN-CONTAINING PROTEIN') == 0, 'a DUF-only name is uninformative');
  check($informative->('UNCHARACTERIZED DUF1308') == 0, '"UNCHARACTERIZED DUF1308" is uninformative');
  check($informative->('UPF0462 PROTEIN') == 0, 'a UPF-only name is uninformative');
  check($informative->('regulator of nonsense transcripts 1') == 1, 'UPF1 (a real gene name) stays informative');
}

print $failed ? "\n$failed FAILED, $passed passed\n" : "all $passed checks passed\n";
exit($failed ? 1 : 0);

sub read_tsv {
  my ($file) = @_;
  open my $fh, '<', $file or do { check(0, "output exists: $file"); return (); };
  my @rows;
  while (my $line = <$fh>) {
    next if $line =~ /^#/ or $line =~ /^ID\t/;
    chomp $line;
    push @rows, [ split /\t/, $line, -1 ];
  }
  close $fh;
  return @rows;
}
