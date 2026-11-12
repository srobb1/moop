#!/usr/bin/perl
use strict;
use warnings;
use Cwd qw(getcwd abs_path);
use File::Basename qw(basename);
use FindBin qw($RealBin);

# Build an OMA DB/<PREFIX>.fa and DB/<PREFIX>.splice for the target species of an OMA run,
# and set the fixed species tree in parameters.drw.
#
#   cd OMA_v2/<organism>/<genome>/<geneset>      (the OMA run dir, made from a template)
#   make_oma_db_files.pl PREFIX [LINEAGE]
#
# Species tree: the target's lineage is found from ncbi-taxon-id in the datastore metadata.yaml,
# walked up the NCBI taxonomy in $TAXONOMY_DIR to the most specific lineage row in
# species_trees.tsv (next to this script). LINEAGE (a species_trees.tsv lineage name)
# overrides the lookup. The row's newick, with TARGET replaced by PREFIX, is written into the
# single active "SpeciesTree :=" line of parameters.drw. Every other leaf of the tree must have
# a DB/<leaf>.fa, so a tree cannot be applied to the wrong template.
#
# The run dir mirrors the genome datastore, so the inputs are read from
#   $DATASTORE/<organism>/<genome>/<geneset>/protein2gene.txt   protein_id <tab> gene_id
#   $DATASTORE/<organism>/<genome>/<geneset>/protein.aa.fa
# and written to
#     -> DB/PREFIX.fa      cleaned proteins (OMA does not accept * or . in sequences):
#                          a single terminal * or . is stripped; any protein with a * or . left
#                          after that (internal stop/gap) is removed entirely (same rules as
#                          moop-pipeline .../analysis_parsers/clean_protein_fasta.pl)
#                          headers are written as the id only: OMA matches splice ids against the
#                          whole header and crashes (EstablishSpliceMap "range selector out of
#                          bounds") when some headers carry descriptions
#     -> DB/PREFIX.splice  one line per gene with >1 protein, ids ';' separated
#                          not written (and any old one removed) when no gene has >1 protein:
#                          OMA treats an empty .splice as a missing splicing map and every
#                          process of the parallel run rebuilds (and corrupts) the genome cache
#
# Fasta ids are the first word of the header. Only ids present in the fasta (and not removed
# by cleaning) are written to the splice file. Skipped ids go to STDERR.
#
# A gene set that IS one of the template's reference genomes (>= 50% of its proteins identical
# to one reference, e.g. Nematostella RefSeq = NEMVE, Drosophila FlyBase = DROME) must not be run
# as a target: the genome would be in OMA twice. Then nothing is written except
# REFERENCE_GENOME.txt, which tells the moop pipeline and oma_status_check.sh to use the
# template's reference run instead. 10-50% identical (same species, other annotation) is a
# warning only.

