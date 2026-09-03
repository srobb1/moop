#!/usr/bin/perl
use strict;
use warnings;

#for i in ~/dbs/ensembl/release-105/*pep* ;do grep '>' $i | perl -p -e 's/>(\S+).+description:(.+)/$1\t$2/' ; done
#>ENSGALP00000071091.1 pep scaffold:GRCg6a:KZ626833.1:112024:112371:-1 gene:ENSGALG00000050515.1 transcript:ENSGALT00000093220.1 gene_biotype:IG_V_gene transcript_biotype:IG_V_gene description:collagen alpha-2(IV) chain-like [Source:NCBI gene;Acc:107051274]
# >sp|Q6GZX3|002L_FRG3G Uncharacterized protein 002L OS=Frog virus 3 (isolate Goorha) OX=654924 GN=FV3-002L PE=4 SV=1

my $fa = shift;
open FA, $fa or die "cant open fasta : $fa $!\n";
while(my $line = <FA>){
  chomp $line;
  next unless $line =~ /^>(\S+)/;
  my $header = $1;
  my $id=$header;
  my $desc = 'none';
  if($header =~ /sp\|/){ #uniport
    ($id,$desc) = $line =~ /sp\|(\w+)\|\S+ (.+)/;
  } elsif ($line =~ /^>E.+description/){
    $id = $header;
    ($desc) = $line =~ /.+description:(.+)/;
  }
  my $gene = '';
  if ($line =~ /gene:(\S+)/){
    $gene = $1;
  }
  my $sym = $id;
  if ($line =~ /gene_symbol:(\S+)/){
   $sym = $1;
  }
  print "$id\t$desc\t$sym\n";
}
