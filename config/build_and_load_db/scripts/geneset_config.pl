#!/usr/bin/perl
use strict;
use warnings;
use CPAN::Meta::YAML;

# geneset_config.pl CONFIG.yaml ORGANISM ASSEMBLY GENESET
# geneset_config.pl --check CONFIG.yaml
#
# Validates the WHOLE config (geneset_config.yaml; format in its header). With a gene set,
# prints its assign_gene_names_v2.pl options NUL-separated, for
#   mapfile -d '' -t ARGS < file
# and nothing for a gene set that is not listed.
#
# Problems in the gene set being built (or in the organism/assembly above it) stop that
# build. Problems anywhere else are printed as WARNINGs and do not stop it -- one stale
# entry must not block every organism -- but they are printed on EVERY build, so a
# misspelled gene set name (a curation silently never applied) stays visible in the logs.
# --check prints every problem and exits 1 if there is any: run it after editing the file.
# A file that is not valid YAML stops every build: nothing in it can be trusted.
# Gene sets are checked against $GENOMES/<organism>/<assembly>/<geneset>.

# Layout: fixed tables and shared state at file level, the work in main(), called on the last
# line -- so every file-level assignment has run before any work starts (a file-level "my %X =
# (...)" below the work would still be EMPTY when a sub reads it; tests/check_perl_file_scope.pl).
my %SETTINGS = map { my $setting = $_; ($setting => 1) } qw(human_curated_gene_names closest_species transcript_hits);
my %CLOSEST = map { my $key = $_; ($key => 1) } qw(species tag label oma_code hits diamond rbh use_for_names same_species);
# closest_species diamond: / rbh: -- the OUT_DIR of scripts/closest_species_diamond.sh / closest_species_rbh.sh
my %SEARCH_RESULTS = (diamond => ['diamond_results.tsv.gz', 'diamond_results.tsv'], rbh => ['rbh_mmseq_results.tsv']);

# every message starts with the entry it is about: "Org", "Org/Asm" or "Org/Asm/GeneSet"
my @errors;

sub main {
  my $check_only = @ARGV && $ARGV[0] eq '--check' ? shift @ARGV : 0;
  my ($config_file, $want_org, $want_asm, $want_gs) = @ARGV;
  die "Usage: $0 CONFIG.yaml ORGANISM ASSEMBLY GENESET\n       $0 --check CONFIG.yaml\n"
    unless defined $config_file and ($check_only or defined $want_gs);
  my $genomes = $ENV{GENOMES} or die "GENOMES is not set (source scripts/paths.sh)\n";

  open my $fh, '<', $config_file or die "cant open $config_file: $!\n";
  my $text = do { local $/; <$fh> };
  close $fh;
  my $docs = eval { CPAN::Meta::YAML->read_string($text) }
    or die "ERROR: $config_file: not valid YAML: " . ($@ || CPAN::Meta::YAML->errstr) . "\n";
  my $config = $docs->[0] // {};
  die "ERROR: $config_file: top level must be organism names\n" unless ref $config eq 'HASH';


  my @args;
  foreach my $org (sort keys %$config) {
    my $assemblies = $config->{$org};
    ref $assemblies eq 'HASH' or push(@errors, "$org: expected assemblies under it"), next;
    foreach my $asm (sort keys %$assemblies) {
      my $genesets = $assemblies->{$asm};
      ref $genesets eq 'HASH' or push(@errors, "$org/$asm: expected gene sets under it"), next;
      foreach my $gs (sort keys %$genesets) {
        my $where = "$org/$asm/$gs";
        push @errors, "$where: no such gene set ($genomes/$where)" unless -d "$genomes/$where";
        my $settings = $genesets->{$gs};
        ref $settings eq 'HASH' or push(@errors, "$where: expected settings under it"), next;
        my @these = check_geneset($where, $settings);
        @args = @these if !$check_only and $org eq $want_org and $asm eq $want_asm and $gs eq $want_gs;
      }
    }
  }

  if ($check_only) {
    print map { my $error = $_; "PROBLEM: $error\n" } @errors;
    print @errors ? scalar(@errors) . " problem(s) in $config_file\n" : "$config_file: OK\n";
    exit(@errors ? 1 : 0);
  }
  my $target = "$want_org/$want_asm/$want_gs";
  my (@fatal, @warnings);
  foreach my $error (@errors) {
    my ($entry) = $error =~ /^([^:\s]+)/;
    if ($target eq $entry or index($target, "$entry/") == 0) {
      push @fatal, $error;
    } else {
      push @warnings, $error;
    }
  }
  warn map { my $warning = $_; "WARNING: $config_file (not this gene set): $warning\n" } @warnings if @warnings;   # warn() with nothing prints "something's wrong"
  die map { my $error = $_; "ERROR: $config_file: $error\n" } @fatal if @fatal;
  print join("\0", @args), (@args ? "\0" : '');
}