# Paths come from oma.conf next to this script (or $OMA_CONF): OMA_V2, OMA_TEMPLATES, GENOMES, TAXONOMY_DIR.
my %conf = read_oma_conf($ENV{OMA_CONF} // "$RealBin/oma.conf");
my $DATASTORE     = $conf{GENOMES};
my $TAXONOMY_DIR  = $conf{TAXONOMY_DIR};
my $SPECIES_TREES = "$RealBin/species_trees.tsv";
my $FIND_REFERENCE = "$RealBin/../build_and_load_db/analysis_parsers/find_reference_genome.pl";
die "cannot find $FIND_REFERENCE\n" unless -e $FIND_REFERENCE;

# oma.conf is bash: let bash read it, and print the settings this script uses
sub read_oma_conf {
  my ($conf_file) = @_;
  die "no settings file $conf_file (copy oma.conf.example to oma.conf)\n" unless -r $conf_file;
  my $script = 'source "$1" >/dev/null || exit 1; OMA_TEMPLATES=${OMA_TEMPLATES:-$OMA_V2/OMA_TEMPLATE_RUNS}; '
             . 'for key in OMA_V2 OMA_TEMPLATES GENOMES TAXONOMY_DIR; do printf "%s\t%s\n" "$key" "${!key}"; done';
  open my $conf_fh, '-|', 'bash', '-c', $script, 'read_oma_conf', $conf_file or die "cannot read $conf_file: $!\n";
  my %settings;
  while (my $line = <$conf_fh>) {
    chomp $line;
    my ($key, $value) = split /\t/, $line, 2;
    $settings{$key} = $value;
  }
  close $conf_fh or die "cannot read $conf_file\n";
  foreach my $key (qw(OMA_V2 OMA_TEMPLATES GENOMES TAXONOMY_DIR)) {
    die "$conf_file does not set $key\n" unless defined $settings{$key} and length $settings{$key};
  }
  return %settings;
}

my $usage = "usage: cd OMA_v2/<organism>/<genome>/<geneset> && $0 PREFIX [LINEAGE]\n";
my $prefix = shift or die $usage;
my $lineage_override = shift;
die $usage if @ARGV;
die "PREFIX should be letters/digits only, e.g. CONKUS: $prefix\n" unless $prefix =~ /^[A-Za-z0-9]+$/;

my $oma_root      = abs_path($conf{OMA_V2});
my $oma_templates = abs_path($conf{OMA_TEMPLATES});
my $run_dir  = abs_path(getcwd());
my $run_rel;
if (index($run_dir, "$oma_root/") == 0) {
  $run_rel = substr($run_dir, length("$oma_root/"));
}
die "run this from an OMA run dir <organism>/<genome>/<geneset> under $oma_root (cwd is $run_dir)\n"
  unless defined $run_rel and scalar(split m{/}, $run_rel) == 3;
die "no DB/ or parameters.drw in $run_dir: copy the template in first\n"
  unless -d "DB" and -e "parameters.drw";

# the prefix must not clash with a reference species shipped in the templates
foreach my $template_fa (glob "$oma_templates/*/OMA.2.7.0/DB/*.fa") {
  die "PREFIX $prefix is a reference species in the templates, pick another\n"
    if basename($template_fa, '.fa') eq $prefix;
}

my $geneset_dir  = "$DATASTORE/$run_rel";
my $protein2gene = "$geneset_dir/protein2gene.txt";
my $fasta        = "$geneset_dir/protein.aa.fa";
foreach my $input_file ($protein2gene, $fasta) {
  die "missing or empty: $input_file\n" unless -s $input_file;
}
my $outprefix = "DB/$prefix";
warn "reading $geneset_dir\n";

# is this gene set one of the reference genomes already in DB/?
check_reference_genome($fasta);

# a genome cache built from old DB files is not safely rebuilt by the parallel oma -s run
my @old_cache = glob "Cache/DB/$prefix.db*";
push @old_cache, "Cache/AllAll/$prefix" if -d "Cache/AllAll/$prefix";
if (@old_cache) {
  warn "WARNING: old cache for $prefix exists; remove it before rerunning oma-part1.sh:\n";
  warn "  rm -r @old_cache\n";
}

# species tree for the target, worked out before anything is written
my ($lineage, $species_tree) = species_tree_for_target($prefix, $lineage_override, "$geneset_dir/metadata.yaml");
warn "lineage: $lineage\nSpeciesTree: $species_tree\n";

# read fasta: keep header order, clean sequences
my (%seq, @order);
my $id;
open FA, $fasta or die "cant open fasta: $fasta $!\n";
while (my $line = <FA>) {
  chomp $line;
  $line =~ s/\r$//;
  if ($line =~ /^>(\S+)/) {
    $id = $1;
    die "duplicate id in fasta: $id\n" if exists $seq{$id};
    $seq{$id} = '';
    push @order, $id;
  } elsif (defined $id) {
    $line =~ s/\s//g;
    $seq{$id} .= $line;
  }
}
close FA;

open OUTFA, ">$outprefix.fa" or die "cant write $outprefix.fa $!\n";
my ($removed, %removed) = (0);
foreach my $pid (@order) {
  $seq{$pid} =~ s/[*.]\z//;        # strip single terminal stop/gap
  if ($seq{$pid} =~ /[*.]/ or $seq{$pid} eq '') {
    warn "internal * or . (or empty), removed from fasta: $pid\n";
    delete $seq{$pid};
    $removed{$pid}++;
    $removed++;
    next;
  }
  print OUTFA ">$pid\n";
  print OUTFA "$1\n" while $seq{$pid} =~ /(.{1,60})/g;
}
close OUTFA;

# group proteins by gene, keeping only proteins that are in the fasta
open P2G, $protein2gene or die "cant open P2G: $protein2gene $!\n";
my (%groups, %missing);
while (my $line = <P2G>) {
  chomp $line;
  $line =~ s/\r$//;
  next if $line =~ /^\s*$/;
  my ($pid, $gid) = split "\t", $line;
  next unless defined $gid and $gid ne '';
  if (exists $seq{$pid}) {
    $groups{$gid}{$pid}++;
  } elsif (!$removed{$pid}) {
    $missing{$pid}++;
  }
}
close P2G;

# only write a splice file when there are isoforms: OMA treats an empty .splice as a missing
# splicing map and rebuilds the genome cache in every process, which corrupts it under -s -n N
my @splice_lines;
foreach my $gene (sort keys %groups) {
  my @transcripts = sort keys %{$groups{$gene}};
  push @splice_lines, join(";", @transcripts) if scalar @transcripts > 1;
}
my $nlines = scalar @splice_lines;
if ($nlines) {
  open SPLICE, ">$outprefix.splice" or die "cant write $outprefix.splice $!\n";
  foreach my $splice_line (@splice_lines) {
    print SPLICE "$splice_line\n";
  }
  close SPLICE;
} elsif (-e "$outprefix.splice") {
  unlink "$outprefix.splice" or die "cant remove stale $outprefix.splice $!\n";
  warn "no isoforms, removed existing $outprefix.splice\n";
}

foreach my $missing_pid (sort keys %missing) {
  warn "not in fasta, skipped: $missing_pid\n";
}
warn sprintf("wrote %s.fa (%d proteins, %d removed for internal * or .) and %s (%d genes); %d protein2gene ids not in fasta\n",
             $outprefix, scalar(keys %seq), $removed,
             ($nlines ? "$outprefix.splice" : "no .splice (no isoforms)"), $nlines, scalar(keys %missing));
die "ERROR: no proteins written to $outprefix.fa\n" unless scalar(keys %seq);

set_species_tree("parameters.drw", $species_tree);
warn "set SpeciesTree in parameters.drw ($lineage)\n";


# lineage + newick for the target: LINEAGE override, else ncbi-taxon-id walked up the taxonomy
sub species_tree_for_target {
  my ($target, $override, $metadata_file) = @_;

  my (%newick_for_lineage, %lineage_for_taxid);
  open my $trees_fh, '<', $SPECIES_TREES or die "cant open $SPECIES_TREES $!\n";
  while (my $line = <$trees_fh>) {
    chomp $line;
    next if $line =~ /^#/ or $line !~ /\S/;
    my ($row_lineage, $taxids, $names, $newick) = split /\t/, $line;
    $newick_for_lineage{$row_lineage} = $newick;
    foreach my $taxid (split /,/, $taxids) {
      $lineage_for_taxid{$taxid} = $row_lineage;
    }
  }
  close $trees_fh;

  my $found_lineage;
  if (defined $override) {
    die "unknown LINEAGE $override; choose one of: " . join(' ', sort keys %newick_for_lineage) . "\n"
      unless exists $newick_for_lineage{$override};
    $found_lineage = $override;
  } else {
    my $taxid = read_taxon_id($metadata_file);
    my @ancestors = ncbi_lineage($taxid);
    foreach my $ancestor (@ancestors) {
      if (exists $lineage_for_taxid{$ancestor}) {
        $found_lineage = $lineage_for_taxid{$ancestor};
        last;
      }
    }
    die "ncbi-taxon-id $taxid is not under any lineage in $SPECIES_TREES; "
      . "add a row or give LINEAGE on the command line\n" unless defined $found_lineage;
  }

  my $newick = $newick_for_lineage{$found_lineage};
  die "species tree for $found_lineage has no TARGET leaf\n" unless $newick =~ /\bTARGET\b/;
  $newick =~ s/\bTARGET\b/$target/;

  # every other leaf must be a genome in this run's DB/
  foreach my $leaf ($newick =~ /([A-Za-z0-9]+)/g) {
    next if $leaf eq $target;
    die "species tree leaf $leaf has no DB/$leaf.fa: wrong template for $SPECIES_TREES?\n"
      unless -e "DB/$leaf.fa";
  }
  return ($found_lineage, $newick);
}

sub read_taxon_id {
  my ($metadata_file) = @_;
  open my $meta_fh, '<', $metadata_file or die "cant open $metadata_file $! (needed for ncbi-taxon-id; or give LINEAGE)\n";
  while (my $line = <$meta_fh>) {
    if ($line =~ /^ncbi-taxon-id:\s*['"]?(\d+)/) {
      close $meta_fh;
      return $1;
    }
  }
  close $meta_fh;
  die "no ncbi-taxon-id in $metadata_file (or give LINEAGE)\n";
}

# taxid, parent, grandparent, ... up to the root (1)
sub ncbi_lineage {
  my ($taxid) = @_;

  open my $merged_fh, '<', "$TAXONOMY_DIR/merged.dmp" or die "cant open $TAXONOMY_DIR/merged.dmp $!\n";
  while (my $line = <$merged_fh>) {
    my ($old_taxid, $new_taxid) = split /\t\|\t/, $line;
    if ($old_taxid eq $taxid) {
      $new_taxid =~ s/\D//g;
      warn "ncbi-taxon-id $taxid was merged into $new_taxid\n";
      $taxid = $new_taxid;
      last;
    }
  }
  close $merged_fh;

  my %parent_of;
  open my $nodes_fh, '<', "$TAXONOMY_DIR/nodes.dmp" or die "cant open $TAXONOMY_DIR/nodes.dmp $!\n";
  while (my $line = <$nodes_fh>) {
    my ($node, $parent) = split /\t\|\t/, $line, 3;
    $parent_of{$node} = $parent;
  }
  close $nodes_fh;
  die "ncbi-taxon-id $taxid is not in $TAXONOMY_DIR/nodes.dmp\n" unless exists $parent_of{$taxid};

  my @lineage = ($taxid);
  my $current = $taxid;
  while ($current ne '1') {
    $current = $parent_of{$current};
    push @lineage, $current;
  }
  return @lineage;
}

# replace the one active SpeciesTree line in place
sub set_species_tree {
  my ($parameters_file, $newick) = @_;
  open my $in_fh, '<', $parameters_file or die "cant open $parameters_file $!\n";
  my @lines = <$in_fh>;
  close $in_fh;

  my $active = 0;
  foreach my $line (@lines) {
    if ($line =~ /^\s*SpeciesTree\s*:=/) {
      $line = "SpeciesTree := '$newick';\n";
      $active++;
    }
  }
  die "expected exactly one active 'SpeciesTree :=' line in $parameters_file, found $active\n"
    unless $active == 1;

  open my $out_fh, '>', "$parameters_file.tmp" or die "cant write $parameters_file.tmp $!\n";
  foreach my $line (@lines) {
    print $out_fh $line;
  }
  close $out_fh or die "cant write $parameters_file.tmp $!\n";
  rename "$parameters_file.tmp", $parameters_file or die "cant replace $parameters_file $!\n";
}

# Stops (after writing REFERENCE_GENOME.txt) when the gene set is a reference genome of this
# template; warns when it shares 10-50% identical proteins with one.
sub check_reference_genome {
  my ($target_fasta) = @_;
  if (!-e $FIND_REFERENCE) {
    warn "WARNING: $FIND_REFERENCE not found; cannot check whether this gene set is a reference genome\n";
    return;
  }
  my @reference_fastas;
  foreach my $reference_fa (glob "DB/*.fa") {
    push @reference_fastas, $reference_fa unless basename($reference_fa) eq "$prefix.fa";
  }
  return unless @reference_fastas;
  # the finder reads a directory of <CODE>.fa; point it at the references only
  my $reference_dir = "Cache/reference_check";
  mkdir 'Cache';
  mkdir $reference_dir;
  foreach my $reference_fa (@reference_fastas) {
    my $link = "$reference_dir/" . basename($reference_fa);
    symlink(abs_path($reference_fa), $link) unless -e $link;
  }
  my @report = `perl '$FIND_REFERENCE' '$reference_dir' '$target_fasta'`;
  my $is_reference = ($? >> 8) == 0;
  foreach my $link (glob "$reference_dir/*.fa") {
    unlink $link;
  }
  rmdir $reference_dir;
  return unless @report;
  chomp @report;
  my ($code, $percent, $identical, $total) = split /\t/, $report[0];

  if ($is_reference) {
    my $template = '';
    if (open my $readme_fh, '<', 'README.exportedAllAll') {
      while (my $line = <$readme_fh>) {
        $template = $1 if $line =~ /^OMA template:\s*(\S+)/;
      }
      close $readme_fh;
    }
    open my $marker_fh, '>', 'REFERENCE_GENOME.txt' or die "cant write REFERENCE_GENOME.txt $!\n";
    print $marker_fh "reference: $code\nidentical: $percent% ($identical of $total proteins)\n"
                   . "template: $template\nchecked: " . `date '+%Y-%m-%d'`;
    close $marker_fh;
    my $reference_run = "$oma_templates/$template/reference_run";
    die "\nSTOP: this gene set IS the reference genome $code of the template: $percent% of its proteins\n"
      . "($identical of $total) are identical to ${code}'s. It must not be run as an OMA target (the genome\n"
      . "would be in OMA twice). Nothing was written except REFERENCE_GENOME.txt.\n\n"
      . "  In THIS directory: run nothing (no oma-part1/2/3). The template files copied here can be\n"
      . "  deleted; keep REFERENCE_GENOME.txt.\n\n"
      . "  Instead, its orthologs come from the template's SHARED reference run, run once for all\n"
      . "  reference genomes:\n"
      . "    $reference_run\n"
      . "  If it has not been run yet: part 1, then part 3 (no part 2), then mapGO/get_OMA_GO_terms.sh $code.\n"
      . "  oma_status_check.sh shows its next step (REFRUN), and this gene set as waiting for it.\n";
  }
  if ($percent >= 10) {
    warn "WARNING: $percent% of this gene set's proteins ($identical of $total) are identical to reference ${code}'s\n"
       . "         (the same species in another annotation?). Continuing as a normal target.\n";
  }
}
