#!/usr/bin/perl
use strict;
use warnings;

#for i in ~/dbs/ensembl/release-105/*pep* ;do grep '>' $i | perl -p -e 's/>(\S+).+description:(.+)/$1\t$2/' ; done
#>ENSGALP00000071091.1 pep scaffold:GRCg6a:KZ626833.1:112024:112371:-1 gene:ENSGALG00000050515.1 transcript:ENSGALT00000093220.1 gene_biotype:IG_V_gene transcript_biotype:IG_V_gene description:collagen alpha-2(IV) chain-like [Source:NCBI gene;Acc:107051274]
# >sp|Q6GZX3|002L_FRG3G Uncharacterized protein 002L OS=Frog virus 3 (isolate Goorha) OX=654924 GN=FV3-002L PE=4 SV=1
#product_accession related_accession name  symbol  GeneID
my $txt = shift;
open TXT, $txt or die "cant open feature table: $txt $!\n";
while(my $line = <TXT>){
  chomp $line;
  next unless $line =~ /^mRNA/;
  my @line = split "\t" , $line;
  #grep -P "^mRNA" $FEATURE | awk -F "\t" {'print $11 "\t" $13 "\t" $14 "\t" $15 "\t" $16'} >> ${FEATURE}.summary
  my ($product_accession,$related_accession,$name,$symbol,$GeneID) = ($line[10],$line[12],$line[13],$line[14],$line[15]);
  print "$product_accession\t$name\t$symbol\n";
  print "$related_accession\t$name\t$symbol\n";
  print "$GeneID\t$name\t$symbol\n";
}
