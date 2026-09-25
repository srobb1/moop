#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;
use FindBin;
use lib "$FindBin::Bin";
use GeneNamingV2 qw(clean_name split_symbol is_placeholder_symbol is_informative_hit
                    add_like_to_description add_like_to_symbol load_hgnc hgnc_record);
use OmaHogOrthologs qw(read_hog_orthologs parse_oma_header read_id_map target_ids);

# Gene naming v2: a name for every gene and, separately, its closest human gene.
# Design: notes/NAMING_V2_PLAN.md.
#
#   assign_gene_names_v2.pl --isoforms isoforms.tsv --protein-fasta protein.aa.fa \
#       --hgnc-dir moop/hgnc [--oma-dir OMA_v2/<org>/<asm>/<gs> --oma-code CODE] \
#       [--mmseqs-dir <analysis>/rbh_mmseq] [--diamond-dir <analysis>/diamond] [--ref-db REF_DB] \
#       [--compara-dir moop/ensembl_compara] [--uniprot-dir moop/uniprot] \
#       [--taxonomy-dir moop/ncbi_taxonomy] [--panther PANTHER.iprscan.moop.tsv] \
#       [--native native_geneNames.tsv] [--human-curated-gene-names curated.moop.tsv ...] \
#       [--closest-species 'tag=Nvec|species=Nematostella vectensis|label=sea anemone|oma_code=NEMVE|hits=FILE|use_for_names=0|same_species=0' ...] \
#       --out-names geneNames.tsv --out-dir DIR
#
# Closest human gene, strongest first (the tier is the Score of the moop table):
#   1 OMA pairwise ortholog to HUMAN
#   2 OMA HOG co-ortholog with HUMAN (only when parameters.drw has a fixed SpeciesTree)
#   3 MMseqs2 reciprocal best hit to Ensembl human (NORMAL filter)
#   4 via another species: OMA ortholog in a reference species -> its OMA HUMAN ortholog, or
#     MMseqs2 reciprocal best hit -> Ensembl Compara human ortholog (same Ensembl release)
#   5 DIAMOND best hit to a human protein (Ensembl human or a Swiss-Prot HUMAN entry, NORMAL)
#   6 DIAMOND Swiss-Prot hit in another species -> its Ensembl gene -> Ensembl Compara
#   7 DIAMOND Swiss-Prot hit in another species -> its PANTHER subfamily -> the human
#     Swiss-Prot genes in that subfamily
# Similarity hits must report coverage (NORMAL: E <= 1e-10, both coverages >= 50%); DIAMOND
# output without coverage columns is not used. Several human genes (a 1:many or many:many
# family), or tier 7 (a PANTHER subfamily), are reported as ONE family entry, never as a
# member picked by score. Notes/GENE_NAMING_METHODS.md has the full method.
#
# Closest gene in another species (--closest-species, from geneset_config.yaml): OMA ortholog to
# oma_code (1:1, many:1, 1:many, many:many, in that order; several genes = a family), else the
# best hit in its hits file.
#
# Names -- a plain name only from an orthology call, "-like" for full-length similarity, a
# family name when the evidence stops at the family, else no name:
#   1 human-curated name, as given (never checked)
#   2 native name if informative; or the closest-species entry with use_for_names (its OMA 1:1
#     or many:1 ortholog, else its hits file; "-like (label)" unless same_species)
#   3 OMA human ortholog (tier 1-2): 1:1 "SYM: name"; many:1 "SYM: name (k of n)"; a family
#     "<HGNC group> family member", or -- no shared group -- straight to step 5
#   4 best full-length human hit (FULL: both coverages >= 80%), reciprocal or not: "SYM-like:
#     name-like"; symbol only from HGNC. Other species never name a gene.
#   5 PANTHER family: "<family> family member"
#   6 None
#
# Output geneNames.tsv: ID MAINID GroupId Desc Note (one row per id in isoforms.tsv or, with
# --native, per id in the native file). In --out-dir, per species (human always, then
# each --closest-species; <tag> lowercased): closest_<tag>.tsv, the same rows as geneNames.tsv
# with ID GroupId and four columns whose header names ARE the GFF attributes
# (addClosestToGFF.pl): closestHGNC closestHumanSym closestHumanDesc closestHumanEvidence, or
# closest<Tag>Id closest<Tag>Sym closest<Tag>Desc closest<Tag>Evidence; and
# closest_<tag>[.ensembl|.family].moop.tsv, annotation type "Closest Gene", a row for the gene
# and every isoform. And gene_name_source.<kind>.moop.tsv, annotation type "Gene Name Source":
# for every named gene, what its name came from (accession), the name and the rule
# (description), and the naming step (score) -- one file per kind of accession link.

# Per-gene-set inputs come from geneset_config.yaml through scripts/geneset_config.pl.
#
# LAYOUT -- constants and shared state at file level, ALL the work in main(), called on the
# LAST line of this file. A file-level "my %X = (...)" is assigned only when execution reaches
# its line; with the work at the top of the file, a table defined further down was still EMPTY
# when used, and Perl says nothing (no error under strict, no warning). That silently broke
# this script three times on 2026-09-25 (OMA pair ranks, the Closest Gene type, InterProScan
# E-value analyses). With main() last, every file-level assignment has run before any work
# starts, wherever it sits. tests/check_perl_file_scope.pl fails CI if the trap comes back.

# ---- filters (percent; see the plan)
# A hit must report coverage to be used at all: an E-value alone says two proteins share
# something (often one domain), not that they are the same kind of protein.
#   NORMAL -- evidence for the closest human gene
#   FULL   -- a name: the whole of both proteins aligns
my %NORMAL = (evalue => 1e-10, qcov => 50, tcov => 50);
my %FULL   = (evalue => 1e-10, qcov => 80, tcov => 80);

