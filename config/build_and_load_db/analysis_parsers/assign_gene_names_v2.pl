#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;
use FindBin;
use lib "$FindBin::Bin";
use GeneNamingV2 qw(clean_name split_symbol is_placeholder_symbol is_informative_hit
                    add_like_to_description add_like_to_symbol load_hgnc hgnc_record);
use OmaHogOrthologs qw(read_hog_orthologs parse_oma_header);

# Gene naming v2: a name for every gene and, separately, its closest human gene.
# Design: notes/NAMING_V2_PLAN.md.
#
#   assign_gene_names_v2.pl --isoforms isoforms.tsv --protein-fasta protein.aa.fa \
#       --hgnc-dir moop/hgnc [--oma-dir OMA_v2/<org>/<asm>/<gs> --oma-code CODE] \
#       [--mmseqs-dir <analysis>/rbh_mmseq] [--diamond-dir <analysis>/diamond] [--ref-db REF_DB] \
#       [--compara-dir moop/ensembl_compara] [--uniprot-dir moop/uniprot] \
#       [--taxonomy-dir moop/ncbi_taxonomy] [--panther PANTHER.iprscan.moop.tsv] \
#       [--native native_geneNames.tsv] [--override curated.moop.tsv ...] \
#       --out-names geneNames.tsv --out-moop closest_human.moop.tsv
#
# Closest human gene, strongest first (the tier is the Score of the moop table):
#   1 OMA pairwise ortholog to HUMAN
#   2 OMA HOG co-ortholog with HUMAN (only when parameters.drw has a fixed SpeciesTree)
#   3 MMseqs2 reciprocal best hit to Ensembl human (filtered)
#   4 via another species: OMA ortholog in a reference species -> its OMA HUMAN ortholog, or
#     MMseqs2 reciprocal best hit -> Ensembl Compara human ortholog (same Ensembl release)
#   5 DIAMOND best hit to a human protein (Ensembl human or a Swiss-Prot HUMAN entry, filtered)
#   6 DIAMOND Swiss-Prot hit in another species -> its Ensembl gene -> Ensembl Compara
#   7 DIAMOND Swiss-Prot hit in another species -> its PANTHER subfamily -> the human
#     Swiss-Prot genes in that subfamily
#
# Names: curated override > native name if informative > tier 1-2 human ortholog
# (1:1 plain, many:1 "(n of X)", 1:many family) > best hit by bitscore (human reciprocal hit
# plain unless its human gene is already another gene's ortholog; everything else "-like",
# with the species when it is not human: "acrosin-like (turkey)") > PANTHER family > None.
#
# Output geneNames.tsv: ID MAINID GroupId Desc Note closestHGNC closestHumanSym
# closestHumanDesc closestHumanEvidence (first five as before; one row per id in isoforms.tsv
# or, with --native, per id in the native file). The Closest Human Gene moop table has a row
# for the gene and for every isoform.

# --extra-hits FILE: a per-gene-set moop TSV of similarity hits (e.g. reciprocal best hits to a
# RefSeq proteome no other source covers) added as naming candidates, ranked like any other hit
# (E-value only, so after hits that report a bitscore). Species label: --extra-hits-species.
my %opt = (override => [], 'extra-hits' => []);
GetOptions(\%opt, 'isoforms=s', 'protein-fasta=s', 'protein2gene=s', 'hgnc-dir=s',
           'oma-dir=s', 'oma-code=s', 'mmseqs-dir=s', 'diamond-dir=s', 'ref-db=s',
           'compara-dir=s', 'uniprot-dir=s', 'taxonomy-dir=s', 'panther=s', 'native=s',
           'override=s@', 'extra-hits=s@', 'extra-hits-species=s', 'out-names=s', 'out-moop=s')
  or die "bad options\n";
foreach my $required (qw(isoforms protein-fasta hgnc-dir out-names out-moop)) {
  die "--$required is required\n" unless defined $opt{$required};
}

# ---- filters (percent; see the plan)
my %NORMAL = (evalue => 1e-10, qcov => 50, tcov => 50);
my %STRONG = (evalue => 1e-50, qcov => 80, tcov => 80, pident => 50, bits => 200);

my %COMMON_NAME = (
  MOUSE => 'mouse', DROME => 'fly', LOTGI => 'limpet', CAPTE => 'annelid (Capitella)',
  BRAFL => 'amphioxus', CALMI => 'elephant shark', LEPOC => 'spotted gar', NEMVE => 'sea anemone',
  MONBE => 'choanoflagellate',
  anolis_carolinensis => 'anole lizard', astyanax_mexicanus => 'cavefish',
  astyanax_mexicanus_pachon => 'cavefish (Pachon)', caenorhabditis_elegans => 'worm',
  danio_rerio => 'zebrafish', drosophila_melanogaster => 'fly', gallus_gallus => 'chicken',
  gallus_gallus_gca000002315v5 => 'chicken (GRCg6a)', mus_musculus => 'mouse',
  oryzias_latipes => 'medaka', petromyzon_marinus => 'lamprey', pogona_vitticeps => 'bearded dragon',
  saccharomyces_cerevisiae => 'yeast', xenopus_tropicalis => 'frog', escherichia_coli => 'E. coli',
);

my %stats;

# ============================================================== genes and ids
my (%group_of, %members, %curated_selected);
read_isoforms($opt{isoforms});
my %gene_of_protein = read_protein2gene($opt{protein2gene});
my %query_length = fasta_lengths($opt{'protein-fasta'});

my $hgnc = load_hgnc("$opt{'hgnc-dir'}/hgnc_complete_set.txt", "$opt{'hgnc-dir'}/withdrawn.txt");

# ============================================================== evidence
my %human_links;   # group -> [ link ]   link = {tier, human => [records], type, evidence, bits, id, hit}
my %hits;          # group -> [ naming candidates from similarity ]
my @pending_compara;  # links through another species' Ensembl gene, resolved in one Compara pass

