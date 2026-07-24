#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

#perl /n/sci/SCI-004223-SBGENOMES/scripts/smr_dev/oma_ortho.pl /n/sci/SCI-004106-SBBATS/genomes/Aeorestes_cinereus/analysis/helixer/ortholog_table/orthologs.HUMAN.MYOLU.PTEVA.txt

#perl /n/sci/SCI-004223-SBGENOMES/scripts/smr_dev/oma_ortho.pl /n/sci/SCI-004106-SBBATS/genomes/Anoura_caudifer/analysis/helixer/ortholog_table/orthologs.HUMAN.MYOLU.PTEVA.txt


my $iprscan_tsv = shift;
my $version = shift;
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
#print Dumper \%go;

my $date = `date '+%Y-%m-%d' -r $iprscan_tsv`;
$date =~ s/\s+//g;

open TSV, $iprscan_tsv or die "Can't open oma iprscan file:$iprscan_tsv $!\n";
<TSV>;
while (my $line = <TSV>){
  chomp $line;
  #seq_id  protein_md5 protein_length  analysis  signature_id  signature_desc  start end score status  date  interpro_id interpro_desc go_terms  pathway_terms
  #ACI1_HiC_scaffold_1000_000001.1 ba3d68d82a5b17e086aee506056d36bd  412 FunFam  G3DSA:3.30.160.60:FF:000135 Zinc finger protein 358 270 295 1.7E-12 T 22-03-2025  - - - -
  #ACI1_HiC_scaffold_10_000001.1	1a7cc15bf91900869cb7f6b4b219f948	177	PANTHER	PTHR42693	ARYLSULFATASE FAMILY MEMBER	4	142	2.1E-12	T	22-03-2025	IPR050738	Sulfatase enzyme	GO:0004065(PANTHER)	MetaCyc:PWY-6546|MetaCyc:PWY-6558|MetaCyc:PWY-6567|MetaCyc:PWY-6568|MetaCyc:PWY-6821|MetaCyc:PWY-7831|MetaCyc:PWY-8045|MetaCyc:PWY-8358|MetaCyc:PWY-8381|Reactome:R-BTA-1663150|Reactome:R-BTA-6798695|Reactome:R-BTA-9840310|Reactome:R-CFA-2022857|Reactome:R-CFA-6798695|Reactome:R-HSA-1663150|Reactome:R-HSA-196071|Reactome:R-HSA-2022857|Reactome:R-HSA-2206290|Reactome:R-HSA-6798695|Reactome:R-HSA-9840310|Reactome:R-MMU-1663150|Reactome:R-MMU-196071|Reactome:R-MMU-2022857|Reactome:R-MMU-6798695|Reactome:R-MMU-9840310|Reactome:R-RNO-1663150|Reactome:R-RNO-196071|Reactome:R-RNO-2022857|Reactome:R-RNO-6798695|Reactome:R-RNO-9840310|Reactome:R-SPO-196071|Reactome:R-SPO-2022857|Reactome:R-SPO-6798695|Reactome:R-SPO-9840310
  #ACI1_HiC_scaffold_10_000002.1	06aa6d1bcba3aa8478ca0e4c2896ea97	772	PANTHER	PTHR22846	WD40 REPEAT PROTEIN	84	771	1.8E-231	T	22-03-2025	IPR045183	F-box-like/WD repeat-containing protein Ebi-like	GO:0000118(PANTHER)|GO:0003714(InterPro)|GO:0003714(PANTHER)|GO:0006357(PANTHER)	Reactome:R-DME-3214815|Reactome:R-DME-350054|Reactome:R-DME-400206|Reactome:R-DME-9029569|Reactome:R-DME-9707564|Reactome:R-HSA-1368082|Reactome:R-HSA-1368108|Reactome:R-HSA-1989781|Reactome:R-HSA-2122947|Reactome:R-HSA-2151201|Reactome:R-HSA-2426168|Reactome:R-HSA-2644606|Reactome:R-HSA-2894862|Reactome:R-HSA-3214815|Reactome:R-HSA-350054|Reactome:R-HSA-381340|Reactome:R-HSA-400206|Reactome:R-HSA-400253|Reactome:R-HSA-9022537|Reactome:R-HSA-9022692|Reactome:R-HSA-9029569|Reactome:R-HSA-9609690|Reactome:R-HSA-9707564|Reactome:R-HSA-9707616|Reactome:R-MMU-3214815|Reactome:R-MMU-350054|Reactome:R-MMU-400206|Reactome:R-MMU-9029569|Reactome:R-MMU-9707564|Reactome:R-SCE-3214815|Reactome:R-SPO-3214841|Reactome:R-SPO-3214858|Reactome:R-SPO-8951664|Reactome:R-SPO-9772755
  my ($t_id, $protein_md5, $protein_length, $analysis, $signature_id, $signature_desc, $start, $end, $score, $status, $date, $interpro_id, $interpro_desc, $go_terms, $pathway_terms) = split "\t" , $line;
  my ($g_id) = $t_id =~ /(.+)\.\d+$/;
  $annot{$analysis}{$t_id}{id}=$signature_id;
  $annot{$analysis}{$t_id}{desc}=$signature_desc;
  $annot{$analysis}{$t_id}{score}=$score;

  if($interpro_id ne '-'){
    $analysis = 'InterPro';
    $annot{$analysis}{$t_id}{id}=$interpro_id;
    $annot{$analysis}{$t_id}{desc}=$interpro_desc;
    $annot{$analysis}{$t_id}{score}='-';
  }
 
  if ($go_terms ne '-'){
    my @go_terms = split /\|/ , $go_terms;
    foreach my $go_term (@go_terms){
      if ($go_term =~ /PANTHER/){
        $go_term =~ s/\(\S+\)//;
        $analysis='PANTHER2GO';
      }else {
        $go_term =~ s/\(InterPro\)//;
        $analysis='InterPro2GO';
      }
      $annot{$analysis}{$t_id}{desc}="$go{$go_term}{name}: $go{$go_term}{desc}";
      $annot{$analysis}{$t_id}{id}=$go_term;
      $annot{$analysis}{$t_id}{score}=$go{$go_term}{namespace};
    } 
  }
}

