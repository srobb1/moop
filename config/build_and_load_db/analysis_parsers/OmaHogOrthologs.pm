package OmaHogOrthologs;
use strict;
use warnings;
use Exporter 'import';

our @EXPORT_OK = qw(read_hog_orthologs parse_oma_header best_accession accession_for
                    read_export_sources find_export_readme write_ortholog_tables read_hgnc_symbols
                    read_id_map target_ids);

# A gene set that IS a reference genome takes its orthologs from the template's reference run,
# where its genes carry the reference's OMA ids (NEMVE000123). find_reference_genome.pl maps
# those to the gene set's own protein ids by identical sequence:
#   reference_id <TAB> target_protein_id      (one reference id may map to several targets)
# read_id_map(file) -> { reference_id => [ target ids ] }; undef file -> undef (no mapping).
sub read_id_map {
  my ($file) = @_;
  return undef unless defined $file and $file ne '' and $file ne '-';
  my %map;
  open my $fh, '<', $file or die "cant open id map $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    my ($reference_id, $target_id) = split /\t/, $line;
    push @{$map{$reference_id}}, $target_id if defined $target_id and $target_id ne '';
  }
  close $fh;
  return \%map;
}

# the gene set's own ids for a target id seen in OMA output: itself without a map, else the
# mapped ids (none when the reference gene has no identical protein in the gene set)
sub target_ids {
  my ($id_map, $id) = @_;
  return ($id) unless $id_map;
  return @{$id_map->{$id} // []};
}

# Orthologs of one target species implied by OMA's HierarchicalGroups.orthoxml.
#
#   my $result = read_hog_orthologs($orthoxml_file, $target_species);
#
# Two genes are orthologs when their lowest common group in the HOG tree is an
# <orthologGroup> (a speciation); a <paralogGroup> (a duplication) makes them paralogs.
# So at every orthologGroup, each target gene in one child is an ortholog of each gene of
# another species in a different child.
#
# Returns a hashref:
#   genes   => { gene_id => { species => CODE, prot_id => "first word of protId", header => protId } }
#   pairs   => { PARTNER_SPECIES => { target_gene_id => { partner_gene_id => { hog => "HOG:...", level => "/A/B/..." } } } }
#   type    => { PARTNER_SPECIES => { target_gene_id => { partner_gene_id => "1:1" | "1:many" | "many:1" | "many:many" } } }
# "1:many" = one target gene to several partner genes (the OMA pairwise convention).
#
# OMA writes one tag per line; tags are read one at a time, so no XML module is needed.

sub read_hog_orthologs {
  my ($orthoxml_file, $target_species) = @_;

  open my $fh, '<', $orthoxml_file or die "cant open $orthoxml_file $!\n";
  local $/;
  my $xml = <$fh>;
  close $fh;

  my %genes;
  my $current_species;
  my @stack;          # open groups: { kind => 'O'|'P', hog => ..., level => ..., children => [ {species => [gene ids]} ] }
  my %pairs;

  while ($xml =~ /<([^>]+)>/g) {
    my $tag = $1;

    if ($tag =~ /^species\s/) {
      ($current_species) = $tag =~ /\bname="([^"]*)"/;
    } elsif ($tag =~ /^gene\s/) {
      my ($gene_id) = $tag =~ /\bid="([^"]*)"/;
      my ($prot_id) = $tag =~ /\bprotId="([^"]*)"/;
      $prot_id = xml_unescape($prot_id // '');
      my ($first_word) = $prot_id =~ /^(\S+)/;
      $genes{$gene_id} = { species => $current_species, prot_id => $first_word // $prot_id, header => $prot_id };
    } elsif ($tag =~ /^orthologGroup\b/) {
      my ($hog) = $tag =~ /\bog="([^"]*)"/;
      ($hog) = $tag =~ /\bid="([^"]*)"/ unless defined $hog;
      push @stack, { kind => 'O', hog => $hog // '', level => '', children => [] };
    } elsif ($tag =~ /^paralogGroup\b/) {
      push @stack, { kind => 'P', hog => '', level => '', children => [] };
    } elsif ($tag =~ /^property\s/ and @stack) {
      my ($name)  = $tag =~ /\bname="([^"]*)"/;
      my ($value) = $tag =~ /\bvalue="([^"]*)"/;
      $stack[-1]{level} = $value if defined $name and $name eq 'TaxRange';
    } elsif ($tag =~ /^geneRef\s/ and @stack) {
      my ($gene_id) = $tag =~ /\bid="([^"]*)"/;
      my $species = $genes{$gene_id}{species};
      die "geneRef $gene_id has no <gene> entry in $orthoxml_file\n" unless defined $species;
      push @{$stack[-1]{children}}, { $species => [$gene_id] };
    } elsif ($tag =~ m{^/(orthologGroup|paralogGroup)$}) {
      my $node = pop @stack;
      if ($node->{kind} eq 'O') {
        record_orthologs($node, $target_species, \%pairs);
      }
      # the whole subtree becomes one child of the enclosing group
      my %merged;
      foreach my $child (@{$node->{children}}) {
        foreach my $species (keys %$child) {
          push @{$merged{$species}}, @{$child->{$species}};
        }
      }
      push @{$stack[-1]{children}}, \%merged if @stack;
    }
  }
  die "unbalanced groups in $orthoxml_file\n" if @stack;
  my $target_gene_count = 0;
  foreach my $gene_id (keys %genes) {
    $target_gene_count++ if $genes{$gene_id}{species} eq $target_species;
  }
  die "no genes for target species $target_species in $orthoxml_file\n" unless $target_gene_count;

  return { genes => \%genes, pairs => \%pairs, type => relationship_types(\%pairs) };
}

