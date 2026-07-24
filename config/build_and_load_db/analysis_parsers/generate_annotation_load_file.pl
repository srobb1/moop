#!/usr/bin/perl
use strict;
use warnings;

my $annotations_file = shift;
my $desc_file = shift;
my $metadata_file = shift;

## ANNOTATIONS FILE
## TIP: make one ortholog file per organism so the user can filter based on organism in case they only are interestd in one
## score can be an evalue or a note, for example oma doesnt have numerical scores, but assigns orthologs and if desired, one can extract pairs instead which reports 1:1,1:many
#id  analysis_score organsim ids
#CCA3t011428001.1  ORTHOLOG_GROUP  CHICK CHICK033704;XP_046799681.1;TTN;HOG:E0765614.1b.2a
#CCA3t018799001.1  ORTHOLOG_GROUP  HUMAN HUMAN055080;ENST00000570156.7;ENSP00000455507.2;ENSG00000154358.22;A6NGQ3;HOG:E0782499.1a.3b
#CCA3t018799001.1  ORTHOLOG_GROUP  MOUSE MOUSE003341;ENSMUST00000238536.2;ENSMUSP00000158795.2;ENSMUSG00000061462.19;OBSCN_MOUSE;A2AAJ9;HOG:E0782499.1a.3b

## desc file
## TIP: refine the desc/id used by only including the wanted desc with ids in the desc_file
#protein_id	transcript_id	gene_id	gene_symbol	description
#ENSACAP00000021683.1	ENSACAT00000029084.1	ENSACAG00000028215.1	MT-ND1	mitochondrially encoded NADH:ubiquinone oxidoreductase core subunit 1
#ENSACAP00000021684.1	ENSACAT00000029088.1	ENSACAG00000028219.1	MT-ND2	mitochondrially encoded NADH:ubiquinone oxidoreductase core subunit 2
#ENSACAP00000021685.1	ENSACAT00000029094.1	ENSACAG00000028225.1	MT-CO1	mitochondrially encoded cytochrome c oxidase I
#ENSACAP00000021686.1	ENSACAT00000029097.1	ENSACAG00000028228.1	MT-CO2	mitochondrially encoded cytochrome c oxidase II
#ENSACAP00000021687.1	ENSACAT00000029099.1	ENSACAG00000028230.1	None	None

## metadata file
=pod
## Annotation Source: source (ENSEMBL Human,OMA Human)
## Annotation Source Version: source_version (release-110)
## Annotation Source URL: source_url (https://www.ensembl.org/)
## Annotation Accession URL: annotation_url (https://www.ensembl.org/Multi/Search/Results?q=)
## Annotation Type: annotation_type (Orthologs, Homologs, Domains, Gene Ontology, Gene Families, AI Annotations, Mapping, Publications)
## Annotation Creation Date: CALCULATED (based on annotation file date)
## ID Accession Accession_Description Score
=cut


my $date = `date '+%Y-%m-%d' -r $annotations_file`;
$date =~ s/\s+//g;

# build desc/sym hash
my %desc;
open DESC, $desc_file or die "CAn't open descFile: $desc_file $! \n";
while (my $line = <DESC>){
  chomp $line;
  my @line = split "\t", $line;
  my $desc = pop @line;
  my $symbol = pop @line;
  foreach my $id (@line){
    $symbol =~ s/\s*//g;
    $desc =~ s/^\s*(.+)\s$/$1/g;
    $desc{$id}{sym}=$symbol;
    $desc{$id}{desc}=$desc; 
  }
}

my $header = join("\t",qw(ID Accession Accession_Description Score));
open METADATA, $metadata_file or die "Can't open metatdata file: $metadata_file $!\n";
while (my $line = <METADATA>){
  chomp $line;
  
  if ($line =~ /Annotation Source:\s*(\S.+)/){
    print "## Annotation Source: $1\n"
  }
  if ($line =~ /Annotation Source Version:\s*(\S.+)/){
    print "## Annotation Source Version: $1\n"
  }
  if ($line =~ /Annotation Source URL:\s*(\S.+)/){
    print "## Annotation Source URL: $1\n"
  }
  if ($line =~ /Annotation Accession URL:\s*(\S.+)/){
    print "## Annotation Accession URL: $1\n"
  }
  if ($line =~ /Annotation Type:\s*(\S.+)/){
    print "## Annotation Type: $1\n"
  }
  if ($line =~ /^(:?Annotation)/){
    $header = $line;
  }
}
print "## Annotation Creation Date: $date\n";
print "## $header\n";

### ANNOTATIONS FILE
#CCA3t011428001.1  CHICK CHICK033704;XP_046799681.1;TTN;HOG:E0765614.1b.2a ORTHOLOG_GROUP

open ANNOTATIONS, $annotations_file or die "Can't open annotation file: $annotations_file $! \n";
while (my $line = <ANNOTATIONS>){
  chomp $line;
  #CCA3t000004001.1	Q2UVH8	Meleagris gallopavo	1.42e-43
  my ($id, $org, $accessions, $analysis_score) = split "\t" , $line;
  my @accessions = split ";", $accessions;
  foreach my $acc (@accessions){
    if (exists $desc{$acc}){
      
      my $desc_note = '';
      if (defined $desc{$acc}{desc} and $desc{$acc}{desc} ne 'None' and $desc{$acc}{desc} !~ /^\s*$/){
        $desc_note = $desc{$acc}{desc};
      }
      my $desc_sym = '';
      if (defined $desc{$acc}{sym} and $desc{$acc}{sym} ne 'None' and $desc{$acc}{sym} !~ /^\s*$/){
        $desc_sym = $desc{$acc}{sym};
      }
      my $note = $desc_note;
      if (defined $desc_sym){
        $note = "$desc_sym: $desc_note";
      }
      ## ID Accession Accession_Description Score
      print join("\t",$id,$acc,$note,$analysis_score),"\n";
      last;
    }
  }  
}