my %reference_fasta_cache;
if (defined $opt{'oma-dir'}) {
  collect_oma();
}
if (defined $opt{'mmseqs-dir'}) {
  collect_mmseqs();
}
if (defined $opt{'diamond-dir'}) {
  collect_diamond();
  link_swissprot_hits() if defined $opt{'uniprot-dir'};
}
foreach my $extra_file (@{$opt{'extra-hits'}}) {
  read_extra_hits($extra_file);
}
resolve_compara();
name_species();
my %panther = defined $opt{panther} ? read_panther($opt{panther}) : ();
my %override;
foreach my $override_file (@{$opt{override}}) {
  read_override($override_file, \%override);
}

# ============================================================== decide
my %claimed_human;   # human key -> { group => 1 } for tier 1-2 orthologs
foreach my $group (keys %human_links) {
  foreach my $link (@{$human_links{$group}}) {
    next unless $link->{tier} <= 2;
    foreach my $human (@{$link->{human}}) {
      $claimed_human{$human->{key}}{$group} = 1;
    }
  }
}

my %closest;   # group -> { tier, human => [records], evidence, id }
foreach my $group (keys %members) {
  $closest{$group} = choose_closest_human($group);
}

my %name;      # group -> { desc, note, selected }
foreach my $group (keys %members) {
  $name{$group} = choose_name($group);
}

# ============================================================== write
write_outputs();
foreach my $key (sort keys %stats) {
  warn sprintf("%-40s %d\n", $key, $stats{$key});
}

# ##############################################################################
# input readers

sub read_isoforms {
  my ($file) = @_;
  open my $fh, '<', $file or die "cant open isoforms file $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    next if $line !~ /\S/;
    my ($ids, $selected, $group) = split /\t/, $line;
    $group = $line unless defined $group;
    foreach my $id (split /;/, $ids) {
      $group_of{$id} = $group;
      push @{$members{$group}}, $id;
    }
    $curated_selected{$group} = $selected if defined $selected and $selected ne 'None' and $selected ne '';
  }
  close $fh;
}

sub read_protein2gene {
  my ($file) = @_;
  my %map;
  return %map unless defined $file and -e $file;
  open my $fh, '<', $file or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    my ($protein, $gene) = split /\t/, $line;
    $map{$protein} = $gene if defined $gene;
  }
  close $fh;
  return %map;
}

sub fasta_lengths {
  my ($file) = @_;
  my (%length, $id);
  my $open = $file =~ /\.gz$/ ? "gzip -dc '$file' |" : "< $file";
  open my $fh, $open or die "cant open fasta $file $!\n";
  while (my $line = <$fh>) {
    if ($line =~ /^>(\S+)/) {
      $id = $1;
      $length{$id} = 0;
    } elsif (defined $id) {
      $line =~ s/\s//g;
      $length{$id} += length $line;
    }
  }
  close $fh;
  return %length;
}

# group for an id seen in some analysis: exact, without mmseqs ":pep", without ORF ".pN",
# or through protein2gene when the gene id is a group
sub group_for {
  my ($id) = @_;
  return undef unless defined $id;
  foreach my $candidate ($id, strip_suffixes($id)) {
    return $group_of{$candidate} if exists $group_of{$candidate};
  }
  my $plain = strip_suffixes($id);
  if (exists $gene_of_protein{$plain} and exists $members{$gene_of_protein{$plain}}) {
    return $gene_of_protein{$plain};
  }
  return undef;
}

sub strip_suffixes {
  my ($id) = @_;
  $id =~ s/:pep$//;
  $id =~ s/\.p\d+$//i;
  return $id;
}

sub query_length_of {
  my ($id) = @_;
  return $query_length{$id} // $query_length{strip_suffixes($id)};
}

# ##############################################################################
# human records

