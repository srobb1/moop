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

# ---- the gene set: one protein per gene, G1..G9 (G2/G3 are two copies of one human gene)
my %length = (T1 => 300, T2 => 250, T3 => 250, T4 => 400, T5 => 100, T6 => 100, T7 => 200, T8 => 150, T9 => 120);
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
));

# ---- OMA: pairwise orthologs to HUMAN (1:1, many:1, 1:many) and to NEMVE
my $human = sub { my ($n) = @_; my %name = (1 => 'alpha synthase', 2 => 'beta protein 1', 3 => 'beta protein 2', 4 => 'gamma transferase');
  return "HUMAN0000$n | ENSP0$n | ENSG0$n | $name{$n} [Source:HGNC Symbol;Acc:HGNC:$n]" };
write_file("$dir/oma/Output/PairwiseOrthologs/TEST-HUMAN.txt", join('', map { join("\t", @$_) . "\n" }
  [1, 1, 'T1.1', $human->(1), '1:1'],
  [2, 4, 'T2.1', $human->(4), 'many:1'],
  [3, 4, 'T3.1', $human->(4), 'many:1'],
  [4, 2, 'T4.1', $human->(2), '1:many'],
  [4, 3, 'T4.1', $human->(3), '1:many'],
));
write_file("$dir/oma/Output/PairwiseOrthologs/TEST-NEMVE.txt", join('', map { join("\t", @$_) . "\n" }
  [1, 11, 'T1.1', 'NEMVE00011 | XP_000011.1 | LOC11 | anemone alpha', '1:1'],
  [4, 12, 'T4.1', 'NEMVE00012 | XP_000012.1 | LOC12 | anemone beta A', '1:many'],
  [4, 13, 'T4.1', 'NEMVE00013 | XP_000013.1 | LOC13 | anemone beta B', '1:many'],
));

# ---- MMseqs2 reciprocal best hits to Ensembl human, and that proteome (--ref-db)
# T5: full length (95% / 95%) -> may name a gene; T6: 90% / 60% -> closest-human evidence only
write_file("$dir/mmseqs/ENS_homo_sapiens/db_version.txt", "ENS_homo_sapiens\trelease-113\n");
write_file("$dir/mmseqs/ENS_homo_sapiens/rbh_mmseq_results.tsv", "query\ttarget\tpident\talnlen\tmismatch\tgapopen\tqstart\tqend\ttstart\ttend\tevalue\tbits\n"
  . "T5.1\tENSP05.1\t0.62\t95\t30\t0\t1\t95\t1\t95\t1e-50\t300\n"
  . "T6.1\tENSP06.1\t0.40\t90\t50\t2\t1\t90\t1\t120\t1e-20\t120\n");
my $pep = '';
foreach my $row (['ENSP05.1', 'ENSG05.1', 'DELTA', 'delta kinase', 5, 100], ['ENSP06.1', 'ENSG06.1', 'EPS', 'epsilon protein', 6, 200]) {
  my ($protein, $gene, $symbol, $name, $n, $len) = @$row;
  $pep .= ">$protein pep chromosome:GRCh38:1:1:100:1 gene:$gene transcript:ENST0$n gene_biotype:protein_coding "
        . "transcript_biotype:protein_coding gene_symbol:$symbol description:$name [Source:HGNC Symbol;Acc:HGNC:$n]\n" . ('M' x $len) . "\n";
}
system('mkdir', '-p', "$dir/refdb/ENS_homo_sapiens/current") == 0 or die;
gzip(\$pep => "$dir/refdb/ENS_homo_sapiens/current/Homo_sapiens.test.pep.all.fa.gz") or die $GzipError;

# ---- PANTHER (moop TSV) and InterProScan + InterPro entry list
# T6: an informative family; T7: PANTHER's "-" (no description); T9: a locus-id family
write_file("$dir/PANTHER.iprscan.moop.tsv", "## Annotation Source: InterProScan (PANTHER)\n## Gene\tPANTHER\tDescription\tScore\n"
  . "T6.1\tPTHR00006\tWIDGET PROTEIN\t1e-30\nT7.1\tPTHR00007\t-\t1e-25\nT9.1\tPTHR00009\tPROTEIN CBG12345\t1e-20\n");
# T7 has two domains: the LOWER E-value one (SMART, IPR000002) must name it, not the lower accession
write_file("$dir/iprscan.tsv", join('', map { join("\t", @$_) . "\n" }
  ['T7.1', 'md5', 200, 'Pfam',  'PF00001', 'kinase', 1, 100, '1.0E-5',  'T', 'd', 'IPR000001', 'Kinase domain', '-', '-'],
  ['T7.1', 'md5', 200, 'SMART', 'SM00002', 'RING',   120, 180, '1.0E-20', 'T', 'd', 'IPR000002', 'Zinc finger, RING-type', '-', '-'],
  ['T8.1', 'md5', 150, 'Pfam',  'PF00003', 'duf',    1, 50, '1.0E-9',   'T', 'd', 'IPR000003', 'Domain of unknown function DUF1', '-', '-'],
));
write_file("$dir/entry.list", "ENTRY_AC\tENTRY_TYPE\tENTRY_NAME\nIPR000001\tDomain\tKinase domain\n"
  . "IPR000002\tDomain\tZinc finger, RING-type\nIPR000003\tDomain\tDomain of unknown function DUF1\n");