# validates one gene set's settings; returns its options
sub check_geneset {
  my ($where, $settings) = @_;
  my @options;
  foreach my $key (sort keys %$settings) {
    push @errors, "$where: unknown setting '$key' (known: " . join(', ', sort keys %SETTINGS) . ")"
      unless $SETTINGS{$key};
  }
  # transcript_hits: the gene set's proteins searched against the species' own transcriptome ORFs
  # (DIAMOND/BLAST tabular) -- whether a gene with no name is expressed
  if (defined(my $transcript_hits = $settings->{transcript_hits})) {
    push @options, '--transcript-hits', $transcript_hits if file_ok("$where transcript_hits", $transcript_hits);
  }
  if (defined(my $curated = $settings->{human_curated_gene_names})) {
    if (file_ok("$where human_curated_gene_names", $curated)) {
      push @options, '--human-curated-gene-names', $curated;
    }
  }
  if (defined(my $list = $settings->{closest_species})) {
    if (ref $list ne 'ARRAY') {
      push @errors, "$where: closest_species is a list of '- species: ... tag: ...'";
    } else {
      my (%tags, $naming);
      foreach my $entry (@$list) {
        if (ref $entry ne 'HASH') {
          push @errors, "$where: closest_species: each entry needs species, tag, ...";
          next;
        }
        my $what = "$where closest_species " . ($entry->{tag} // '?');
        foreach my $key (sort keys %$entry) {
          push @errors, "$what: unknown key '$key' (known: " . join(', ', sort keys %CLOSEST) . ")" unless $CLOSEST{$key};
        }
        my %flag;
        foreach my $key (qw(use_for_names same_species)) {
          my $value = $entry->{$key} // 'false';
          push @errors, "$what: $key must be true or false" unless $value =~ /^(?:true|false)$/;
          $flag{$key} = $value eq 'true' ? 1 : 0;
        }
        push @errors, "$what: needs species" unless defined $entry->{species} and $entry->{species} ne '';
        push @errors, "$what: tag must be letters/digits, starting with a letter, not Human"
          unless defined $entry->{tag} and $entry->{tag} =~ /^[A-Za-z][A-Za-z0-9]*$/ and lc $entry->{tag} ne 'human';
        push @errors, "$where closest_species: tag $entry->{tag} used twice (tags are case-insensitive)"
          if defined $entry->{tag} and $tags{lc $entry->{tag}}++;
        push @errors, "$what: needs oma_code, hits, diamond or rbh"
          unless grep { my $key = $_; defined $entry->{$key} } qw(oma_code hits diamond rbh);
        push @errors, "$what: needs a label (or same_species: true)" unless $flag{same_species} or defined $entry->{label};
        push @errors, "$where: only one closest_species may have use_for_names: true" if $flag{use_for_names} and $naming++;
        foreach my $key (qw(species tag label oma_code)) {
          push @errors, "$what: $key may not contain | or =" if defined $entry->{$key} and $entry->{$key} =~ /[|=]/;
        }
        my $hits_ok = !defined $entry->{hits} || hits_file_ok("$what hits", $entry->{hits});
        push @errors, "$what: hits path may not contain |" if defined $entry->{hits} and !ref $entry->{hits} and $entry->{hits} =~ /\|/;
        my @fields = map { my $key = $_; "$key=" . ($entry->{$key} // '') } qw(species tag label oma_code);
        my %search_ok;
        foreach my $search (qw(diamond rbh)) {
          next unless defined $entry->{$search};
          $search_ok{$search} = search_dir_ok("$what $search", $entry->{$search}, $SEARCH_RESULTS{$search});
          push @errors, "$what: $search path may not contain |" if $search_ok{$search} and $entry->{$search} =~ /\|/;
        }
        push @fields, 'hits=' . ($hits_ok ? $entry->{hits} // '' : ''),
                      (map { my $search = $_; "$search=" . ($search_ok{$search} ? $entry->{$search} : '') } qw(diamond rbh)),
                      "use_for_names=$flag{use_for_names}", "same_species=$flag{same_species}";
        push @options, '--closest-species', join('|', @fields);
      }
    }
  }
  return @options;
}

# a moop hits file (closest_species hits): 4 tab-separated columns, E-value last. Catches the likely mistake -- a
# raw DIAMOND/BLAST table, whose 4 columns (qseqid sseqid stitle evalue) look the same but
# whose third is the subject's whole FASTA title, starting with its id.
sub hits_file_ok {
  my ($what, $file) = @_;
  return 0 unless file_ok($what, $file);
  open my $fh, '<', $file or return 0;
  my ($lines, $raw, $short, $bad_score) = (0, 0, 0, 0);
  while (my $line = <$fh>) {
    next if $line =~ /^#/ or $line !~ /\S/;
    chomp $line;
    last if ++$lines > 1000;
    my ($id, $accession, $description, $score) = split /\t/, $line;
    if ($lines == 1 and $id eq 'qseqid') { $raw = 1; last }
    if (!defined $score) { $short++; next }
    $raw++ if $accession ne '' and index($description, $accession) == 0;
    $bad_score++ unless $score =~ /^[0-9.eE+-]+$/;
  }
  close $fh;
  $lines = 1000 if $lines > 1000;
  if ($raw and $raw >= $lines / 2) {
    push @errors, "$what: $file looks like a raw DIAMOND/BLAST table (column 3 is the hit's FASTA title); convert it to moop TSV first (see the format notes in geneset_config.yaml)";
  } elsif ($short) {
    push @errors, "$what: $file: $short of the first $lines lines have fewer than 4 tab-separated columns (moop TSV: id accession description evalue)";
  } elsif ($bad_score > $lines / 2) {
    push @errors, "$what: $file: column 4 is not an E-value in most lines (moop TSV: id accession description evalue)";
  }
  return !($raw and $raw >= $lines / 2) && !$short && $bad_score <= $lines / 2;
}

# a closest_species diamond: / rbh: path: the OUT_DIR a closest_species_*.sh script wrote, with its
# results file and db_version.txt (the partner's label, used in the evidence text)
sub search_dir_ok {
  my ($what, $dir, $results) = @_;
  if (ref $dir or !-d $dir) {
    push @errors, "$what: not a directory " . (ref $dir ? '(not a single path)' : $dir);
    return 0;
  }
  my ($found) = grep { my $file = $_; -s "$dir/$file" } @$results;
  push @errors, "$what: $dir has no " . join(' or ', @$results) . " (run scripts/closest_species_*.sh into it)" unless $found;
  push @errors, "$what: $dir has no db_version.txt (written by scripts/closest_species_*.sh)" unless -s "$dir/db_version.txt";
  return $found && -s "$dir/db_version.txt" ? 1 : 0;
}

sub file_ok {
  my ($what, $file) = @_;
  if (ref $file or !-s $file) {
    push @errors, "$what: missing or empty file " . (ref $file ? '(not a single path)' : $file);
    return 0;
  }
  return 1;
}

main();