# key a human gene by HGNC id when there is one, else by Ensembl gene
sub human_record {
  my (%keys) = @_;
  my $record = hgnc_record($hgnc, %keys);
  if ($record) {
    return { key => $record->{hgnc_id}, hgnc_id => $record->{hgnc_id}, symbol => $record->{symbol},
             name => $record->{name}, gene_group => $record->{gene_group} };
  }
  my $ensembl_gene = $keys{ensembl_gene} // '';
  $ensembl_gene =~ s/\.\d+$//;
  my $description = clean_name($keys{description} // '');
  return undef if $ensembl_gene eq '' and $description eq '';
  return { key => ($ensembl_gene ne '' ? $ensembl_gene : "desc:$description"), hgnc_id => '',
           symbol => $ensembl_gene, name => $description, gene_group => '' };
}

sub human_from_oma_header {
  my ($header) = @_;
  my $parsed = parse_oma_header($header);
  return human_record(hgnc_id => $parsed->{hgnc_id}, ensembl_gene => $parsed->{gene_id},
                      uniprot => $parsed->{uniprot}, description => $parsed->{description});
}

sub add_link {
  my ($group, %link) = @_;
  push @{$human_links{$group}}, \%link;
}

# ##############################################################################
# OMA: pairwise (tier 1), HOGs (tier 2), reference-species chains (tier 4)

sub collect_oma {
  my $output = "$opt{'oma-dir'}/Output";
  my $code = $opt{'oma-code'} or die "--oma-code is required with --oma-dir\n";
  die "no $output/PairwiseOrthologs\n" unless -d "$output/PairwiseOrthologs";

  # tier 1
  my $direct = read_oma_pairs($output, $code, 'HUMAN');
  foreach my $target_id (keys %$direct) {
    my $group = group_for($target_id) or next;
    foreach my $pair (@{$direct->{$target_id}}) {
      my $human = human_from_oma_header($pair->{partner_header}) or next;
      add_link($group, tier => 1, human => [$human], type => $pair->{type}, id => $target_id,
               evidence => "OMA ortholog ($pair->{type})", hit => $pair->{partner_id});
    }
  }

  # tier 2, only with a fixed species tree
  my $parameters = "$opt{'oma-dir'}/parameters.drw";
  my $fixed_tree = 0;
  if (open my $parameters_fh, '<', $parameters) {
    while (my $line = <$parameters_fh>) {
      $fixed_tree = 1 if $line =~ /^\s*SpeciesTree\s*:=\s*'\(/;
    }
    close $parameters_fh;
  }
  if ($fixed_tree and -s "$output/HierarchicalGroups.orthoxml") {
    my $hogs = read_hog_orthologs("$output/HierarchicalGroups.orthoxml", $code);
    my $human_pairs = $hogs->{pairs}{HUMAN} // {};
    foreach my $target_gene (keys %$human_pairs) {
      my $target_id = $hogs->{genes}{$target_gene}{prot_id};
      my $group = group_for($target_id) or next;
      foreach my $human_gene (keys %{$human_pairs->{$target_gene}}) {
        my $human = human_from_oma_header($hogs->{genes}{$human_gene}{header}) or next;
        my $type = $hogs->{type}{HUMAN}{$target_gene}{$human_gene};
        add_link($group, tier => 2, human => [$human], type => $type, id => $target_id,
                 evidence => "OMA HOG co-ortholog ($type)", hit => $hogs->{genes}{$human_gene}{prot_id});
      }
    }
  } else {
    $stats{'note: HOG tier skipped (no fixed SpeciesTree)'} = 1;
  }

  # tier 4 via reference species: target -> REF ortholog -> REF's HUMAN ortholog
  opendir my $dir_handle, "$output/PairwiseOrthologs" or die "cant read $output/PairwiseOrthologs\n";
  my %references;
  foreach my $file (readdir $dir_handle) {
    next unless $file =~ /^([A-Za-z0-9]+)-([A-Za-z0-9]+)\.txt$/;
    my ($left, $right) = ($1, $2);
    $references{$right} = 1 if $left eq $code and $right ne 'HUMAN';
    $references{$left}  = 1 if $right eq $code and $left ne 'HUMAN';
  }
  closedir $dir_handle;
  foreach my $reference (sort keys %references) {
    my $to_reference = read_oma_pairs($output, $code, $reference);
    my $reference_to_human = read_oma_pairs($output, $reference, 'HUMAN');
    foreach my $target_id (keys %$to_reference) {
      my $group = group_for($target_id) or next;
      foreach my $first (@{$to_reference->{$target_id}}) {
        foreach my $second (@{$reference_to_human->{$first->{partner_id}} // []}) {
          my $human = human_from_oma_header($second->{partner_header}) or next;
          my $common = $COMMON_NAME{$reference} // $reference;
          add_link($group, tier => 4, human => [$human], type => $second->{type}, id => $target_id,
                   evidence => "via $common ortholog (OMA $first->{type}) > OMA ortholog ($second->{type})",
                   hit => $second->{partner_id});
        }
      }
    }
  }
}

# pairs between species A and B from PairwiseOrthologs/A-B.txt or B-A.txt, keyed by A's
# protein id; type oriented A:B
sub read_oma_pairs {
  my ($output, $species_a, $species_b) = @_;
  my %pairs;
  my $forward = "$output/PairwiseOrthologs/$species_a-$species_b.txt";
  my $reverse = "$output/PairwiseOrthologs/$species_b-$species_a.txt";
  my ($file, $a_first);
  if (-e $forward) {
    ($file, $a_first) = ($forward, 1);
  } elsif (-e $reverse) {
    ($file, $a_first) = ($reverse, 0);
  } else {
    return \%pairs;
  }
  open my $fh, '<', $file or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    next if $line =~ /^#/;
    chomp $line;
    my ($number_1, $number_2, $header_1, $header_2, $type) = split /\t/, $line;
    my ($a_header, $b_header) = $a_first ? ($header_1, $header_2) : ($header_2, $header_1);
    unless ($a_first) {
      my ($left, $right) = split /:/, $type;
      $type = "$right:$left";
    }
    my ($a_id) = $a_header =~ /^(\S+)/;
    my ($b_id) = $b_header =~ /^(\S+)/;
    push @{$pairs{$a_id}}, { partner_id => $b_id, partner_header => $b_header, type => $type };
  }
  close $fh;
  return \%pairs;
}

# ##############################################################################
# MMseqs2 reciprocal best hits (tier 3 human, tier 4 via Compara, naming candidates)

sub collect_mmseqs {
  my $base = $opt{'mmseqs-dir'};
  opendir my $dir_handle, $base or die "cant read $base\n";
  my @species_dirs;
  foreach my $entry (sort readdir $dir_handle) {
    push @species_dirs, $entry if $entry =~ /^ENS_/ and -s "$base/$entry/rbh_mmseq_results.tsv";
  }
  closedir $dir_handle;
  foreach my $species_dir (@species_dirs) {
    (my $species = $species_dir) =~ s/^ENS_//;
    my $release = read_release("$base/$species_dir/db_version.txt");
    my $reference = reference_proteome($species_dir);
    next unless $reference;

    open my $fh, '<', "$base/$species_dir/rbh_mmseq_results.tsv" or die "cant open mmseqs results $!\n";
    while (my $line = <$fh>) {
      chomp $line;
      next if $line =~ /^query\t/ or $line !~ /\S/;
      my ($query, $target, $pident, $alnlen, $mismatch, $gapopen,
          $qstart, $qend, $tstart, $tend, $evalue, $bits) = split /\t/, $line;
      my $group = group_for($query) or next;
      my $query_len  = query_length_of($query);
      my $target_len = $reference->{$target}{length};
      next unless $query_len and $target_len;
      my %hit = (
        evalue => $evalue, bits => $bits, pident => $pident * 100,
        qcov => 100 * ($qend - $qstart + 1) / $query_len,
        tcov => 100 * ($tend - $tstart + 1) / $target_len,
      );
      next unless passes(\%hit, \%NORMAL);
      my $info = $reference->{$target};
      my $common = $COMMON_NAME{$species} // $species;

      if ($species eq 'homo_sapiens') {
        my $human = human_record(ensembl_gene => $info->{gene}, description => $info->{description}) or next;
        add_link($group, tier => 3, human => [$human], type => '1:1', id => $query, bits => $bits,
                 evidence => 'reciprocal best hit (MMseqs2)', hit => $target);
        push @{$hits{$group}}, { %hit, source => 'MMseqs2_RBH_Homo_sapiens', type => 'RBBH_Homolog',
                                 id => $query, hit => $target, human => $human, reciprocal => 1,
                                 symbol => $human->{symbol}, description => $human->{name}, species => 'human' };
      } else {
        push @{$hits{$group}}, { %hit, source => "MMseqs2_RBH_$species", type => 'RBBH_Homolog',
                                 id => $query, hit => $target, reciprocal => 1, species => $common,
                                 symbol => $info->{symbol}, description => $info->{description} };
        push @pending_compara, { group => $group, release => $release, genes => [ $info->{gene} ],
                                 tier => 4, id => $query, hit => $target, bits => $bits,
                                 via => "via $common reciprocal best hit (MMseqs2)" };
      }
    }
    close $fh;
  }
}

sub read_release {
  my ($file) = @_;
  open my $fh, '<', $file or return '';
  my $line = <$fh> // '';
  close $fh;
  # "release-113" or "ENS_<species><TAB>release-113"; main Ensembl only -- Ensembl Genomes
  # ("release-61_bacteria_...") has no human Compara
  my ($release) = $line =~ /(?:^|\s)release-(\d+)\s*$/;
  return $release // '';
}

# protein id -> { gene, symbol, description, length } from REF_DB/ENS_<species>/current
sub reference_proteome {
  my ($species_dir) = @_;
  return $reference_fasta_cache{$species_dir} if exists $reference_fasta_cache{$species_dir};
  my $ref_db = $opt{'ref-db'} or die "--ref-db is required for MMseqs2/DIAMOND hits\n";
  my @fastas = glob "$ref_db/$species_dir/current/*.pep.all.fa.gz";
  if (!@fastas) {
    warn "WARNING: no reference proteome for $species_dir under $ref_db\n";
    $reference_fasta_cache{$species_dir} = undef;
    return undef;
  }
  my (%proteins, $id);
  open my $fh, "gzip -dc '$fastas[0]' |" or die "cant read $fastas[0]\n";
  while (my $line = <$fh>) {
    if ($line =~ /^>(\S+)/) {
      $id = $1;
      my ($gene)   = $line =~ /\bgene:(\S+)/;
      my ($symbol) = $line =~ /\bgene_symbol:(\S+)/;
      my ($desc)   = $line =~ /\bdescription:(.+?)\s*$/;
      $proteins{$id} = { gene => $gene // $id, symbol => $symbol // '', description => clean_name($desc // ''), length => 0 };
    } elsif (defined $id) {
      $line =~ s/\s//g;
      $proteins{$id}{length} += length $line;
    }
  }
  close $fh;
  $reference_fasta_cache{$species_dir} = \%proteins;
  return \%proteins;
}

# Links through another species' Ensembl gene (MMseqs2 hits, Swiss-Prot cross-references):
# one pass over each Compara release file, keeping only the genes asked for.
# A link without a release uses the newest release downloaded.
my %compara_releases_used;
sub resolve_compara {
  return unless @pending_compara and defined $opt{'compara-dir'};
  my @available;
  foreach my $release_dir (glob "$opt{'compara-dir'}/release-*") {
    push @available, $1 if $release_dir =~ /release-(\d+)$/ and -s "$release_dir/homo_sapiens.orthologs.tsv.gz";
  }
  @available = sort { $a <=> $b } @available;
  return unless @available;

  my %wanted;   # release -> gene -> 1
  foreach my $pending (@pending_compara) {
    my $release = $pending->{release} ne '' ? $pending->{release} : $available[-1];
    $pending->{release} = $release;
    foreach my $gene (@{$pending->{genes}}) {
      $wanted{$release}{$gene} = 1;
    }
  }
  my %orthologs;   # release -> gene -> [ { human_gene, type } ]
  foreach my $release (sort keys %wanted) {
    my $file = "$opt{'compara-dir'}/release-$release/homo_sapiens.orthologs.tsv.gz";
    if (!-s $file) {
      $stats{"warning: no Compara release $release (run update_reference_data.sh)"} = 1;
      next;
    }
    $compara_releases_used{$release} = 1;
    open my $fh, "gzip -dc '$file' |" or die "cant read $file\n";
    <$fh>;
    while (my $line = <$fh>) {
      my ($human_gene, $human_protein, $human_species, $identity, $type, $other_gene) = split /\t/, $line, 7;
      next unless $wanted{$release}{$other_gene};
      (my $short_type = $type) =~ s/^ortholog_//;
      push @{$orthologs{$release}{$other_gene}}, { human_gene => $human_gene, type => $short_type };
    }
    close $fh;
  }
  foreach my $pending (@pending_compara) {
    foreach my $gene (@{$pending->{genes}}) {
      foreach my $ortholog (@{$orthologs{$pending->{release}}{$gene} // []}) {
        my $human = human_record(ensembl_gene => $ortholog->{human_gene}) or next;
        add_link($pending->{group}, tier => $pending->{tier}, human => [$human], type => $ortholog->{type},
                 id => $pending->{id}, bits => $pending->{bits}, hit => $pending->{hit},
                 evidence => "$pending->{via} > Ensembl Compara ($ortholog->{type})");
      }
    }
  }
}

# Non-human Swiss-Prot hits: Ensembl gene -> Compara (tier 6, resolved with the rest), or
# PANTHER subfamily -> the human Swiss-Prot genes in it (tier 7). Also records each hit's taxon.
sub link_swissprot_hits {
  my $file = "$opt{'uniprot-dir'}/sprot_xrefs.tsv.gz";
  if (!-s $file) {
    $stats{'warning: no uniprot/sprot_xrefs.tsv.gz (run update_reference_data.sh)'} = 1;
    return;
  }
  my %wanted;
  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      $wanted{$hit->{accession}} = 1 if defined $hit->{accession};
    }
  }
  my (%xref, %human_in_subfamily);
  open my $fh, "gzip -dc '$file' |" or die "cant read $file\n";
  <$fh>;
  while (my $line = <$fh>) {
    chomp $line;
    my ($accession, $taxid, $gene_name, $hgnc_ids, $genes, $proteins, $panther) = split /\t/, $line, -1;
    if ($taxid eq '9606') {
      my ($hgnc_id) = split /;/, $hgnc_ids;
      foreach my $family (split /;/, $panther) {
        $human_in_subfamily{$family}{$hgnc_id} = 1 if $family =~ /:SF/ and defined $hgnc_id and $hgnc_id ne '';
      }
    }
    next unless $wanted{$accession};
    my @subfamilies;
    foreach my $family (split /;/, $panther) {
      push @subfamilies, $family if $family =~ /:SF/;
    }
    $xref{$accession} = { taxid => $taxid, genes => [ split /;/, $genes ], subfamilies => \@subfamilies };
  }
  close $fh;

  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      next unless defined $hit->{accession} and !$hit->{human};
      my $cross = $xref{$hit->{accession}} or next;
      $hit->{taxid} = $cross->{taxid};
      my $what = "$hit->{species_scientific} Swiss-Prot hit" . ($hit->{symbol} ne '' ? " $hit->{symbol}" : '');
      if (@{$cross->{genes}}) {
        push @pending_compara, { group => $group, release => '', genes => $cross->{genes}, tier => 6,
                                 id => $hit->{id}, hit => $hit->{hit}, bits => $hit->{bits}, via => "via $what" };
      }
      foreach my $subfamily (@{$cross->{subfamilies}}) {
        my @hgnc_ids = sort keys %{$human_in_subfamily{$subfamily} // {}};
        next unless @hgnc_ids;
        my @humans;
        foreach my $hgnc_id (@hgnc_ids) {
          my $human = human_record(hgnc_id => $hgnc_id);
          push @humans, $human if $human;
        }
        next unless @humans;
        add_link($group, tier => 7, human => \@humans, type => 'PANTHER subfamily', id => $hit->{id},
                 bits => $hit->{bits}, hit => $hit->{hit},
                 evidence => "via $what > PANTHER subfamily $subfamily");
      }
    }
  }
}

