#!/usr/bin/perl
# make_named_partner_fasta.pl -- a partner proteome for closest_species searches from one of OUR
# built gene sets: one protein per gene, titled with the gene id and the name this pipeline gave
# it, so the closest gene in another gene set reads "A2h1G0000012 LRMDA: leucine rich ..." instead
# of a bare id.
#
#   perl scripts/make_named_partner_fasta.pl BUILD_GENESET_DIR PROTEIN_FASTA > partner.fa
#     BUILD_GENESET_DIR  the build's data/<Org>/<Asm>/<GeneSet> (geneNames.tsv, geneNames.readable.tsv)
#     PROTEIN_FASTA      the gene set's delivered protein.aa.fa (genomes/v2), first word = transcript id
#
# The protein of a gene is its main isoform (geneNames.tsv MAINID); when that isoform has no
# protein (a non-coding main isoform), the gene's longest protein. Genes with no protein at all
# are left out. Title: ">GENE_ID SYMBOL: description", ">GENE_ID description" when the name has
# no symbol, and ">GENE_ID" alone for a gene with no name. Writes a summary to STDERR.
use strict;
use warnings;

my ($build_dir, $protein_fasta) = @ARGV;
die "Usage: $0 BUILD_GENESET_DIR PROTEIN_FASTA > partner.fa\n" unless defined $protein_fasta;

# geneNames.tsv: ID MAINID GroupId Desc Note (MAINID SELF: the transcript is its own main isoform)
my (%gene_of_transcript, %main_of_gene);
open my $names_fh, '<', "$build_dir/geneNames.tsv" or die "cant read $build_dir/geneNames.tsv\n";
while (my $line = <$names_fh>) {
  chomp $line;
  next if $line =~ /^ID\t/;
  my ($transcript_id, $main_id, $gene_id) = split /\t/, $line;
  next unless defined $gene_id and $gene_id ne '';
  $main_id = $transcript_id if $main_id eq 'SELF';
  $gene_of_transcript{$transcript_id} = $gene_id;
  $main_of_gene{$gene_id} //= $main_id;
}
close $names_fh;

# geneNames.readable.tsv: gene_id type id symbol description name_from method evidence
my %title_of_gene;
open my $readable_fh, '<', "$build_dir/geneNames.readable.tsv" or die "cant read $build_dir/geneNames.readable.tsv\n";
while (my $line = <$readable_fh>) {
  chomp $line;
  my ($gene_id, $type, undef, $symbol, $description) = split /\t/, $line;
  next unless defined $type and $type eq 'gene';
  my @parts;
  push @parts, "$symbol:" if defined $symbol and $symbol ne '' and $symbol ne 'N/A';
  push @parts, $description if defined $description and $description ne '' and $description !~ /^(?:N\/A|No name)$/;
  $title_of_gene{$gene_id} = join ' ', @parts;
}
close $readable_fh;

# every protein of every gene
my (%proteins_of_gene, $current, $unknown);
open my $fasta_fh, '<', $protein_fasta or die "cant read $protein_fasta\n";
while (my $line = <$fasta_fh>) {
  chomp $line;
  if ($line =~ /^>(\S+)/) {
    my $transcript_id = $1;
    my $gene_id = $gene_of_transcript{$transcript_id};
    $unknown++ unless defined $gene_id;
    $current = defined $gene_id ? ($proteins_of_gene{$gene_id}{$transcript_id} = \(my $sequence = '')) : undef;
  } elsif (defined $current) {
    $$current .= $line;
  }
}
close $fasta_fh;

my ($written, $named, $not_main) = (0, 0, 0);
foreach my $gene_id (sort keys %proteins_of_gene) {
  my $proteins = $proteins_of_gene{$gene_id};
  my $chosen = $main_of_gene{$gene_id};
  unless (defined $chosen and exists $proteins->{$chosen}) {
    ($chosen) = sort { length ${ $proteins->{$b} } <=> length ${ $proteins->{$a} } or $a cmp $b } keys %$proteins;
    $not_main++;
  }
  my $title = $title_of_gene{$gene_id} // '';
  print $title ne '' ? ">$gene_id $title\n" : ">$gene_id\n";
  my $sequence = ${ $proteins->{$chosen} };
  for (my $start = 0; $start < length $sequence; $start += 60) {
    print substr($sequence, $start, 60), "\n";
  }
  $written++;
  $named++ if $title ne '';
}

my $genes = keys %main_of_gene;
print STDERR "genes: $genes; with a protein (written): $written; with a name: $named; ",
             "longest protein used (main isoform has none): $not_main; no protein: ", $genes - $written, "\n";
print STDERR "WARNING: $unknown proteins in $protein_fasta are not in geneNames.tsv (left out)\n" if $unknown;
