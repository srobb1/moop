package OmaHogOrthologs;
use strict;
use warnings;
use Exporter 'import';

our @EXPORT_OK = qw(read_hog_orthologs parse_oma_header best_accession);

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

# OMA export header: "CODE000001 | ids; ids | gene | uniprot; entry | HOG:... | description [Source...]; transcript_id=..."
# Returns { oma_id, protein_ids => [...], gene_id, uniprot => [...], description, hgnc_id }
sub parse_oma_header {
  my ($header) = @_;
  my @fields = split_trimmed($header, qr/\|/);
  my %parsed = (oma_id => $fields[0] // '', protein_ids => [], gene_id => '', uniprot => [], description => '', hgnc_id => '');
  return \%parsed if @fields < 3;
  my $description = $fields[-1];
  # "description; transcript_id=..." or, with no description, just "transcript_id=..."
  $description =~ s/(?:^|;)\s*transcript_id=.*$//;
  ($parsed{hgnc_id}) = $description =~ /Acc:(HGNC:\d+)/;
  $parsed{hgnc_id} //= '';
  $description =~ s/\s*\[Source:[^\]]*\]//;
  $parsed{description} = $description;
  $parsed{protein_ids} = [ split_trimmed($fields[1], qr/;/) ];
  $parsed{gene_id} = $fields[2] // '';
  if (@fields >= 5) {
    # UniProt accessions, not entry names (which contain "_")
    foreach my $uniprot_id (split_trimmed($fields[3], qr/;/)) {
      push @{$parsed{uniprot}}, $uniprot_id unless $uniprot_id =~ /_/;
    }
  }
  return \%parsed;
}

# The accession shown for a partner gene: UniProt accession, else a protein id (Ensembl
# ...P..., RefSeq XP_/NP_), else the first listed id, else the OMA id. OMA lists transcript
# and protein ids in no fixed order, so the protein id is picked explicitly.
sub best_accession {
  my ($parsed) = @_;
  return $parsed->{uniprot}[0] if @{$parsed->{uniprot}};
  foreach my $id (@{$parsed->{protein_ids}}) {
    return $id if $id =~ /^ENS[A-Z]*P\d/ or $id =~ /^[XN]P_/;
  }
  return $parsed->{protein_ids}[0] if @{$parsed->{protein_ids}};
  return $parsed->{oma_id};
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