foreach my $analysis (sort keys %annot){
  my $annotation_type = 'Domains';
  my $annotation_url = lc("https://www.ebi.ac.uk/interpro/entry/$analysis/");
  if ($analysis eq 'PANTHER'){
    $annotation_type = 'Gene Families';
  }elsif($analysis eq 'NCBIfam'){
    $annotation_type = 'Gene Families';
  }elsif($analysis eq 'Gene3D'){
    $annotation_type = 'Gene Families';
    $annotation_url = 'https://www.ebi.ac.uk/interpro/entry/cathgene3d/';
  }elsif($analysis eq 'FunFam'){
    $annotation_type = 'Gene Families';
    $annotation_url ='https://www.cathdb.info/version/latest/superfamily/';
  }elsif($analysis eq 'PIRSF'){
    $annotation_type = 'Gene Families';
  }elsif($analysis eq 'PRINTS'){
    $annotation_type = 'Gene Families';
  }elsif($analysis eq 'SFLD'){
    $annotation_type = 'Gene Families';
  }elsif($analysis eq 'SUPERFAMILY'){
    $annotation_type = 'Gene Families';
  }elsif($analysis eq 'Hamap'){
    $annotation_type = 'Gene Families';
  } 
  print "Starting: $analysis.iprscan.moop.tsv\n";
  open OUT, ">$analysis.iprscan.moop.tsv" or die "Can't open $analysis.iprscan.tsv for writing $! \n";
  print OUT "## Annotation Source: InterProScan ($analysis)
## Annotation Source Version: $version
## Annotation Accession URL: $annotation_url
## Annotation Source URL: https://www.ebi.ac.uk/interpro/
## Annotation Type: $annotation_type
## Annotation Creation Date: $date\n";
  print OUT join("\t","## Gene","${analysis}_iprscan","Description","Score"),"\n";
  foreach my $t (sort keys %{$annot{$analysis}}){
    if (exists  $annot{$analysis}{$t}{id}){
      my $id = $annot{$analysis}{$t}{id};
      if ($analysis eq 'FunFam'){
        $id =~ s/G3DSA:(.+):FF:(\d+)/$1\/funfam\/$2/;
      }
      my $desc = defined $annot{$analysis}{$t}{desc} ? $annot{$analysis}{$t}{desc} : '-'; ;
      my $score = defined $annot{$analysis}{$t}{score} ? $annot{$analysis}{$t}{score} : '-';
      print OUT join("\t",$t,$id,$desc,$score),"\n";
    }
  }
  close OUT;
  print "Finished\n";
}
foreach my $analysis ('InterPro2GO','PANTHER2GO'){
  print "Starting: $analysis.iprscan.moop.tsv\n";
  open OUT, ">$analysis.iprscan.moop.tsv" or die "Can't open $analysis.iprscan.moop.txt for writing $! \n";
  print OUT "## Annotation Source: InterProScan ($analysis)
## Annotation Source Version: 5.72-103.0
## Annotation Accession URL: https://amigo.geneontology.org/amigo/term/
## Annotation Source URL: https://www.ebi.ac.uk/interpro/
## Annotation Type: Gene Ontology
## Annotation Creation Date: $date\n";
  print OUT join("\t","## Gene","${analysis}","Description","Score"),"\n";
  foreach my $t (sort keys %{$annot{$analysis}}){
    if (exists  $annot{$analysis}{$t}{id}){
      my $id = $annot{$analysis}{$t}{id};
      my $desc = $annot{$analysis}{$t}{desc};
      my $score = $annot{$analysis}{$t}{score};
      print OUT join("\t",$t,$id,$desc,$score),"\n";
    }
  }
  close OUT;
  print "Finished\n";
}


