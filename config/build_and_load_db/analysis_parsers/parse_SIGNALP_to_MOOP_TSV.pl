#!/usr/bin/perl
use strict;
use warnings;

my $top_hits = shift; #= '../analysis/BLASTP_UNIPROT_sprot/tophit.tsv';
my $source = 'SignalP'; #shift; #'SwissProt';
my $source_url = "https://services.healthtech.dtu.dk/services/SignalP-6.0/"; # shift; #'https://www.ensembl.org/'; #'https://www.uniprot.org';
my $annotation_type = 'Domains'; 
my $annotation_url = '';

my $date = `date '+%Y-%m-%d' -r '$top_hits'`;
$date =~ s/\s+//g;
my $source_nospace = $source;
$source_nospace =~ s/\s+/_/g;
$source_nospace =~ s/\//_/g;
open OUT, ">$source_nospace.domains.moop.tsv" or die "Can't open $source_nospace.domains.moop.tsv for writing $! \n";

open TH, $top_hits or die "Can't open top hits $source file:$top_hits $! \n";
my $header = <TH>;
my ($source_version) = $header =~ /^#\s*(SignalP-\S+)\s*/;

print OUT "## Annotation Source: $source\n";
print OUT "## Annotation Source Version: $source_version\n";
print OUT "## Annotation Source URL: $source_url\n";
print OUT "## Annotation Accession URL: $annotation_url\n";
print OUT "## Annotation Type: $annotation_type\n";
print OUT "## Annotation Creation Date: $date\n";
print OUT join("\t","## Gene", "Accession","Accession_Description","Score"),"\n";

## SignalP-6.0	Organism: Other	Timestamp: 20260731111413
# ID	Prediction	OTHER	SP(Sec/SPI)	LIPO(Sec/SPII)	TAT(Tat/SPI)	TATLIPO(Tat/SPII)	PILIN(Sec/SPIII)	CS Position
#ACA1_PVKU01000001.1_000001.1 VAV3 Guanine nucleotide exchange factor EGGNOG_ORTHOLOG_GROUP|ACA1_PVKU01000001.1_000001.1|XP_008145317.1|5.03e-36	OTHER	1.000000	0.000000	0.000000	0.000000	0.000000	0.000000
#ACA1_PVKU01000001.1_000004.1 NTNG1 netrin G1 OMA_STRICT_ORTHOLOG|ACA1_PVKU01000001.1_000004.1|HUMAN|ENSG00000162631	OTHER	1.000000	0.000000	0.000000	0.000000	0.000000	0.000000
#ACA1_PVKU01000001.1_000003.1 PRMT6 protein arginine methyltransferase 6 OMA_STRICT_ORTHOLOG|ACA1_PVKU01000001.1_000003.1|HUMAN|ENSG00000198890	OTHER	1.000000	0.000000	0.000000	0.000000	0.000000	0.000000
#ACA1_PVKU01000001.1_000002.1 AMY1A amylase alpha 1A OMA_STRICT_ORTHOLOG|ACA1_PVKU01000001.1_000002.1|HUMAN|ENSG00000237763	SP	0.000144	0.999357	0.000123	0.000140	0.000113	0.000110	CS pos: 15-16. Pr: 0.9804

## Only SP (Sec/SPI, the general secretory signal peptide) is loaded.
## LIPO/TAT/TATLIPO/PILIN are deliberately dropped -- decided with a colleague
## that most users only care about the standard SP class. Not an oversight;
## do not "complete" this without asking.
while (my $line = <TH>){
  chomp $line;
  next if $line =~ /^#/;
  my @line = split "\t" , $line;
  next unless ($line[1] // '') eq 'SP';
  my $query = $line[0];
  my ($t_id) = $query =~ /^(\S+)/;
  my $score = $line[3];

  ## The CS-position text is the last (9th) column, but split() drops
  ## trailing empty fields -- don't trust $line[-1] blindly. Fall back to a
  ## plain description if it's missing or doesn't look like a CS position.
  my $position = $line[8];

  ## Say it in words rather than pasting SignalP's own string through.
  ##
  ## That string is "CS pos: 26-27. Pr: 0.9751", and the row it lands in already
  ## carries a Score of, say, 0.999357. The two numbers are NOT the same quantity:
  ## Score is column 4, SP(Sec/SPI) -- the probability the protein HAS a signal
  ## peptide -- while "Pr" is the probability of THAT PARTICULAR CLEAVAGE SITE.
  ## Displayed side by side, unlabelled, one reads as a rounding of the other. So
  ## the description names which probability it is, and spells out CS and Pr.
  my $description;
  if (defined $position && $position =~ /^CS pos:\s*(\d+)-(\d+)\.\s*Pr:\s*([0-9.]+)/) {
    $description = "Signal peptide with cleavage site between residues $1 and $2 "
                 . "(cleavage site probability $3)";
  }
  elsif (defined $position && $position =~ /^CS pos:/) {
    ## Recognisably a CS position but not the shape above -- keep SignalP's text
    ## rather than dropping information we failed to parse.
    $description = "Signal peptide ($position)";
  }
  else {
    $description = "Signal peptide predicted";
  }

  print OUT join("\t",$t_id,"SP",$description,$score),"\n";
}
close OUT;