# common names for the species of Swiss-Prot hits ("turkey"), from NCBI genbank common names
sub name_species {
  my %wanted;
  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      $wanted{$hit->{taxid}} = 1 if defined $hit->{taxid};
    }
  }
  my %common;
  if (%wanted and defined $opt{'taxonomy-dir'} and open my $fh, '<', "$opt{'taxonomy-dir'}/names.dmp") {
    while (my $line = <$fh>) {
      next unless $line =~ /genbank common name/;
      my ($taxid, $name) = split /\t\|\t/, $line;
      $common{$taxid} = $name if $wanted{$taxid};
    }
    close $fh;
  }
  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      next unless defined $hit->{taxid};
      $hit->{species} = $common{$hit->{taxid}} // $hit->{species_scientific};
    }
  }
}

# ##############################################################################
# DIAMOND best hits (Swiss-Prot and Ensembl species)

sub collect_diamond {
  my $base = $opt{'diamond-dir'};
  opendir my $dir_handle, $base or die "cant read $base\n";
  my @dbs;
  foreach my $entry (sort readdir $dir_handle) {
    push @dbs, $entry if -e "$base/$entry/diamond_results.tsv" or -e "$base/$entry/diamond_results.tsv.gz";
  }
  closedir $dir_handle;
  foreach my $db (@dbs) {
    my $file = -e "$base/$db/diamond_results.tsv" ? "$base/$db/diamond_results.tsv" : "$base/$db/diamond_results.tsv.gz";
    my $open = $file =~ /\.gz$/ ? "gzip -dc '$file' |" : "< $file";
    open my $fh, $open or die "cant read $file\n";
    my $has_coverage = 0;
    while (my $line = <$fh>) {
      chomp $line;
      if ($line =~ /^qseqid\t/) {
        $has_coverage = $line =~ /\tscovhsp/ ? 1 : 0;
        next;
      }
      my @fields = split /\t/, $line;
      my ($query, $subject, $title, $evalue) = @fields[0 .. 3];
      my $group = group_for($query) or next;
      my %hit = (evalue => $evalue);
      if (@fields >= 17) {
        @hit{qw(pident bits qcov tcov)} = ($fields[4], $fields[12], $fields[15], $fields[16]);
        $has_coverage = 1;
      }
      next unless passes(\%hit, \%NORMAL);
      my $candidate = diamond_candidate($db, $subject, $title);
      next unless $candidate;
      push @{$hits{$group}}, { %hit, %$candidate, id => $query, hit => $subject, reciprocal => 0 };
      if ($candidate->{human}) {
        add_link($group, tier => 5, human => [$candidate->{human}], type => 'best hit', id => $query,
                 bits => $hit{bits}, evidence => "best BLAST hit ($candidate->{label})", hit => $subject);
      }
    }
    close $fh;
    $stats{"note: DIAMOND $db without coverage columns (E-value only)"} = 1 unless $has_coverage;
  }
}