# at one orthologGroup: target genes in one child x other-species genes in another child
sub record_orthologs {
  my ($node, $target_species, $pairs) = @_;
  my @children = @{$node->{children}};
  foreach my $i (0 .. $#children) {
    my $target_genes = $children[$i]{$target_species} or next;
    foreach my $j (0 .. $#children) {
      next if $i == $j;
      foreach my $partner_species (keys %{$children[$j]}) {
        next if $partner_species eq $target_species;
        foreach my $target_gene (@$target_genes) {
          foreach my $partner_gene (@{$children[$j]{$partner_species}}) {
            # the lowest orthologGroup wins: closed groups are visited bottom-up
            next if exists $pairs->{$partner_species}{$target_gene}{$partner_gene};
            $pairs->{$partner_species}{$target_gene}{$partner_gene} = { hog => $node->{hog}, level => $node->{level} };
          }
        }
      }
    }
  }
}

sub relationship_types {
  my ($pairs) = @_;
  my %type;
  foreach my $partner_species (keys %$pairs) {
    my %targets_of_partner;
    foreach my $target_gene (keys %{$pairs->{$partner_species}}) {
      foreach my $partner_gene (keys %{$pairs->{$partner_species}{$target_gene}}) {
        $targets_of_partner{$partner_gene}{$target_gene} = 1;
      }
    }
    foreach my $target_gene (keys %{$pairs->{$partner_species}}) {
      my $n_partners = scalar keys %{$pairs->{$partner_species}{$target_gene}};
      foreach my $partner_gene (keys %{$pairs->{$partner_species}{$target_gene}}) {
        my $n_targets = scalar keys %{$targets_of_partner{$partner_gene}};
        my $left  = $n_targets  > 1 ? 'many' : '1';
        my $right = $n_partners > 1 ? 'many' : '1';
        $type{$partner_species}{$target_gene}{$partner_gene} = "$left:$right";
      }
    }
  }
  return \%type;
}

# OMA export header, fields separated by " | ":
#   CODE000001 | protein/transcript ids | gene id | [UniProt accession; entry name] | [HOG:...] | description
# The UniProt field is missing entirely when a gene has none, the HOG field may be empty, and
# some descriptions contain "|" themselves (JGI: "jgi|Lotgi1|51275|gw1.4.3.1"), so fields are
# found by content, not only by position.
# Returns { oma_id, protein_ids => [...], gene_id, uniprot => [...], description, hgnc_id }
my $UNIPROT_ACCESSION = qr/^(?:[OPQ][0-9][A-Z0-9]{3}[0-9]|[A-NR-Z][0-9](?:[A-Z][A-Z0-9]{2}[0-9]){1,2})(?:-\d+)?$/;

