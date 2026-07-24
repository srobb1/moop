#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

my $pairs_file    = shift;    # Output/PairwiseOrthologs/<THISORG>-<OTHERORG>.txt
my $THISORG       = shift;
my $OTHERORG      = shift;
my $THISORG_first = shift;    # 1|0 means True|False
my $OMA_DB_V      = shift;

warn "Pairs Path: $pairs_file\n";
warn "Your Organism: $THISORG\n";
warn "Other Organism: $OTHERORG\n";
warn "DB VERSION: $OMA_DB_V\n";

open PAIRS, $pairs_file or die "Cant open pairsfile: $pairs_file $! \n";
## Format: Protein 1<tab>Protein 2<tab>Protein ID1<tab>ProteinID2<tab>Orthology type<tab>OMA group (if any)
#9	29551	CCA3t004838004.1	HUMAN029551 | ENSP00000497736.1; ENST00000647773.1 | ENSG00000167377.18 | HOG:F0769924 | zinc finger protein 23 [Source:HGNC Symbol;Acc:HGNC:13023]; transcript_id=ENST00000647773.1	many:many
#7 4712  DANMAL_XP_005163249.1 NP_001070058.1 uncharacterized protein LOC767650 [Danio rerio]  many:many

my %hits;

while (my $line = <PAIRS>){
  chomp $line;
  next if $line =~ /^#/;
  my ($omaid1,$omaid2,$ids1,$ids2,$type,$oma_group) = split "\t", $line;
  my ($this_id,$other_org_ids);
  if ($THISORG_first){
    ($this_id) = $ids1 =~ /^(\S+)/;
    $other_org_ids = $ids2;
  }else{
    ($this_id) = $ids2 =~ /^(\S+)/;
    $other_org_ids = $ids1;
    my @parts = split ":", $type;
    $type = "$parts[1]:$parts[0]";
  }
  my @parts = split /\|/, $other_org_ids;
  my $last  = pop @parts;
  my @other_ids = ($other_org_ids);
  if ($last =~ /transcript_id=(\S+)\.?\d*/){
    push @other_ids, $1;
    $last =~ s/; transcript_id=.*//;
  }
  my $description = $last;
  $description =~ s/^\s*(.*?)\s*$/$1/;
  foreach my $part (@parts){
    foreach my $each (split /\;/, $part){
      $each =~ s/\s*(\S+)\s*/$1/;
      push @other_ids, $each;
    }
  }

  foreach my $oid (@other_ids){
    $description = 'None' if !defined $description || length($description) < 2;
    my $out = join("\t", $this_id, $oid, $description, $type);
    if ($oid =~ /ENS.*P\d+/){
      $hits{'Ensembl'}{"$this_id-$oid"} = $out;
    }elsif ($oid =~ /XP_/ or $oid =~ /NP_/){
      $hits{'RefSeq'}{"$this_id-$oid"} = $out;
    }
  }
}

foreach my $db (sort keys %hits){
  my $db_url = "https://www.ensembl.org/Multi/Search/Results?q=";
  if ($db =~ /RefSeq/){
    $db_url = "https://www.ncbi.nlm.nih.gov/search/all/?term=";
  }
  my $date = `date '+%Y-%m-%d' -r $pairs_file`;
  $date =~ s/\s+//g;
  my $outfile = "$OTHERORG.$db.oma_pairs.moop.tsv";
  print "Starting: $outfile\n";
  open OUT, ">$outfile" or die "Can't open $outfile for writing $! \n";
  print OUT "## Annotation Source: OMA PAIRWISE ORTHOLOGS ($OTHERORG)
## Annotation Source Version: $OMA_DB_V
## Annotation Accession URL: https://omabrowser.org/oma/home/
## Annotation Source URL: $db_url
## Annotation Type: Orthologs
## Annotation Creation Date: $date\n";
  print OUT join("\t","## Gene","${OTHERORG}_ORTHOLOG","Description","ORTHOLOG_TYPE"),"\n";
  foreach my $each (sort keys %{$hits{$db}}){
    print OUT "$hits{$db}{$each}\n";
  }
  close OUT;
}

print "Finished\n";
