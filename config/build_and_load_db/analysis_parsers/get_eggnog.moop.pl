#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

#perl /n/sci/SCI-004223-SBGENOMES/dev/smr_dev/eggnog.pl ../analysis/eggnog_mapper/*.emapper.annotations

my $eggnog_tsv = shift;
my %annot;
my $GOTSV = 'go.tsv';
if(!-e $GOTSV){
  `curl -OL http://purl.obolibrary.org/obo/go.obo`;
  `perl  /home/smr/sciproj/SBOMA/mapGO/get_id_and_names.pl go.obo > $GOTSV`;
}

open GO, $GOTSV or die "cant open $GOTSV\n";
#GO:0030603 oxaloacetase activity "Catalysis of the reaction: H(2)O + oxaloacetate = acetate + H(+) + oxalate." [EC:3.7.1.1, RHEA:24432]  molecular_function
my %go;
while (my $line = <GO>){
  chomp $line;
  my ($id, $name, $desc, $namespace) = split "\t" , $line;
  $go{$id}{name}=$name;
  $go{$id}{desc}=$desc;
  $go{$id}{namespace}=$namespace;
}

my $date = `date '+%Y-%m-%d' -r $eggnog_tsv`;
$date =~ s/\s+//g;

#emapper-2.1.12 / Expected eggNOG DB version: 5.0.2
open ORTHO, ">eggnog_orthologs.moop.tsv" or die "Can't opne eggnog_orthologs.moop.tsv for writing $! \n";
print ORTHO "## Annotation Source: EggNOG
## Annotation Source Version: 5.0.2
## Annotation Accession URL: http://eggnog5.embl.de/
## Annotation Source URL: http://eggnog5.embl.de/
## Annotation Type: Orthologs
## Annotation Creation Date: $date\n";
print ORTHO join("\t","## Gene","EggNOG_seed_ortholog","Description","Seed_evalue"),"\n";
 
open TSV, $eggnog_tsv or die "Can't open eggnog file:$eggnog_tsv $!\n";
while (my $line = <TSV>){
  chomp $line;
  next if $line =~ /^#/;
  ##query	seed_ortholog	evalue	score	eggNOG_OGs	max_annot_lvl	COG_category	Description	Preferred_name	GOs	EC	KEGG_ko	KEGG_Pathway	KEGG_Module	KEGG_Reaction	KEGG_rclass	BRITE	KEGG_TC	CAZy	BiGG_Reaction	PFAMs
  #Montipora_capitata_HIv3___RNAseq.g22918.t1	45351.EDO48690	5.46e-35	131.0	KOG3656@1|root,KOG3656@2759|Eukaryota	2759|Eukaryota	O	G-protein coupled receptor activity	-	--	ko:K03440,ko:K04165,ko:K04289,ko:K14049	ko04015,ko04072,ko04080,ko04151,ko04540,ko05200,map04015,map04072,map04080,map04151,map04540,map05200	-	-	-	ko00000,ko00001,ko04030,ko04040	1.A.6.1.4,1.A.6.2,1.A.6.4,1.A.6.5	-	-	7tm_1
  #Montipora_capitata_HIv3___RNAseq.g20158.t1	45351.EDO32906	5.26e-73	228.0	KOG4026@1|root,KOG4026@2759|Eukaryota,38DRD@33154|Opisthokonta,3BCEF@33208|Metazoa	33208|Metazoa	S	positive regulation of fertilization	LHFPL2	GO:0000003,GO:0002576,GO:0003006,GO:0005575,GO:0005622,GO:0005623,GO:0005737,GO:0005886,GO:0005911,GO:0005918,GO:0006810,GO:0006887,GO:0007163,GO:0007275,GO:0007548,GO:0008150,GO:0009987,GO:0012505,GO:0012506,GO:0016020,GO:0016192,GO:0022414,GO:0030054,GO:0030141,GO:0030659,GO:0030667,GO:0031090,GO:0031091,GO:0031092,GO:0031410,GO:0031982,GO:0032501,GO:0032502,GO:0032940,GO:0043226,GO:0043227,GO:0043229,GO:0043296,GO:0043900,GO:0043902,GO:0044422,GO:0044424,GO:0044433,GO:0044444,GO:0044446,GO:0044464,GO:0045055,GO:0045137,GO:0046545,GO:0046546,GO:0046660,GO:0046661,GO:0046903,GO:0048518,GO:0048856,GO:0050789,GO:0051179,GO:0051234,GO:0065007,GO:0070160,GO:0071944,GO:0080154,GO:0097708,GO:0098588,GO:0098805,GO:0099503,GO:1905516,GO:2000241,GO:2000243	-	-	-	-	-	-	-	-	-	-	L_HMGIC_fpl
  my ($t_id, $seed_ortholog, $evalue, $score, $eggNOG_OGs, $max_annot_lvl, $COG_category, $Description, $Preferred_name, $GOs, $EC, $KEGG_ko, $KEGG_Pathway, $KEGG_Module, $KEGG_Reaction, $KEGG_rclass, $BRITE, $KEGG_TC, $CAZy, $BiGG_Reaction, $PFAMs) = split "\t" , $line;

  next if $Description eq '-';

  my ($seed_taxon_id,$seed_ortholog_id) = $seed_ortholog =~ /([^.]+)\.(\S+)/;

  if ($GOs ne '-'){
    my @go_terms = split /,/ , $GOs;
    foreach my $go_term (@go_terms){
      my $analysis='EggNOG2GO';
      $annot{EggNOG2GO}{$t_id}{$go_term}{desc}="$go{$go_term}{name}: $go{$go_term}{desc}";
      $annot{EggNOG2GO}{$t_id}{$go_term}{id}=$go_term;
      $annot{EggNOG2GO}{$t_id}{$go_term}{score}=$go{$go_term}{namespace};
    } 
  }

  my $id = $seed_ortholog_id;
  my $desc = $Description;
  my $sym = $Preferred_name;
  my $summary = $desc;
  if (defined $sym and length $sym > 1 and $sym ne '-'){
    $summary = "$sym: $desc";
  }
  print ORTHO join("\t",$t_id,$id,$summary,$evalue),"\n";
}
close ORTHO;

my $analysis = 'EggNOG2GO'; 
print "Starting: EggNOG2GO.eggnog.moop.txt\n";
open OUT, ">EggNOG2GO.eggnog.moop.tsv" or die "Can't open EggNOG2GO.eggnog.moop.txt for writing $! \n";
print OUT "## Annotation Source: EggNOG ($analysis)
## Annotation Source Version: EggNOG 5.0
## Annotation Accession URL: https://amigo.geneontology.org/amigo/term/
## Annotation Source URL: http://eggnog5.embl.de/
## Annotation Type: Gene Ontology
## Annotation Creation Date: $date\n";
print OUT join("\t","## Gene","${analysis}","Description","NameSpace"),"\n";
foreach my $t (sort keys %{$annot{$analysis}}){
  foreach my $go_term (sort keys %{$annot{$analysis}{$t}}){
    if (exists  $annot{$analysis}{$t}{$go_term}{id}){
      my $id = $annot{$analysis}{$t}{$go_term}{id};
      my $desc = $annot{$analysis}{$t}{$go_term}{desc};
      my $score = $annot{$analysis}{$t}{$go_term}{score};
      print OUT join("\t",$t,$id,$desc,$score),"\n";
    }
  }
}
close OUT;
print "Finished\n";