sub parse_oma_header {
  my ($header) = @_;
  my @fields;
  foreach my $field (split /\|/, $header // '', -1) {
    $field =~ s/^\s+|\s+$//g;
    push @fields, $field;
  }
  my %parsed = (oma_id => $fields[0] // '', protein_ids => [], gene_id => '', uniprot => [], description => '', hgnc_id => '');
  return \%parsed if @fields < 3;
  $parsed{protein_ids} = [ split_trimmed($fields[1], qr/;/) ];
  $parsed{gene_id} = $fields[2];

  # fields 3.. : optional UniProt field, optional/empty HOG field, then the description
  my $next = 3;
  if (defined $fields[$next] and $fields[$next] ne '' and $fields[$next] !~ /^HOG:/) {
    my @accessions;
    my $all_uniprot = 1;
    foreach my $piece (split_trimmed($fields[$next], qr/;/)) {
      if ($piece =~ $UNIPROT_ACCESSION) {
        push @accessions, $piece;
      } elsif ($piece !~ /^[A-Z0-9]+_[A-Z0-9]+$/) {   # entry names (Q3ZCM7 / TBB8_HUMAN) are fine
        $all_uniprot = 0;
      }
    }
    if ($all_uniprot) {
      $parsed{uniprot} = \@accessions;
      $next++;
    }
  }
  $next++ if defined $fields[$next] and ($fields[$next] eq '' or $fields[$next] =~ /^HOG:/);

  my $description = join(' | ', @fields[$next .. $#fields]);
  # "description; transcript_id=..." or, with no description, just "transcript_id=..."
  $description =~ s/(?:^|;)\s*transcript_id=.*$//;
  ($parsed{hgnc_id}) = $description =~ /Acc:(HGNC:\d+)/;
  $parsed{hgnc_id} //= '';
  $description =~ s/\s*\[Source:[^\]]*\]//;
  $description = '' if $description =~ /^jgi \| /;   # a JGI model id, not a description
  $description =~ s/^\s+|\s+$//g;
  $parsed{description} = $description;
  return \%parsed;
}

# The id a partner gene is shown with, and which database it links to. Chosen from the ids the
# gene carries, so each species gets the database its annotation came from:
#   Ensembl protein (ENS...P) > FlyBase protein (FBpp) > RefSeq protein (XP_/NP_) > UniProt
#   accession > the OMA export id.
# Returns (namespace, accession).
sub accession_for {
  my ($parsed) = @_;
  foreach my $id (@{$parsed->{protein_ids}}) {
    return ('Ensembl', $id) if $id =~ /^ENS[A-Z]*P\d/;
  }
  foreach my $id (@{$parsed->{protein_ids}}) {
    return ('FlyBase', $id) if $id =~ /^FBpp\d/;
  }
  foreach my $id (@{$parsed->{protein_ids}}) {
    return ('RefSeq', $id) if $id =~ /^[XN]P_\d/;
  }
  return ('UniProt', $parsed->{uniprot}[0]) if @{$parsed->{uniprot}};
  return ('OMA', $parsed->{oma_id});
}

# kept for callers that only need the accession
sub best_accession {
  my ($parsed) = @_;
  my ($namespace, $accession) = accession_for($parsed);
  return $accession;
}

my %NAMESPACE = (
  Ensembl => { source_url => 'https://www.ensembl.org', accession_url => 'https://www.ensembl.org/Multi/Search/Results?q=' },
  FlyBase => { source_url => 'https://flybase.org', accession_url => 'https://flybase.org/reports/' },
  RefSeq  => { source_url => 'https://www.ncbi.nlm.nih.gov/refseq/', accession_url => 'https://www.ncbi.nlm.nih.gov/protein/' },
  UniProt => { source_url => 'https://www.uniprot.org', accession_url => 'https://www.uniprot.org/uniprotkb/' },
  OMA     => { source_url => 'https://omabrowser.org', accession_url => 'https://www.ebi.ac.uk/ebisearch/search?query=' },
);

# "  - HUMAN: Homo sapiens (DB release: Ensembl 102; GRCh38)" -> { HUMAN => 'Ensembl 102; GRCh38' }
sub read_export_sources {
  my ($readme) = @_;
  my %release;
  return \%release unless defined $readme and open my $fh, '<', $readme;
  while (my $line = <$fh>) {
    if ($line =~ /^\s*-\s*([A-Za-z0-9]+):.*\(DB release:\s*(.+)\)\s*$/) {
      $release{$1} = $2;
    }
  }
  close $fh;
  return \%release;
}

# README.exportedAllAll of the OMA run a file belongs to: the nearest one up to 3 levels up
sub find_export_readme {
  my ($file) = @_;
  (my $dir = $file) =~ s{/[^/]*$}{};
  $dir = '.' if $dir eq $file;
  foreach my $level (0 .. 3) {
    return "$dir/README.exportedAllAll" if -e "$dir/README.exportedAllAll";
    $dir .= '/..';
  }
  return undef;
}

# Write one moop TSV per partner species and id namespace, so every file's accession links
# resolve: <PARTNER>.<Namespace>.<kind>.moop.tsv (kind: oma_pairs, oma_hog, oma_orthologs).
#   write_ortholog_tables(kind => 'oma_pairs', label => 'OMA pairwise orthologs', version => ...,
#                         sources => read_export_sources(...), hgnc => {HGNC:n => {symbol,name}},
#                         date => 'YYYY-MM-DD', rows => [ [partner, target_id, parsed_header, suffix], ... ])
# The description is the partner gene's description (HUMAN: current HGNC symbol and name when
# known) plus the suffix, e.g. " (1:1)".
sub write_ortholog_tables {
  my (%arg) = @_;
  my %by_file;
  foreach my $row (@{$arg{rows}}) {
    my ($partner, $target_id, $parsed, $suffix) = @$row;
    my ($namespace, $accession) = accession_for($parsed);
    my $label = $parsed->{description};
    if ($parsed->{hgnc_id} ne '' and $arg{hgnc} and exists $arg{hgnc}{$parsed->{hgnc_id}}) {
      my $current = $arg{hgnc}{$parsed->{hgnc_id}};
      $label = "$current->{symbol}: $current->{name}";
    }
    $label = $accession if $label eq '';
    push @{$by_file{$partner}{$namespace}}, join("\t", $target_id, $accession, "$label$suffix", '-');
  }

  my @written;
  foreach my $partner (sort keys %by_file) {
    foreach my $namespace (sort keys %{$by_file{$partner}}) {
      my $out_file = "$partner.$namespace.$arg{kind}.moop.tsv";
      my $release = $arg{sources}{$partner};
      my $version = $arg{version} . (defined $release ? "; $partner $release" : '');
      open my $out_fh, '>', $out_file or die "cant write $out_file $!\n";
      print $out_fh "## Annotation Source: $arg{label} ($partner)
## Annotation Source Version: $version
## Annotation Source URL: $NAMESPACE{$namespace}{source_url}
## Annotation Accession URL: $NAMESPACE{$namespace}{accession_url}
## Annotation Type: Orthologs
## Annotation Creation Date: $arg{date}
";
      print $out_fh join("\t", "## Gene", "${partner}_ORTHOLOG", "Description", "Score"), "\n";
      my %seen;
      foreach my $line (sort @{$by_file{$partner}{$namespace}}) {
        print $out_fh "$line\n" unless $seen{$line}++;
      }
      close $out_fh;
      push @written, "$out_file (" . scalar(keys %seen) . ")";
    }
  }
  return @written;
}

# HGNC table -> { 'HGNC:n' => { symbol, name } }
sub read_hgnc_symbols {
  my ($file) = @_;
  my %symbol_of;
  return \%symbol_of unless defined $file;
  open my $fh, '<', $file or die "cant open $file $!\n";
  my $header = <$fh>;
  chomp $header;
  my @columns = split /\t/, $header;
  my %index;
  foreach my $column_number (0 .. $#columns) {
    $index{$columns[$column_number]} = $column_number;
  }
  die "no hgnc_id/symbol/name columns in $file\n"
    unless defined $index{hgnc_id} and defined $index{symbol} and defined $index{name};
  while (my $line = <$fh>) {
    chomp $line;
    my @fields = split /\t/, $line;
    $symbol_of{$fields[$index{hgnc_id}]} = { symbol => $fields[$index{symbol}], name => $fields[$index{name}] };
  }
  close $fh;
  return \%symbol_of;
}

# split and trim, dropping empty pieces
sub split_trimmed {
  my ($text, $separator) = @_;
  my @pieces;
  foreach my $piece (split $separator, $text // '') {
    $piece =~ s/^\s+|\s+$//g;
    push @pieces, $piece if length $piece;
  }
  return @pieces;
}

sub xml_unescape {
  my ($text) = @_;
  $text =~ s/&quot;/"/g;
  $text =~ s/&apos;/'/g;
  $text =~ s/&lt;/</g;
  $text =~ s/&gt;/>/g;
  $text =~ s/&amp;/&/g;
  return $text;
}

1;