sub diamond_candidate {
  my ($db, $subject, $title) = @_;
  if ($db =~ /sprot/i) {
    # sp|Q6AZB8|HARB1_DANRE Putative nuclease HARBI1 OS=Danio rerio OX=7955 GN=harbi1 PE=2 SV=1
    my ($accession) = $subject =~ /^sp\|([^|]+)\|/;
    my ($description) = $title =~ /^\S+\s+(.*?)\s+OS=/;
    my ($organism) = $title =~ /\bOS=(.*?)\s+OX=/;
    my ($symbol) = $title =~ /\bGN=(\S+)/;
    my ($taxid) = $title =~ /\bOX=(\d+)/;
    my $candidate = { source => 'UniProtKB/Swiss-Prot', type => 'Homologs', species => $organism // '',
                      species_scientific => $organism // '', taxid => $taxid, accession => $accession,
                      symbol => $symbol // '', description => clean_name($description // ''),
                      label => 'Swiss-Prot' };
    if (defined $organism and $organism eq 'Homo sapiens' and defined $accession) {
      $candidate->{human} = human_record(uniprot => [$accession], description => $description);
      $candidate->{symbol} = $candidate->{human}{symbol} if $candidate->{human} and $candidate->{human}{hgnc_id};
    }
    return $candidate;
  }
  if ($db =~ /^ENS_(.+)$/) {
    my $species = $1;
    my ($gene)   = $title =~ /\bgene:(\S+)/;
    my ($symbol) = $title =~ /\bgene_symbol:(\S+)/;
    my ($desc)   = $title =~ /\bdescription:(.+?)\s*$/;
    my $candidate = { source => "Ensembl_$species", type => 'Homologs', species => $COMMON_NAME{$species} // $species,
                      symbol => $symbol // '', description => clean_name($desc // ''),
                      label => "Ensembl $species" };
    if ($species eq 'homo_sapiens') {
      $candidate->{human} = human_record(ensembl_gene => $gene, description => $desc);
      $candidate->{label} = 'Ensembl human';
    }
    return $candidate;
  }
  return undef;
}