# ---- run it (twice, with different hash seeds: the output must not depend on hash order)
my @arguments = ('--isoforms', "$dir/isoforms.tsv", '--protein-fasta', "$dir/protein.aa.fa", '--hgnc-dir', "$dir/hgnc",
  '--oma-dir', "$dir/oma", '--oma-code', 'TEST', '--mmseqs-dir', "$dir/mmseqs", '--ref-db', "$dir/refdb",
  '--panther', "$dir/PANTHER.iprscan.moop.tsv", '--interproscan', "$dir/iprscan.tsv", '--interpro-entries', "$dir/entry.list",
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
  foreach my $row (read_tsv($file)) { $source{$row->[0]} = [ @$row[1 .. 3], $kind ] if $row->[0] =~ /^G\d$/; }
}
my %closest_human = map { $_->[1] => $_ } read_tsv("$out/closest_human.tsv");
my %closest_nvec  = map { $_->[1] => $_ } read_tsv("$out/closest_nvec.tsv");

# ---- names: one rule per gene
check(($name{G1} // '') eq 'ALPHA: alpha synthase', 'G1: OMA 1:1 -> the human name, plain', $name{G1});
check(($name{G2} // '') eq 'GAMMA: gamma transferase' && ($name{G3} // '') eq 'GAMMA: gamma transferase',
      'G2/G3: OMA many:1 -> every copy the plain name, no "(k of n)"', "$name{G2} | $name{G3}");
check(($name{G4} // '') eq 'Beta proteins family member', 'G4: OMA 1:many -> the HGNC group, no symbol, no member picked', $name{G4});
check(($name{G5} // '') eq 'DELTA-like: delta kinase-like', 'G5: full-length reciprocal hit -> "-like", never plain', $name{G5});
check(($name{G6} // '') eq 'WIDGET PROTEIN family member', 'G6: partial hit cannot name; PANTHER family does', $name{G6});
check(($name{G7} // '') eq 'Zinc finger RING-type domain-containing protein',
      'G7: PANTHER "-" skipped; the LOWER E-value InterPro domain names it, comma dropped', $name{G7});
check(($name{G8} // '') eq 'None', 'G8: only a "unknown function" domain -> None', $name{G8});
check(($name{G9} // '') eq 'None', 'G9: only a locus-id PANTHER family ("PROTEIN CBG12345") -> None', $name{G9});
check(!grep({ / \(\d+ of \d+\)$|^- family member$|  / } values %name), 'no "(k of n)", "- family member" or double spaces anywhere');

# ---- provenance: why each name, as loaded into MOOP (accession, description, step, source)
my $p = sub { my ($gene) = @_; return $source{$gene} ? join(' | ', @{$source{$gene}}) : '(no row)'; };
check(($source{G1}[0] // '') eq 'HGNC:1' && ($source{G1}[1] // '') eq 'Ortholog of human ALPHA (OMA, 1:1)' && ($source{G1}[2] // '') eq '3',
      'G1 provenance: HGNC:1, OMA 1:1, step 3', $p->('G1'));
check(($source{G2}[1] // '') eq 'Ortholog of human GAMMA (OMA, many:1): one of 2 copies in this genome',
      'G2 provenance: the copy count is here, not in the name', $p->('G2'));
check(($source{G4}[3] // '') eq 'hgnc_group' && ($source{G4}[0] // '') eq '10'
      && ($source{G4}[1] // '') eq 'Co-ortholog of 2 human genes in the HGNC group "Beta proteins" (OMA, 1:many); no single ortholog',
      'G4 provenance: HGNC gene group id 10, linked as a group', $p->('G4'));
check(($source{G5}[1] // '') eq 'Similar to human DELTA along its length: reciprocal best hit, 95% of this protein and 95% of DELTA aligned, E=1e-50 (MMseqs2)'
      && ($source{G5}[2] // '') eq '4', 'G5 provenance: coverage and E-value, step 4', $p->('G5'));
check(($source{G6}[3] // '') eq 'panther' && ($source{G6}[0] // '') eq 'PTHR00006' && ($source{G6}[2] // '') eq '5',
      'G6 provenance: PANTHER family, step 5', $p->('G6'));
check(($source{G7}[0] // '') eq 'IPR000002' && ($source{G7}[1] // '') =~ /\(SMART SM00002, E=1e-20\)/ && ($source{G7}[2] // '') eq '6',
      'G7 provenance: the chosen domain with its E-value, step 6', $p->('G7'));
check(!exists $source{G8} && !exists $source{G9}, 'unnamed genes have no provenance row');

# ---- closest genes: one entry per gene; families as families
check(($closest_human{G1}[2] // '') eq 'HGNC:1', 'closest human G1: HGNC:1');
check(($closest_human{G4}[2] // 'x') eq '' && ($closest_human{G4}[3] // '') eq 'Beta proteins family' && ($closest_human{G4}[5] // '') =~ /family of 2$/,
      'closest human G4: the family, no gene id', join(' | ', @{$closest_human{G4} // []}));
check(($closest_human{G6}[2] // '') eq 'HGNC:6' && ($closest_human{G6}[5] // '') =~ /reciprocal best hit/,
      'closest human G6: a partial RBH still counts as evidence (normal filter)', join(' | ', @{$closest_human{G6} // []}));
check(($closest_nvec{G1}[2] // '') eq 'XP_000011.1' && ($closest_nvec{G1}[5] // '') eq 'OMA ortholog (1:1)',
      'closest Nvec G1: from the OMA pairs (the check that caught the empty-ranks bug)', join(' | ', @{$closest_nvec{G1} // []}));
check(($closest_nvec{G4}[2] // 'x') eq '' && ($closest_nvec{G4}[5] // '') =~ /family of 2$/, 'closest Nvec G4: a family');

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