# ---- closest gene in a --closest-species species
my %OMA_RANK = ('1:1' => 0, 'many:1' => 1, '1:many' => 2, 'many:many' => 3);
# the one database annotation type for every closest-gene table; the sources tell them apart
my $CLOSEST_TYPE = 'Closest Gene';
# InterProScan member databases whose score column is an E-value (the others report a match
# score, or nothing) -- used to pick a gene's best InterPro domain
my %EVALUE_ANALYSIS = map { $_ => 1 } qw(Pfam SMART CDD NCBIfam PRINTS PIRSF SFLD Gene3D SUPERFAMILY FunFam);
# Gene Name Source: why each gene has its name. One database source (and file) per kind of
# accession, because a source has one accession link.
my $NAME_SOURCE_TYPE = 'Gene Name Source';
my %NAME_SOURCE = (
  hgnc       => ['Gene name source: HGNC gene', 'https://www.genenames.org',
                 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/'],
  hgnc_group => ['Gene name source: HGNC gene group', 'https://www.genenames.org',
                 'https://www.genenames.org/data/genegroup/#!/group/'],
  ensembl    => ['Gene name source: Ensembl gene', 'https://www.ensembl.org',
                 'https://www.ensembl.org/Homo_sapiens/Gene/Summary?g='],
  panther    => ['Gene name source: PANTHER family', 'https://www.ebi.ac.uk/interpro/',
                 'https://www.ebi.ac.uk/interpro/entry/panther/'],
  ncbi       => ['Gene name source: naming species', 'https://www.ncbi.nlm.nih.gov',
                 'https://www.ncbi.nlm.nih.gov/search/all/?term='],
  curated    => ['Gene name source: human-curated', '', ''],
  native     => ["Gene name source: the gene set's own name", '', ''],
  nolink     => ['Gene name source: human gene without an id', '', ''],
  interpro   => ['Gene name source: InterPro domain', 'https://www.ebi.ac.uk/interpro/',
                 'https://www.ebi.ac.uk/interpro/entry/InterPro/'],
);
# closest human: OMA pair types, and Ensembl Compara's for the via-another-species links
my %LINK_TYPE_RANK = (%OMA_RANK, one2one => 0, one2many => 2, many2many => 3);

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

# ---- shared state: declared here, filled by main() (declarations only -- see LAYOUT above)
my %opt;
my (@closest_species, $naming_species);
my %stats;
my (%group_of, %members, %curated_selected);   # isoform groups
my (%gene_of_protein, %query_length);
my $hgnc;
my %human_links;      # group -> [ link ]   link = {tier, human => [records], type, evidence, bits, id, hit}
my %hits;             # group -> [ naming candidates from similarity ]
my @pending_compara;  # links through another species' Ensembl gene, resolved in one Compara pass
my %reference_fasta_cache;
my (%panther, %domain, %curated);
my %closest;          # group -> { tier, human => [records], evidence, id }
my %claimed_human;    # human key -> { group => 1 }: the co-orthologs a many:1 name is shared by
my %name;             # group -> { desc, note, selected, origin }

# ============================================================== main
sub main {
  %opt = ('human-curated-gene-names' => []);
  GetOptions(\%opt, 'isoforms=s', 'protein-fasta=s', 'protein2gene=s', 'hgnc-dir=s',
             'oma-dir=s', 'oma-code=s', 'mmseqs-dir=s', 'diamond-dir=s', 'ref-db=s',
             'compara-dir=s', 'uniprot-dir=s', 'taxonomy-dir=s', 'panther=s', 'native=s', 'oma-id-map=s',
             'human-curated-gene-names=s@',
             'closest-species=s@', 'interproscan=s', 'interpro-entries=s', 'out-names=s', 'out-dir=s')
    or die "bad options\n";
  foreach my $required (qw(isoforms protein-fasta hgnc-dir out-names out-dir)) {
    die "--$required is required\n" unless defined $opt{$required};
  }

  # --closest-species: one species each, key=value fields joined by |
  foreach my $spec (@{$opt{'closest-species'} // []}) {
    my %species;
    foreach my $field (split /\|/, $spec) {
      my ($key, $value) = split /=/, $field, 2;
      $species{$key} = $value;
    }
    foreach my $key (qw(tag species)) {
      die "--closest-species needs $key: $spec\n" unless defined $species{$key} and $species{$key} ne '';
    }
    die "--closest-species tag must be letters/digits, not 'human': $spec\n"
      if $species{tag} !~ /^[A-Za-z][A-Za-z0-9]*$/ or lc $species{tag} eq 'human';
    die "--closest-species needs oma_code or hits: $spec\n" unless $species{oma_code} or $species{hits};
    $species{$_} = $species{$_} ? 1 : 0 foreach qw(use_for_names same_species);
    die "--closest-species needs a label unless same_species: $spec\n" unless $species{same_species} or $species{label};
    push @closest_species, \%species;
  }
  my @naming_species = grep { $_->{use_for_names} } @closest_species;
  die "only one --closest-species may have use_for_names\n" if @naming_species > 1;
  $naming_species = $naming_species[0];

  # ============================================================== genes and ids
  read_isoforms($opt{isoforms});
  %gene_of_protein = read_protein2gene($opt{protein2gene});
  %query_length = fasta_lengths($opt{'protein-fasta'});

  $hgnc = load_hgnc("$opt{'hgnc-dir'}/hgnc_complete_set.txt", "$opt{'hgnc-dir'}/withdrawn.txt");

  # ============================================================== evidence
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
  collect_closest_species($_) foreach @closest_species;
  resolve_compara();
  name_species();
  %panther = defined $opt{panther} ? read_panther($opt{panther}) : ();
  die "--interproscan needs --interpro-entries (update_reference_data.sh downloads it)\n"
    if defined $opt{interproscan} and !defined $opt{'interpro-entries'};
  %domain = defined $opt{interproscan} ? read_interpro_domains($opt{interproscan}, $opt{'interpro-entries'}) : ();
  foreach my $curated_file (@{$opt{'human-curated-gene-names'}}) {
    read_curated($curated_file, \%curated);
  }

  # ============================================================== decide

  foreach my $group (keys %members) {
    $closest{$group} = choose_closest_human($group);
  }

  # many:1 co-orthologs: genes whose closest human is that ONE gene by OMA (tier 1-2). Counted
  # from the final picks, so a copy that ended up in a family is not counted.
  foreach my $group (keys %closest) {
    my $closest = $closest{$group} or next;
    next if $closest->{family} or $closest->{tier} > 2;
    $claimed_human{$closest->{human}[0]{key}}{$group} = 1;
  }

  foreach my $group (keys %members) {
    $name{$group} = choose_name($group);
  }

  # ============================================================== write
  write_outputs();
  foreach my $key (sort keys %stats) {
    warn sprintf("%-40s %d\n", $key, $stats{$key});
  }
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
  # Ensembl states the HGNC gene in its description ("... [Source:HGNC Symbol;Acc:HGNC:9455]"),
  # also for genes on alternate haplotypes and patches, whose own ENSG id HGNC does not list
  # (ENSG00000274382 is PROP1 on HSCHR5_3_CTG5). Without this they had no HGNC record at all.
  if (!$keys{hgnc_id} and ($keys{description} // '') =~ /\bAcc:(HGNC:\d+)/) {
    $keys{hgnc_id} = $1;
  }
  my $record = hgnc_record($hgnc, %keys);
  if ($record) {
    return { key => $record->{hgnc_id}, hgnc_id => $record->{hgnc_id}, symbol => $record->{symbol},
             name => $record->{name}, gene_group => $record->{gene_group}, gene_group_id => $record->{gene_group_id} };
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

# OMA ids of the target -> (gene set protein id, group) pairs. Normally the OMA id is the gene
# set's own id; for a gene set that IS a reference genome (--oma-id-map, run through the
# template's reference run) it is the reference's id (NEMVE000123), mapped by identical sequence.
my $oma_id_map;
sub own_ids_and_groups {
  my ($oma_target_id) = @_;
  $oma_id_map = read_id_map($opt{'oma-id-map'}) if defined $opt{'oma-id-map'} and !$oma_id_map;
  my @found;
  foreach my $own_id (target_ids($oma_id_map, $oma_target_id)) {
    my $group = group_for($own_id);
    push @found, [$own_id, $group] if defined $group;
  }
  return @found;
}

sub collect_oma {
  my $output = "$opt{'oma-dir'}/Output";
  my $code = $opt{'oma-code'} or die "--oma-code is required with --oma-dir\n";
  die "no $output/PairwiseOrthologs\n" unless -d "$output/PairwiseOrthologs";

  # tier 1
  my $direct = read_oma_pairs($output, $code, 'HUMAN');
  foreach my $oma_target_id (keys %$direct) {
    foreach my $own (own_ids_and_groups($oma_target_id)) {
      my ($target_id, $group) = @$own;
      foreach my $pair (@{$direct->{$oma_target_id}}) {
        my $human = human_from_oma_header($pair->{partner_header}) or next;
        add_link($group, tier => 1, human => [$human], type => $pair->{type}, id => $target_id,
                 evidence => "OMA ortholog ($pair->{type})", hit => $pair->{partner_id});
      }
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
      foreach my $own (own_ids_and_groups($hogs->{genes}{$target_gene}{prot_id})) {
        my ($target_id, $group) = @$own;
        foreach my $human_gene (keys %{$human_pairs->{$target_gene}}) {
          my $human = human_from_oma_header($hogs->{genes}{$human_gene}{header}) or next;
          my $type = $hogs->{type}{HUMAN}{$target_gene}{$human_gene};
          add_link($group, tier => 2, human => [$human], type => $type, id => $target_id,
                   evidence => "OMA HOG co-ortholog ($type)", hit => $hogs->{genes}{$human_gene}{prot_id});
        }
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
    foreach my $oma_target_id (keys %$to_reference) {
      foreach my $own (own_ids_and_groups($oma_target_id)) {
        my ($target_id, $group) = @$own;
        foreach my $first (@{$to_reference->{$oma_target_id}}) {
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
        my $human = human_record(hgnc_id => $info->{hgnc_id}, ensembl_gene => $info->{gene}, description => $info->{description}) or next;
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
                                 tier => 4, id => $query, hit => $target, bits => $bits, evalue => $evalue,
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
      my ($hgnc_id) = $line =~ /\bAcc:(HGNC:\d+)/;   # read before clean_name drops the [Source:...] part
      $proteins{$id} = { gene => $gene // $id, symbol => $symbol // '', description => clean_name($desc // ''),
                         hgnc_id => $hgnc_id // '', length => 0 };
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
                 id => $pending->{id}, bits => $pending->{bits}, evalue => $pending->{evalue}, hit => $pending->{hit},
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
    my ($accession, $taxid, $gene_name, $hgnc_ids, $genes, $proteins, $panther, $secondary) = split /\t/, $line, -1;
    if ($taxid eq '9606') {
      my ($hgnc_id) = split /;/, $hgnc_ids;
      foreach my $family (split /;/, $panther) {
        $human_in_subfamily{$family}{$hgnc_id} = 1 if $family =~ /:SF/ and defined $hgnc_id and $hgnc_id ne '';
      }
    }
    # a hit may carry an older accession since merged into this entry
    my @hit_accessions;
    foreach my $candidate ($accession, split /;/, $secondary // '') {
      push @hit_accessions, $candidate if $wanted{$candidate};
    }
    next unless @hit_accessions;
    my @subfamilies;
    foreach my $family (split /;/, $panther) {
      push @subfamilies, $family if $family =~ /:SF/;
    }
    foreach my $hit_accession (@hit_accessions) {
      $xref{$hit_accession} = { taxid => $taxid, genes => [ split /;/, $genes ], subfamilies => \@subfamilies };
    }
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
                                 id => $hit->{id}, hit => $hit->{hit}, bits => $hit->{bits}, evalue => $hit->{evalue}, via => "via $what" };
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
                 bits => $hit->{bits}, evalue => $hit->{evalue}, hit => $hit->{hit},
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
                 bits => $hit{bits}, evalue => $hit{evalue}, evidence => "best BLAST hit ($candidate->{label})", hit => $subject);
      }
    }
    close $fh;
    $stats{"note: DIAMOND $db has no coverage columns: NOT USED (needs the 17-column output)"} = 1 unless $has_coverage;
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

# E-value and both coverages; a hit that does not report coverage fails
sub passes {
  my ($hit, $filter) = @_;
  return 0 unless defined $hit->{evalue} and $hit->{evalue} <= $filter->{evalue};
  foreach my $measure (qw(qcov tcov)) {
    return 0 unless defined $hit->{$measure} and $hit->{$measure} >= $filter->{$measure};
  }
  return 1;
}

# ##############################################################################
# PANTHER and human-curated names (moop TSV: id accession description score)

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

# InterProScan TSV (the gene set's own results) + InterPro's entry.list: per gene, the best match
# to an InterPro Domain or Repeat entry, leaving out "unknown function" entries (DUF, UPF,
# uncharacterised). Best = lowest E-value among member databases that report one (Pfam, SMART,
# CDD, ...), then entries without an E-value (PROSITE profiles report a score), then accession.
sub read_interpro_domains {
  my ($results, $entries) = @_;
  my %entry;
  open my $entries_fh, '<', $entries or die "cant open $entries $!\n";
  while (my $line = <$entries_fh>) {
    chomp $line;
    my ($accession, $type, $name) = split /\t/, $line;
    next unless defined $name and ($type eq 'Domain' or $type eq 'Repeat');
    next if $name =~ /unknown function|\bDUF\d|\bUPF\d|uncharacteri[sz]ed/i;
    $entry{$accession} = { type => $type, name => $name };
  }
  close $entries_fh;
  my %best;
  my $open = $results =~ /\.gz$/ ? "gzip -dc '$results' |" : "< $results";
  open my $fh, $open or die "cant read $results\n";
  while (my $line = <$fh>) {
    chomp $line;
    my @fields = split /\t/, $line;
    my ($id, $analysis, $signature, $score, $interpro) = @fields[0, 3, 4, 8, 11];
    next unless defined $interpro and exists $entry{$interpro};
    my $group = group_for($id) or next;
    my $evalue = $EVALUE_ANALYSIS{$analysis} && defined $score && $score =~ /^[0-9.eE+-]+$/ ? $score : undef;
    my $candidate = { id => $id, entry => $interpro, %{$entry{$interpro}}, analysis => $analysis,
                      signature => $signature // '', evalue => $evalue };
    my $current = $best{$group};
    if (!$current or better_domain($candidate, $current)) {
      $best{$group} = $candidate;
    }
  }
  close $fh;
  return %best;
}

sub better_domain {
  my ($new, $old) = @_;
  return 1 if defined $new->{evalue} and !defined $old->{evalue};
  return 0 if !defined $new->{evalue} and defined $old->{evalue};
  if (defined $new->{evalue} and $new->{evalue} != $old->{evalue}) {
    return $new->{evalue} < $old->{evalue} ? 1 : 0;
  }
  return ($new->{entry} cmp $old->{entry} or $new->{id} cmp $old->{id}) < 0 ? 1 : 0;
}

sub read_curated {
  my ($file, $curated) = @_;
  open my $fh, '<', $file or die "cant open human-curated names $file $!\n";
  my $source = $file;
  while (my $line = <$fh>) {
    if ($line =~ /^## Annotation Source:\s*(.+?)\s*$/) {
      $source = $1;
    }
    next if $line =~ /^#/;
    chomp $line;
    my ($id, $accession, $description, $score) = split /\t/, $line;
    my $group = group_for($id) or next;
    next if exists $curated->{$group};
    $curated->{$group} = { id => $id, hit => $accession, description => $description // '',
                            score => $score // '-', source => $source };
  }
  close $fh;
}

# ##############################################################################
# closest human gene

# human gene key -> best bitscore of this gene's similarity hits (MMseqs2 RBH, DIAMOND) to it
sub human_support {
  my ($group) = @_;
  my %support;
  foreach my $hit (@{$hits{$group} // []}) {
    next unless $hit->{human} and defined $hit->{bits};
    my $key = $hit->{human}{key};
    $support{$key} = $hit->{bits} if !defined $support{$key} or $hit->{bits} > $support{$key};
  }
  return \%support;
}

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
  my $support = human_support($group);
  @tier_links = order_links($best_tier, $support, @tier_links);
  # tiers 1-2: every co-ortholog, in that order; later tiers: the single best link
  @tier_links = ($tier_links[0]) if $best_tier > 2;
  my (%seen, @humans);
  foreach my $link (@tier_links) {
    foreach my $human (@{$link->{human}}) {
      next if $seen{$human->{key}}++;
      push @humans, $human;
    }
  }
  my $closest = { tier => $best_tier, human => \@humans, evidence => $tier_links[0]{evidence},
                  type => $tier_links[0]{type}, id => $tier_links[0]{id}, hit => $tier_links[0]{hit} };
  # One human gene: that gene. Several (a 1:many or many:many family), or a PANTHER
  # subfamily (tier 7, family-level evidence even with one human member): the family, never
  # one member picked by score -- a gene that predates a duplication is equally related to
  # every copy, and the best-scoring copy is only the slowest-evolving one.
  return $closest if @humans == 1 and $best_tier != 7;
  my $size = @humans;
  $stats{'closest human: family'}++;
  return { %$closest, family_size => $size, family => 1,
           evidence => "$closest->{evidence}, family of $size" };
}

# Order links so the result never depends on hash order (the links arrive in whatever order
# the OMA/hit files were walked through hashes). Tiers 1-2 are OMA and carry no score: the pair
# type first (1:1 > many:1 > 1:many > many:many; Compara's one2one > one2many > many2many
# likewise), then how strongly this gene's own MMseqs2/DIAMOND hits agree (best bitscore to
# that same human gene). Later tiers: their own bitscore first, then the same two. Then a human
# gene with an HGNC record over one without (it has a symbol). Ids last, only for exact ties.
sub order_links {
  my ($tier, $support, @links) = @_;
  my %agreement;
  foreach my $link (@links) {
    my $best = 0;
    foreach my $human (@{$link->{human}}) {
      my $bits = $support->{$human->{key}} // 0;
      $best = $bits if $bits > $best;
    }
    $agreement{$link} = $best;
  }
  my $by_type      = sub { ($LINK_TYPE_RANK{$_[0]{type} // ''} // 9) <=> ($LINK_TYPE_RANK{$_[1]{type} // ''} // 9) };
  my $by_agreement = sub { $agreement{$_[1]} <=> $agreement{$_[0]} };
  my $has_hgnc     = sub { ($_[0]{human}[0]{hgnc_id} // '') ne '' ? 1 : 0 };
  my $by_hgnc      = sub { $has_hgnc->($_[1]) <=> $has_hgnc->($_[0]) };
  my $by_ids       = sub { (($_[0]{human}[0]{key} // '') cmp ($_[1]{human}[0]{key} // ''))
                             or (($_[0]{id} // '') cmp ($_[1]{id} // ''))
                             or (($_[0]{hit} // '') cmp ($_[1]{hit} // '')) };
  if ($tier <= 2) {
    return sort { $by_type->($a, $b) or $by_agreement->($a, $b) or $by_hgnc->($a, $b) or $by_ids->($a, $b) } @links;
  }
  # E-value next: DIAMOND without its coverage columns reports no bitscore, and ranking those
  # hits by id instead picked a closest gene that disagreed with the E-value-ranked name
  return sort { (($b->{bits} // 0) <=> ($a->{bits} // 0)) or (($a->{evalue} // 1e9) <=> ($b->{evalue} // 1e9))
                or $by_agreement->($a, $b)
                or $by_type->($a, $b) or $by_hgnc->($a, $b) or $by_ids->($a, $b) } @links;
}

# the smallest HGNC gene group every member shares ("Integrin alpha subunits" rather than
# "CD molecules"), or nothing
sub shared_hgnc_group {
  my ($humans) = @_;
  my %groups_seen;
  foreach my $human (@$humans) {
    my %own_groups;
    foreach my $gene_group (split /\|/, $human->{gene_group} // '') {
      $own_groups{$gene_group} = 1 if $gene_group ne '';
    }
    $groups_seen{$_}++ foreach keys %own_groups;
  }
  my @shared = grep { $groups_seen{$_} == scalar @$humans } keys %groups_seen;
  @shared = sort { hgnc_group_size($a) <=> hgnc_group_size($b) or $a cmp $b } @shared;
  return $shared[0];
}

# a family as ONE closest entry: symbol column "<HGNC group> family" when the
# members share one, else "A/B-family" for up to 3 members -- "(N genes)" added when some
# members have no symbol, so "SCYGR2-family" never reads as one gene -- else "family of N genes"
sub family_entry {
  my ($size, $symbols, $descriptions, $group_name) = @_;
  my @symbols = grep { $_ ne '' } @$symbols;
  my $label = defined $group_name ? ($group_name =~ /family$/i ? $group_name : "$group_name family")
            : (@symbols and $size <= 3) ? join('/', @symbols) . '-family' . (@symbols < $size ? " ($size genes)" : '')
            : "family of $size genes";
  my %seen;
  my @descriptions = grep { $_ ne '' and !$seen{$_}++ } @$descriptions;
  my $description = join(' / ', @descriptions[0 .. ($#descriptions < 2 ? $#descriptions : 2)]);
  $description .= " ... ($size genes)" if $size > 3;
  return { id => '', symbol => $label, description => $description };
}

my (%species_oma, %species_hit);   # tag -> group -> [ OMA pairs ] / best hit in the hits file
my %species_hit_source;            # tag -> the hits file's Annotation Source, else its file name

# a name from the naming species: plain for another annotation of this same species,
# otherwise "-like (label)", as for any other species' hit
sub naming_species_text {
  my ($symbol, $description) = @_;
  return ($symbol ne '' ? "$symbol: $description" : $description) if $naming_species->{same_species};
  my $like_symbol = $symbol ne '' ? add_like_to_symbol($symbol) : '';
  my $like_description = add_like_to_description($description ne '' ? $description : $symbol)
                       . " ($naming_species->{label})";
  return $like_symbol ne '' ? "$like_symbol: $like_description" : $like_description;
}

# naming step 3: the use_for_names species' OMA 1:1 / many:1 ortholog, else its hits file
sub naming_species_name {
  my ($group) = @_;
  return undef unless $naming_species;
  my $tag = $naming_species->{tag};
  my $code = $naming_species->{oma_code} // '';
  foreach my $candidate (@{$species_oma{$tag}{$group} // []}) {
    next unless $candidate->{type} eq '1:1' or $candidate->{type} eq 'many:1';
    my $parsed = $candidate->{parsed};
    my $symbol = is_placeholder_symbol($parsed->{gene_id}) ? '' : $parsed->{gene_id};
    my $description = clean_name($parsed->{description});
    next unless is_informative_hit($symbol, $description, $candidate->{hit});
    $stats{"name: $tag (OMA $candidate->{type})"}++;
    my $partner = $parsed->{protein_ids}[0] // $candidate->{hit};
    my $rule = $naming_species->{same_species}
      ? "Same gene in another annotation of this species ($naming_species->{species} $partner; OMA, $candidate->{type})"
      : "Ortholog of $naming_species->{species} $partner (OMA, $candidate->{type}); named after it, marked -like";
    return { origin => { kind => 'ncbi', accession => $partner, step => 2, rule => $rule },
             desc => naming_species_text($symbol, $description),
             selected => selected_id($group, $candidate->{id}),
             note => "OMA_pairwise_$code|Orthologs|" . strip_suffixes($candidate->{id}) . "|$candidate->{hit}|$candidate->{type}" };
  }
  if (my $hit = $species_hit{$tag}{$group}) {
    my ($symbol, $description) = split_symbol($hit->{description});
    $symbol = '' if is_placeholder_symbol($symbol);
    $description = clean_name($description);
    if (is_informative_hit($symbol, $description, $hit->{hit})) {
      $stats{"name: $tag hits file"}++;
      my $type = $naming_species->{same_species} ? 'Same_species' : 'Naming_species';
      my $rule = $naming_species->{same_species}
        ? "Same gene in another annotation of this species ($naming_species->{species} $hit->{hit}; best hit in $hit->{source}, E=" . e_value($hit->{evalue}) . ")"
        : "Best hit $naming_species->{species} $hit->{hit} ($hit->{source}, E=" . e_value($hit->{evalue}) . "); named after it, marked -like";
      return { origin => { kind => 'ncbi', accession => $hit->{hit}, step => 2, rule => $rule },
               desc => naming_species_text($symbol, $description),
               selected => selected_id($group, $hit->{id}),
               note => "$hit->{source}|$type|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$hit->{score}" };
    }
  }
  return undef;
}

# closest gene in a --closest-species species, informative or not, by the same rule as the
# closest human gene: the best-ranked OMA relationship, a family of several genes reported as
# the family; no OMA relationship at all: the best hit in the hits file
sub choose_closest_species {
  my ($species, $group) = @_;
  my $tag = $species->{tag};
  my $hit = $species_hit{$tag}{$group};
  my @pairs = @{$species_oma{$tag}{$group} // []};
  if (@pairs) {
    my $type = $pairs[0]{type};
    my (%seen, @genes);
    foreach my $pair (@pairs) {
      last if $pair->{type} ne $type;
      next if $seen{$pair->{hit}}++;
      my $parsed = $pair->{parsed};
      push @genes, { id => $parsed->{protein_ids}[0] // $pair->{hit}, oma_id => $pair->{hit},
                     symbol => $parsed->{gene_id} // '', description => clean_name($parsed->{description} // '') };
    }
    return { rank => 1, genes => \@genes, evidence => "OMA ortholog ($type)" } if @genes == 1;

    # a family: reported as the family (see choose_closest_human)
    my $size = @genes;
    $stats{"closest $tag: family"}++;
    return { rank => 1, genes => [ family_entry($size, [map { $_->{symbol} } @genes], [map { $_->{description} } @genes]) ],
             evidence => "OMA ortholog ($type), family of $size" };
  }
  if ($hit) {
    my ($symbol, $description) = split_symbol($hit->{description});
    return { rank => 2, genes => [ { id => $hit->{hit}, symbol => $symbol // '', description => clean_name($description // '') } ],
             evidence => "hits file ($hit->{source})" };
  }
  return undef;
}

# OMA pairs with oma_code (from the same --oma-dir run), best rank first; the hits file
# (moop TSV: id accession "SYMBOL: description" score), best E-value per gene
sub collect_closest_species {
  my ($species) = @_;
  my $tag = $species->{tag};
  if ($species->{oma_code} and defined $opt{'oma-dir'} and defined $opt{'oma-code'}) {
    my $pairs = read_oma_pairs("$opt{'oma-dir'}/Output", $opt{'oma-code'}, $species->{oma_code});
    foreach my $oma_target_id (keys %$pairs) {
      foreach my $own (own_ids_and_groups($oma_target_id)) {
        my ($target_id, $group) = @$own;
        foreach my $pair (@{$pairs->{$oma_target_id}}) {
          next unless exists $OMA_RANK{$pair->{type}};
          push @{$species_oma{$tag}{$group}}, { id => $target_id, hit => $pair->{partner_id}, type => $pair->{type},
                                                parsed => parse_oma_header($pair->{partner_header}) };
        }
      }
    }
    foreach my $group (keys %{$species_oma{$tag} // {}}) {
      @{$species_oma{$tag}{$group}} = sort { $OMA_RANK{$a->{type}} <=> $OMA_RANK{$b->{type}} or $a->{hit} cmp $b->{hit} }
                                      @{$species_oma{$tag}{$group}};
    }
  }
  if ($species->{hits}) {
    open my $fh, '<', $species->{hits} or die "cant open $species->{hits} $!\n";
    my $source = $species->{hits};
    while (my $line = <$fh>) {
      if ($line =~ /^## Annotation Source:\s*(.+?)\s*$/) {
        ($source = $1) =~ s/\s+/_/g;
      }
      next if $line =~ /^#/;
      chomp $line;
      my ($id, $accession, $description, $score) = split /\t/, $line;
      my $group = group_for($id) or next;
      my $evalue = ($score // '') =~ /^[0-9.eE+-]+$/ ? $score : 1;
      if (!exists $species_hit{$tag}{$group} or $evalue < $species_hit{$tag}{$group}{evalue}) {
        $species_hit{$tag}{$group} = { id => $id, hit => $accession, description => $description // '',
                                       score => $score // '-', evalue => $evalue, source => $source };
      }
    }
    close $fh;
    ($species_hit_source{$tag} = $source) =~ s{.*/}{};
  }
}

sub choose_name {
  my ($group) = @_;

  # human-curated names (a person named these genes, e.g. Chamaeleo's Apollo file) are kept
  # as is: deliberately NOT subject to is_informative_hit, unlike every step below
  if (my $curated = $curated{$group}) {
    $stats{'name: human-curated'}++;
    (my $source = $curated->{source}) =~ s/\s+/_/g;
    return { desc => $curated->{description}, selected => selected_id($group, $curated->{id}),
             note => "$source|Curated|$curated->{id}|$curated->{hit}|$curated->{score}",
             origin => { kind => 'curated', accession => $curated->{hit}, step => 1, rule => "Named by a curator ($curated->{source})" } };
  }

  # the closest-species entry with use_for_names (e.g. NV2's own RefSeq annotation, or
  # Nematostella for a coral once reviewed) -- when informative
  if (my $named = naming_species_name($group)) {
    return $named;
  }

  # OMA orthology to human. A family OMA could not narrow is not handed to step 4 either:
  # the best BLAST hit would just be picking one member by score again.
  my $closest = $closest{$group};
  my $oma_family = 0;
  if ($closest and $closest->{tier} <= 2) {
    my $named = ortholog_name($group, $closest);
    if ($named) {
      $stats{"name: OMA " . ($closest->{family} ? 'family' : $closest->{type})}++;
      return $named;
    }
    $oma_family = $closest->{family} ? 1 : 0;
  }

  if (!$oma_family and my $best = best_hit($group)) {
    return hit_name($group, $best);
  }

  my $family = $panther{$group};
  if ($family and is_informative_hit('', $family->{description}, $family->{family})) {
    $stats{'name: PANTHER family'}++;
    my $description = family_member($family->{description});
    return { desc => $description, selected => selected_id($group, $family->{id}),
             note => "PANTHER|Gene_Families|$family->{id}|$family->{family}|$family->{evalue}",
             origin => { kind => 'panther', accession => $family->{family}, step => 5, rule => "Member of PANTHER family $family->{family} (InterProScan, E=" . e_value($family->{evalue}) . ")" } };
  }

  # the gene's best InterPro domain or repeat: "X domain-containing protein" (UniProt's
  # convention for a protein known only by a domain) -- claims the domain, not a gene identity
  if (my $domain = $domain{$group}) {
    my $name = $domain->{name};
    $name =~ s/,\s+/ /g;   # "Zinc finger, RING-type" -> "Zinc finger RING-type"; keeps "1,2-lyase"
    $name =~ s/\s+/ /g;
    my $description = $name =~ /(?:domain|repeats?)(?:\s+\d+)?$/i ? "$name-containing protein" : "$name domain-containing protein";
    if (is_informative_hit('', $name, $domain->{entry})) {
      $stats{"name: InterPro $domain->{type}"}++;
      my $signature = $domain->{analysis} . ($domain->{signature} ne '' ? " $domain->{signature}" : '')
                    . (defined $domain->{evalue} ? ", E=" . e_value($domain->{evalue}) : '');
      return { desc => $description, selected => selected_id($group, $domain->{id}),
               note => "InterPro|Domains|$domain->{id}|$domain->{entry}|" . ($domain->{evalue} // '-'),
               origin => { kind => 'interpro', accession => $domain->{entry}, step => 6,
                           rule => "Contains InterPro " . lc($domain->{type}) . " $domain->{entry} \"$domain->{name}\" ($signature); no homolog or family evidence" } };
    }
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

  if (!$closest->{family}) {
    my $human = $humans[0];
    return undef unless is_informative_hit($human->{symbol}, $human->{name}, $human->{key});
    my $symbol = $human->{hgnc_id} ? $human->{symbol} : '';
    my $description = $human->{name};
    # many:1 (a duplication in this lineage): every copy is an ortholog of the human gene and
    # carries its name; how many copies share it is provenance, not part of the name
    my $copies = scalar keys %{$claimed_human{$human->{key}} // {}};
    my $rule = ($closest->{tier} == 1 ? 'Ortholog' : 'Co-ortholog') . " of human " . human_label($human)
             . " (" . ($closest->{tier} == 1 ? 'OMA' : 'OMA HOG') . ", $closest->{type})"
             . ($copies > 1 ? ": one of $copies copies in this genome" : '');
    return { desc => ($symbol ne '' ? "$symbol: $description" : $description), selected => $selected,
             note => "$source|Orthologs|$note_tail", origin => human_origin($human, $rule, 3) };
  }

  # a family: named after the most specific HGNC gene group they all share, or not named here
  # at all -- never after a member picked by score or by spelling
  # No symbol: the symbol is what users search as the gene's identity, and a family has none.
  my $shared = shared_hgnc_group(\@humans) or return undef;
  return { desc => family_member($shared), selected => $selected, note => "$source|Orthologs|$note_tail",
           origin => { kind => 'hgnc_group', accession => hgnc_group_id(\@humans, $shared), step => 3,
                       rule => "Co-ortholog of " . scalar(@humans) . " human genes in the HGNC group \"$shared\" ("
                               . ($closest->{tier} == 1 ? 'OMA' : 'OMA HOG') . ", $closest->{type}); no single ortholog" } };
}

# a human gene in provenance text: its HGNC symbol, else its Ensembl id or description
sub human_label {
  my ($human) = @_;
  return $human->{hgnc_id} ne '' ? $human->{symbol} : $human->{key} =~ /^ENSG/ ? $human->{key} : "\"$human->{name}\"";
}

# an E-value as provenance shows it: 2e-95, 0
sub e_value {
  my ($evalue) = @_;
  return '?' unless defined $evalue and $evalue =~ /^[0-9.eE+-]+$/;
  return $evalue == 0 ? '0' : sprintf('%.0e', $evalue);
}

# the provenance of a name taken from one human gene: its HGNC record, else its Ensembl gene
sub human_origin {
  my ($human, $rule, $step) = @_;
  return $human->{hgnc_id} ne '' ? { kind => 'hgnc', accession => $human->{hgnc_id}, rule => $rule, step => $step }
       : $human->{key} =~ /^ENSG/ ? { kind => 'ensembl', accession => $human->{key}, rule => $rule, step => $step }
       : { kind => 'nolink', accession => $human->{name}, rule => $rule, step => $step };
}

# HGNC's id for one of the members' group names (names and ids are parallel "|" lists)
sub hgnc_group_id {
  my ($humans, $group_name) = @_;
  foreach my $human (@$humans) {
    my @names = split /\|/, $human->{gene_group} // '';
    my @ids = split /\|/, $human->{gene_group_id} // '';
    foreach my $index (0 .. $#names) {
      return $ids[$index] if $names[$index] eq $group_name and defined $ids[$index] and $ids[$index] ne '';
    }
  }
  return $group_name;
}

# "Integrin alpha subunits" -> "Integrin alpha subunits family member";
# "Tubulin beta family" -> "Tubulin beta family member"
sub family_member {
  my ($family) = @_;
  # a colon would be read downstream as "SYMBOL: description" (updateGFF.pl takes what precedes
  # the first colon as the symbol): "DUMPY: SHORTER THAN WILD-TYPE" -> "DUMPY - SHORTER ..."
  (my $name = $family) =~ s/\s*:\s*/ - /g;
  $name =~ s/\s+/ /g;   # stray double spaces in PANTHER / HGNC names
  $name =~ s/^ | $//g;
  # "Solute carrier family 5 member", "... superfamily member", else "X family member"
  return $name =~ /family\b/i ? "$name member" : "$name family member";
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

# Naming step 4: the best full-length hit to a HUMAN protein (FULL filter, informative), by
# bitscore then E-value. Hits to other species never name a gene: a transferred name may be a
# lineage-specific paralog ("member 4a" in fish), which we cannot tell and should not copy.
sub best_hit {
  my ($group) = @_;
  my @candidates;
  foreach my $hit (@{$hits{$group} // []}) {
    next unless $hit->{human} and passes($hit, \%FULL);
    next unless is_informative_hit($hit->{human}{symbol}, $hit->{human}{name}, $hit->{hit});
    push @candidates, $hit;
  }
  return undef unless @candidates;
  my @sorted = sort { (($b->{bits} // 0) <=> ($a->{bits} // 0)) or ($a->{evalue} <=> $b->{evalue})
                      or ($a->{hit} cmp $b->{hit}) } @candidates;
  return $sorted[0];
}

# similarity is not orthology, reciprocal or not: always "-like". The symbol is the human
# gene's HGNC symbol, or none -- never borrowed from another gene.
sub hit_name {
  my ($group, $hit) = @_;
  my $human = $hit->{human};
  my $symbol = $human->{hgnc_id} ? $human->{symbol} : '';
  my $description = $human->{name} ne '' ? $human->{name} : $symbol;
  $stats{'name: full-length human hit (-like)' . ($hit->{reciprocal} ? ', reciprocal' : '')}++;
  my $like_symbol = $symbol ne '' ? add_like_to_symbol($symbol) : '';
  my $like_description = add_like_to_description($description);
  my $tool = $hit->{source} =~ /MMseqs2/ ? 'MMseqs2' : 'DIAMOND';
  my $label = human_label($human);
  my $rule = sprintf('Similar to human %s along its length: %s, %.0f%% of this protein and %.0f%% of %s aligned, E=%s (%s)',
                     $label, ($hit->{reciprocal} ? 'reciprocal best hit' : 'best hit'), $hit->{qcov}, $hit->{tcov},
                     $label, e_value($hit->{evalue}), $tool);
  return { desc => ($like_symbol ne '' ? "$like_symbol: $like_description" : $like_description),
           selected => selected_id($group, $hit->{id}),
           note => "$hit->{source}|$hit->{type}|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$hit->{evalue}",
           origin => human_origin($human, $rule, 4) };
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

# every geneNames.tsv row: [ id, main, GroupId column, desc, note, group, origin ]
my @name_rows;

sub write_outputs {
  if (defined $opt{native}) {
    collect_native_rows();
  } else {
    foreach my $group (sort keys %members) {
      my $named = $name{$group};
      foreach my $id (sort @{$members{$group}}) {
        my $main = $id eq $named->{selected} ? 'SELF' : $named->{selected};
        push @name_rows, [$id, $main, $group, $named->{desc}, $named->{note}, $group, $named->{origin}];
      }
    }
  }
  open my $names_fh, '>', $opt{'out-names'} or die "cant write $opt{'out-names'} $!\n";
  print $names_fh join("\t", qw(ID MAINID GroupId Desc Note)), "\n";
  foreach my $row (@name_rows) {
    print $names_fh join("\t", @{$row}[0 .. 4]), "\n";
  }
  close $names_fh;

  # human: its closest pick, as HGNC records
  my %human_closest;
  foreach my $group (keys %closest) {
    my $closest = $closest{$group} or next;
    my @genes = $closest->{family}
      ? (family_entry($closest->{family_size}, [map { $_->{hgnc_id} ? $_->{symbol} : '' } @{$closest->{human}}],
                      [map { $_->{name} } @{$closest->{human}}], shared_hgnc_group($closest->{human})))
      : map { { id => ($_->{hgnc_id} ne '' ? $_->{hgnc_id} : $_->{key} =~ /^ENSG/ ? $_->{key} : ''),
                symbol => ($_->{hgnc_id} ne '' ? $_->{symbol} : ''), description => $_->{name} } } @{$closest->{human}};
    $human_closest{$group} = { rank => $closest->{tier}, genes => \@genes, evidence => $closest->{evidence} };
    $stats{"closest human: tier $closest->{tier}"}++;
  }
  my $version = reference_versions();
  write_name_source($version);
  write_closest('human', [qw(closestHGNC closestHumanSym closestHumanDesc closestHumanEvidence)], \%human_closest, [
    { file => '', source => 'Closest human gene (HGNC)', version => $version, url => 'https://www.genenames.org',
      accession_url => 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/', match => sub { $_[0]{id} =~ /^HGNC:/ } },
    { file => '.ensembl', source => 'Closest human gene (Ensembl, no HGNC record)', version => $version,
      url => 'https://www.ensembl.org', accession_url => 'https://www.ensembl.org/Homo_sapiens/Gene/Summary?g=',
      match => sub { $_[0]{id} ne '' } },
    { file => '.family', source => 'Closest human gene family', version => $version, url => 'https://www.genenames.org',
      accession_url => '', match => sub { 1 } },
  ]);

  foreach my $species (@closest_species) {
    my $tag = $species->{tag};
    my %species_closest;
    foreach my $group (keys %members) {
      my $closest = choose_closest_species($species, $group) or next;
      $species_closest{$group} = $closest;
      $stats{"closest $tag: " . ($closest->{rank} == 1 ? 'OMA' : 'hits file')}++;
    }
    my @sources;
    push @sources, "OMA $opt{'oma-code'}-$species->{oma_code}" if $species->{oma_code} and defined $opt{'oma-code'};
    push @sources, $species_hit_source{$tag} if defined $species_hit_source{$tag};
    my $species_version = join('; ', @sources);
    write_closest(lc $tag, [map { "closest$tag$_" } qw(Id Sym Desc Evidence)], \%species_closest, [
      { file => '', source => "Closest $species->{species} gene", version => $species_version,
        url => 'https://www.ncbi.nlm.nih.gov', accession_url => 'https://www.ncbi.nlm.nih.gov/search/all/?term=',
        match => sub { $_[0]{id} ne '' } },
      { file => '.family', source => "Closest $species->{species} gene family", version => $species_version,
        url => 'https://www.ncbi.nlm.nih.gov', accession_url => '', match => sub { 1 } },
    ]);
  }
}

sub open_closest_moop {
  my ($file, $source, $type) = @_;
  $type //= $CLOSEST_TYPE;
  open my $fh, '>', $file or die "cant write $file $!\n";
  print $fh "## Annotation Source: $source->{source}
## Annotation Source Version: $source->{version}
## Annotation Source URL: $source->{url}
## Annotation Accession URL: $source->{accession_url}
## Annotation Type: $type
## Annotation Creation Date: " . `date '+%Y-%m-%d'`;
  print $fh join("\t", '## Gene', 'Accession', 'Accession_Description', 'Score'), "\n";
  return $fh;
}

# gene_name_source.<kind>.moop.tsv: a row for every named id and its gene. Accession = what the
# name came from; description = why, in words (the name itself is its own column on the site);
# score = the naming step.
sub write_name_source {
  my ($version) = @_;
  my (%fh, %done);
  foreach my $row (@name_rows) {
    my $origin = $row->[6] or next;
    my $kind = $origin->{kind};
    my $meta = $NAME_SOURCE{$kind} or die "unknown name source kind $kind\n";
    my $fh = $fh{$kind} //= open_closest_moop("$opt{'out-dir'}/gene_name_source.$kind.moop.tsv",
      { source => $meta->[0], version => $version, url => $meta->[1], accession_url => $meta->[2] }, $NAME_SOURCE_TYPE);
    foreach my $feature ($row->[0], $row->[2]) {   # the id, and its gene
      next if !defined $feature or $done{$feature}++;
      print $fh join("\t", $feature, $origin->{accession}, $origin->{rule}, $origin->{step}), "\n";
    }
  }
  close $_ foreach values %fh;
}

# closest_<file_tag>.tsv (geneNames.tsv rows, columns named by the GFF attributes) and one
# closest_<file_tag><source file>.moop.tsv per source that has rows (gene and every isoform)
sub write_closest {
  my ($file_tag, $columns, $closest_of, $sources) = @_;
  my $base = "$opt{'out-dir'}/closest_$file_tag";
  open my $fh, '>', "$base.tsv" or die "cant write $base.tsv $!\n";
  print $fh join("\t", 'ID', 'GroupId', @$columns), "\n";
  foreach my $row (@name_rows) {
    my $closest = defined $row->[5] ? $closest_of->{$row->[5]} : undef;
    my (@ids, @symbols, @names);
    foreach my $gene (@{$closest ? $closest->{genes} : []}) {
      push @ids, $gene->{id};
      push @symbols, $gene->{symbol};
      (my $name = $gene->{description}) =~ s/,/%2C/g;   # commas separate multiple genes
      push @names, $name;
    }
    print $fh join("\t", $row->[0], $row->[2], join(',', @ids), join(',', @symbols), join(',', @names),
                   $closest ? $closest->{evidence} : ''), "\n";
  }
  close $fh;

  # one database file per source: each source has one accession link (a family has none),
  # and every source shares the one annotation type, so the site shows and filters them together
  my %moop_fh;
  foreach my $group (sort keys %members) {
    my $closest = $closest_of->{$group} or next;
    # the gene and every isoform carry the same final pick
    my %features = ($group => 1);
    foreach my $member (@{$members{$group}}) {
      $features{$member} = 1;
    }
    foreach my $gene (@{$closest->{genes}}) {
      my ($source) = grep { $_->{match}->($gene) } @$sources;
      my $fh = $moop_fh{$source->{file}} //= open_closest_moop("$base$source->{file}.moop.tsv", $source);
      my $label = $gene->{symbol} ne '' && $gene->{description} ne '' ? "$gene->{symbol}: $gene->{description}"
                : ($gene->{description} || $gene->{symbol});
      foreach my $feature (sort keys %features) {
        print $fh join("\t", $feature, ($gene->{id} ne '' ? $gene->{id} : $gene->{symbol}),
                       "$label [$closest->{evidence}]", $closest->{rank}), "\n";
      }
    }
  }
  close $_ foreach values %moop_fh;
}

# native RefSeq/Ensembl names are kept as provided unless uninformative
sub collect_native_rows {
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
      my $origin = $desc ne '' && $desc ne 'None'
        ? { kind => 'native', accession => $native_group_id, step => 2, rule => "Name from this gene set's own annotation" } : undef;
      if (!$keep_native and defined $group) {
        ($desc, $note, $origin) = ($name{$group}{desc}, $name{$group}{note}, $name{$group}{origin});
      }
      push @name_rows, [$id, $main, $native_group_id, $desc, $note, $group, $origin];
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

# LAST LINE: run only now, when every file-level assignment above has been made (see LAYOUT)
main();