# E-value always; the rest only when the hit reports it
sub passes {
  my ($hit, $filter) = @_;
  return 0 unless defined $hit->{evalue} and $hit->{evalue} <= $filter->{evalue};
  foreach my $measure (qw(qcov tcov)) {
    next unless defined $filter->{$measure};
    return 0 if defined $hit->{$measure} and $hit->{$measure} < $filter->{$measure};
  }
  if (defined $filter->{pident}) {
    return 0 unless defined $hit->{qcov} and defined $hit->{tcov};
    my $identity_ok = defined $hit->{pident} && $hit->{pident} >= $filter->{pident};
    my $bits_ok     = defined $hit->{bits}   && $hit->{bits}   >= $filter->{bits};
    return 0 unless $identity_ok or $bits_ok;
  }
  return 1;
}

# ##############################################################################
# PANTHER and curated overrides (moop TSV: id accession description score)

sub read_panther {
  my ($file) = @_;
  my %best;
  open my $fh, '<', $file or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    next if $line =~ /^#/;
    chomp $line;
    my ($id, $family, $description, $score) = split /\t/, $line;
    my $group = group_for($id) or next;
    my $evalue = ($score // '') =~ /^[0-9.eE+-]+$/ ? $score : 1;
    if (!exists $best{$group} or $evalue < $best{$group}{evalue}) {
      $best{$group} = { id => $id, family => $family, description => $description // '', evalue => $evalue };
    }
  }
  close $fh;
  return %best;
}

sub read_extra_hits {
  my ($file) = @_;
  open my $fh, '<', $file or die "cant open extra hits $file $!\n";
  my $source = $file;
  while (my $line = <$fh>) {
    if ($line =~ /^## Annotation Source:\s*(.+?)\s*$/) {
      $source = $1;
    }
    next if $line =~ /^#/;
    chomp $line;
    my ($id, $accession, $description, $score) = split /\t/, $line;
    my $group = group_for($id) or next;
    my %hit = (evalue => (($score // '') =~ /^[0-9.eE+-]+$/ ? $score : 1));
    next unless passes(\%hit, \%NORMAL);
    my ($symbol, $name) = split_symbol($description // '');
    (my $source_tag = $source) =~ s/\s+/_/g;
    push @{$hits{$group}}, { %hit, source => $source_tag, type => 'Homologs', id => $id, hit => $accession,
                             reciprocal => 0, symbol => $symbol, description => clean_name($name),
                             species => $opt{'extra-hits-species'} // $source };
  }
  close $fh;
}

sub read_override {
  my ($file, $override) = @_;
  open my $fh, '<', $file or die "cant open override $file $!\n";
  my $source = $file;
  while (my $line = <$fh>) {
    if ($line =~ /^## Annotation Source:\s*(.+?)\s*$/) {
      $source = $1;
    }
    next if $line =~ /^#/;
    chomp $line;
    my ($id, $accession, $description, $score) = split /\t/, $line;
    my $group = group_for($id) or next;
    next if exists $override->{$group};
    $override->{$group} = { id => $id, hit => $accession, description => $description // '',
                            score => $score // '-', source => $source };
  }
  close $fh;
}

# ##############################################################################
# closest human gene

sub choose_closest_human {
  my ($group) = @_;
  my @links = @{$human_links{$group} // []};
  return undef unless @links;
  my $best_tier = 99;
  foreach my $link (@links) {
    $best_tier = $link->{tier} if $link->{tier} < $best_tier;
  }
  my @tier_links;
  foreach my $link (@links) {
    push @tier_links, $link if $link->{tier} == $best_tier;
  }
  # tiers 1-2: every co-ortholog; later tiers: the single best-scoring link
  if ($best_tier > 2) {
    my @sorted = sort { ($b->{bits} // 0) <=> ($a->{bits} // 0) } @tier_links;
    @tier_links = ($sorted[0]);
  }
  my (%seen, @humans);
  foreach my $link (@tier_links) {
    foreach my $human (@{$link->{human}}) {
      next if $seen{$human->{key}}++;
      push @humans, $human;
    }
  }
  return { tier => $best_tier, human => \@humans, evidence => $tier_links[0]{evidence},
           type => $tier_links[0]{type}, id => $tier_links[0]{id}, hit => $tier_links[0]{hit} };
}

# ##############################################################################
# names

sub choose_name {
  my ($group) = @_;

  if (my $curated = $override{$group}) {
    $stats{'name: curated override'}++;
    (my $source = $curated->{source}) =~ s/\s+/_/g;
    return { desc => $curated->{description}, selected => selected_id($group, $curated->{id}),
             note => "$source|Curated|$curated->{id}|$curated->{hit}|$curated->{score}" };
  }

  my $closest = $closest{$group};
  if ($closest and $closest->{tier} <= 2) {
    my $named = ortholog_name($group, $closest);
    if ($named) {
      $stats{"name: OMA tier $closest->{tier} ($closest->{type})"}++;
      return $named;
    }
  }

  my $best = best_hit($group);
  if ($best) {
    return hit_name($group, $best);
  }

  if (my $family = $panther{$group}) {
    $stats{'name: PANTHER family'}++;
    my $description = family_member($family->{description});
    return { desc => $description, selected => selected_id($group, $family->{id}),
             note => "PANTHER|Gene_Families|$family->{id}|$family->{family}|$family->{evalue}" };
  }

  $stats{'name: none'}++;
  return { desc => 'None', selected => selected_id($group, undef), note => 'none|none|none|none|-' };
}

sub ortholog_name {
  my ($group, $closest) = @_;
  my @humans = @{$closest->{human}};
  my $source = $closest->{tier} == 1 ? 'OMA_pairwise_HUMAN' : 'OMA_HOG_HUMAN';
  my $note_tail = strip_suffixes($closest->{id}) . "|$closest->{hit}|$closest->{type}";
  my $selected = selected_id($group, $closest->{id});

  if (@humans == 1) {
    my $human = $humans[0];
    return undef unless is_informative_hit($human->{symbol}, $human->{name}, $human->{key});
    my $symbol = $human->{hgnc_id} ? $human->{symbol} : '';
    my $description = $human->{name};
    # many:1 -- several genes here share this human gene
    my @copies = sort { ortholog_rank($a, $human) <=> ortholog_rank($b, $human) or $a cmp $b }
                 keys %{$claimed_human{$human->{key}} // {}};
    if (@copies > 1) {
      my $position = 1;
      foreach my $copy (@copies) {
        last if $copy eq $group;
        $position++;
      }
      $description .= " ($position of " . scalar(@copies) . ")";
    }
    return { desc => ($symbol ne '' ? "$symbol: $description" : $description), selected => $selected,
             note => "$source|Orthologs|$note_tail" };
  }

  # 1:many -- a family; name it after the most specific HGNC gene group they all share
  # (fewest members, so "Integrin alpha subunits" rather than "CD molecules")
  my %groups_seen;
  foreach my $human (@humans) {
    my %own_groups;
    foreach my $gene_group (split /\|/, $human->{gene_group}) {
      $own_groups{$gene_group} = 1 if $gene_group ne '';
    }
    foreach my $gene_group (keys %own_groups) {
      $groups_seen{$gene_group}++;
    }
  }
  my @shared;
  foreach my $gene_group (keys %groups_seen) {
    push @shared, $gene_group if $groups_seen{$gene_group} == scalar @humans;
  }
  @shared = sort { hgnc_group_size($a) <=> hgnc_group_size($b) or $a cmp $b } @shared;
  my @symbols;
  foreach my $human (@humans) {
    push @symbols, $human->{symbol} if $human->{hgnc_id};
  }
  @symbols = sort @symbols;
  my $description;
  if (@shared) {
    $description = family_member($shared[0]);
  } elsif (@symbols) {
    $description = family_member(join('/', @symbols[0 .. ($#symbols < 2 ? $#symbols : 2)]));
  } else {
    return undef;
  }
  my $symbol = @symbols && @symbols <= 3 ? join('/', @symbols) : '';
  return { desc => ($symbol ne '' ? "$symbol: $description" : $description), selected => $selected,
           note => "$source|Orthologs|$note_tail" };
}

# "Integrin alpha subunits" -> "Integrin alpha subunits family member";
# "Tubulin beta family" -> "Tubulin beta family member"
sub family_member {
  my ($family) = @_;
  return $family =~ /family$/i ? "$family member" : "$family family member";
}

my %group_size_cache;
sub hgnc_group_size {
  my ($gene_group) = @_;
  if (!%group_size_cache) {
    foreach my $record (values %{$hgnc->{by_id}}) {
      foreach my $name (split /\|/, $record->{gene_group}) {
        $group_size_cache{$name}++ if $name ne '';
      }
    }
  }
  return $group_size_cache{$gene_group} // 1e9;
}

# lower = earlier copy: best bitscore to that human gene, from any hit of the group
sub ortholog_rank {
  my ($group, $human) = @_;
  my $best = 0;
  foreach my $hit (@{$hits{$group} // []}) {
    next unless $hit->{human} and $hit->{human}{key} eq $human->{key};
    $best = $hit->{bits} if ($hit->{bits} // 0) > $best;
  }
  return -$best;
}

# informative hits; the best by bitscore (hits without a bitscore rank by E-value after);
# a non-human hit must be strong to win
sub best_hit {
  my ($group) = @_;
  my $has_human_hit = 0;
  foreach my $hit (@{$hits{$group} // []}) {
    $has_human_hit = 1 if $hit->{human};
  }
  my @candidates;
  foreach my $hit (@{$hits{$group} // []}) {
    my ($symbol, $description) = hit_label($hit);
    next unless is_informative_hit($symbol, $description, $hit->{hit});
    next if !$hit->{human} and $has_human_hit and !passes($hit, \%STRONG);
    push @candidates, $hit;
  }
  return undef unless @candidates;
  my @sorted = sort {
    (defined $b->{bits} <=> defined $a->{bits})
      or (($b->{bits} // 0) <=> ($a->{bits} // 0))
      or ($a->{evalue} <=> $b->{evalue})
      or ((defined $b->{human}) <=> (defined $a->{human}))
  } @candidates;
  return $sorted[0];
}

sub hit_label {
  my ($hit) = @_;
  if ($hit->{human} and $hit->{human}{hgnc_id}) {
    return ($hit->{human}{symbol}, $hit->{human}{name});
  }
  return ($hit->{symbol} // '', $hit->{description} // '');
}

sub hit_name {
  my ($group, $hit) = @_;
  my ($symbol, $description) = hit_label($hit);
  my $selected = selected_id($group, $hit->{id});
  my $score = $hit->{evalue};
  my $note = "$hit->{source}|$hit->{type}|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$score";

  if ($hit->{human} and $hit->{reciprocal} and !exists $claimed_human{$hit->{human}{key}}) {
    $stats{'name: human reciprocal best hit'}++;
    $symbol = '' unless $hit->{human}{hgnc_id};
    return { desc => ($symbol ne '' ? "$symbol: $description" : $description), selected => $selected, note => $note };
  }

  # everything else is similarity: "-like", and a placeholder symbol is replaced by the
  # closest human symbol when there is one
  if (is_placeholder_symbol($symbol)) {
    my $closest = $closest{$group};
    $symbol = ($closest and @{$closest->{human}} == 1 and $closest->{human}[0]{hgnc_id})
            ? $closest->{human}[0]{symbol} : '';
  }
  if ($description eq '' or !is_informative_hit('', $description, $hit->{hit})) {
    $description = $symbol;
  }
  $stats{'name: ' . ($hit->{human} ? 'human' : 'other species') . ' hit (-like)'}++;
  my $like_symbol = $symbol ne '' ? add_like_to_symbol($symbol) : '';
  my $like_description = add_like_to_description($description);
  # a name from another species says which: "acrosin-like (turkey)"
  if (!$hit->{human} and defined $hit->{species} and $hit->{species} ne '') {
    $like_description .= " ($hit->{species})";
  }
  return { desc => ($like_symbol ne '' ? "$like_symbol: $like_description" : $like_description),
           selected => $selected, note => $note };
}

sub selected_id {
  my ($group, $evidence_id) = @_;
  return $curated_selected{$group} if exists $curated_selected{$group};
  if (defined $evidence_id) {
    foreach my $member (@{$members{$group}}) {
      return $member if $member eq $evidence_id or $member eq strip_suffixes($evidence_id);
    }
  }
  return $members{$group}[0];
}

# ##############################################################################
# output

sub closest_columns {
  my ($group) = @_;
  my $closest = defined $group ? $closest{$group} : undef;
  return ('', '', '', '') unless $closest;
  my (@ids, @symbols, @names);
  foreach my $human (@{$closest->{human}}) {
    push @ids, $human->{hgnc_id} ne '' ? $human->{hgnc_id} : $human->{key};
    push @symbols, $human->{symbol};
    (my $name = $human->{name}) =~ s/,/%2C/g;   # commas separate multiple human genes
    push @names, $name;
  }
  return (join(',', @ids), join(',', @symbols), join(',', @names), $closest->{evidence});
}

sub write_outputs {
  open my $names_fh, '>', $opt{'out-names'} or die "cant write $opt{'out-names'} $!\n";
  print $names_fh join("\t", qw(ID MAINID GroupId Desc Note closestHGNC closestHumanSym closestHumanDesc closestHumanEvidence)), "\n";

  if (defined $opt{native}) {
    write_native_names($names_fh);
  } else {
    foreach my $group (sort keys %members) {
      my $named = $name{$group};
      foreach my $id (sort @{$members{$group}}) {
        my $main = $id eq $named->{selected} ? 'SELF' : $named->{selected};
        print $names_fh join("\t", $id, $main, $group, $named->{desc}, $named->{note}, closest_columns($group)), "\n";
      }
    }
  }
  close $names_fh;

  open my $moop_fh, '>', $opt{'out-moop'} or die "cant write $opt{'out-moop'} $!\n";
  my $version = reference_versions();
  print $moop_fh "## Annotation Source: Closest human gene (SBGENOMES)
## Annotation Source Version: $version
## Annotation Source URL: https://www.genenames.org
## Annotation Accession URL: https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/
## Annotation Type: Closest Human Gene
## Annotation Creation Date: " . `date '+%Y-%m-%d'`;
  print $moop_fh join("\t", '## Gene', 'Accession', 'Accession_Description', 'Score'), "\n";
  foreach my $group (sort keys %members) {
    my $closest = $closest{$group} or next;
    $stats{"closest human: tier $closest->{tier}"}++;
    # the gene and every isoform carry the same final pick
    my %features = ($group => 1);
    foreach my $member (@{$members{$group}}) {
      $features{$member} = 1;
    }
    foreach my $feature (sort keys %features) {
      foreach my $human (@{$closest->{human}}) {
        my $accession = $human->{hgnc_id} ne '' ? $human->{hgnc_id} : $human->{key};
        my $label = $human->{symbol} ne '' && $human->{name} ne '' ? "$human->{symbol}: $human->{name}" : ($human->{name} || $human->{symbol});
        print $moop_fh join("\t", $feature, $accession, "$label [$closest->{evidence}]", $closest->{tier}), "\n";
      }
    }
  }
  close $moop_fh;
}

# native RefSeq/Ensembl names are kept as provided unless uninformative
sub write_native_names {
  my ($names_fh) = @_;
  my (%native_rows, %native_group_informative);
  open my $fh, '<', $opt{native} or die "cant open $opt{native} $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    next if $line =~ /^ID\t/;
    my ($id, $main, $native_group, $desc, $note) = split /\t/, $line;
    push @{$native_rows{$native_group}}, [$id, $main, $native_group, $desc // '', $note // ''];
    if ($main eq 'SELF') {
      my ($symbol, $description) = split_symbol($desc // '');
      $native_group_informative{$native_group} = is_informative_hit($symbol, $description, $id);
    }
  }
  close $fh;
  foreach my $native_group (sort keys %native_rows) {
    my $group;
    foreach my $row (@{$native_rows{$native_group}}) {
      $group = group_for($row->[0]);
      last if defined $group;
    }
    my $keep_native = $native_group_informative{$native_group} // 1;
    $stats{$keep_native ? 'native name kept' : 'native name replaced (uninformative)'}++;
    foreach my $row (@{$native_rows{$native_group}}) {
      my ($id, $main, $native_group_id, $desc, $note) = @$row;
      if (!$keep_native and defined $group) {
        ($desc, $note) = ($name{$group}{desc}, $name{$group}{note});
      }
      print $names_fh join("\t", $id, $main, $native_group_id, $desc, $note, closest_columns($group)), "\n";
    }
  }
}

sub reference_versions {
  my @parts;
  foreach my $file ("$opt{'hgnc-dir'}/VERSION.txt") {
    if (open my $fh, '<', $file) {
      my $line = <$fh> // '';
      close $fh;
      my ($date) = $line =~ /(\d{4}-\d{2}-\d{2})/;
      push @parts, "HGNC $date" if $date;
    }
  }
  if (defined $opt{'oma-dir'} and open my $readme_fh, '<', "$opt{'oma-dir'}/README.exportedAllAll") {
    while (my $line = <$readme_fh>) {
      push @parts, "OMA $1" if $line =~ /^OMA template:\s*(\S+)/;
    }
    close $readme_fh;
  }
  push @parts, 'Ensembl Compara ' . join('/', sort keys %compara_releases_used) if %compara_releases_used;
  if (defined $opt{'uniprot-dir'} and open my $uniprot_fh, '<', "$opt{'uniprot-dir'}/VERSION.txt") {
    my $line = <$uniprot_fh> // '';
    close $uniprot_fh;
    push @parts, "UniProt $1" if $line =~ /UniProt release ([0-9_]+)/;
  }
  return @parts ? join('; ', @parts) : 'unknown';
}
