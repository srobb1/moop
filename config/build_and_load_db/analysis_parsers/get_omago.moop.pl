#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;


my $ORG = shift; # 'PCAN';
my $OMA_DB_V = shift;
my $go = shift || 'go.tsv';
my $map = shift || "$ORG.Map-SeqNum-ID.txt";
my $omago = shift || "$ORG.gene_function.gaf";


open GO, $go or die "cant open go.tsv\n";
#GO:0030603	oxaloacetase activity	"Catalysis of the reaction: H(2)O + oxaloacetate = acetate + H(+) + oxalate." [EC:3.7.1.1, RHEA:24432]	molecular_function
my %go;
while (my $line = <GO>){
  chomp $line;  
  my ($id, $name, $desc, $namespace) = split "\t" , $line;
  $go{$id}{name}=$name;
  $go{$id}{desc}=$desc;
  $go{$id}{namespace}=$namespace; 
}

my %mapping;
open MAP, $map or die "cant open mapping file: $map\n";
#head Map-SeqNum-ID.txt
#Format: genome<tab>sequence number<tab>id
#AMPQE	1	AMPQE000001 | PAC:15698531 | Aqu1.200003 | A0A1X7SD66_AMPQE | HOG:C0651706
while (my $line = <MAP>){
  chomp $line;
	next if $line =~ /^#/;
  my ($org, $oma_gene_id, $id) = split "\t", $line;
	next unless $org eq $ORG;
	$id =~ s/(\S+).*/$1/;
  $mapping{$oma_gene_id}=$id; 
}
open OMAGO, $omago or die "cant open oma gene function assignemnt: $omago\n";
my $date = `date '+%Y-%m-%d' -r $map`;
$date =~ s/\s+//g;
print  "## Annotation Source: OMA2GO
## Annotation Source Version: $OMA_DB_V
## Annotation Source URL: https://omabrowser.org/oma/home/
## Annotation Accession URL: https://www.ensembl.org/Multi/Search/Results?q=
## Annotation Type: Gene Ontology
## Annotation Creation Date: $date
## ID GO_ID	GO_DESCRIPTION	NAMESPACE\n";
my %hits;
my %dbs;
my %reported;
while (my $line = <OMAGO>){
  #OMAStandalone	CCA3X:00002	CCA3X:00002		GO:0005515	OMA_Fun:001	IEA		F			protein	taxon:-1	20250908	OMAStandalone
  chomp $line;
	next if $line =~ /^!/;
	next unless $line =~ /$ORG/;
  my @line = split "\t", $line;
  my $oma_gene_id = $line[1];
	$oma_gene_id =~ s/$ORG:0*(\d+)/$1/;
  my $id = $mapping{$oma_gene_id};
  my $go_id = $line[4];
	# map oma gene id to real gene id
	if (exists $go{$go_id}){
	  my $go_name = $go{$go_id}{name};
	  my $go_desc = $go{$go_id}{desc};
	  my $go_ns = $go{$go_id}{namespace};
    if(!exists $reported{$id}{$go_id}){
	    print join ("\t", $id,$go_id,"$go_name: $go_desc",$go_ns),"\n";
      $reported{$id}{$go_id}++;
    }
	}
}

