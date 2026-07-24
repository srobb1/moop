#!/usr/bin/perl
use strict;
use warnings;


# perl /n/sci/SCI-004223-SBGENOMES/scripts/smr_dev/swissprot.pl 

my $hits = shift;
my $source = 'ProtNLM';
my $source_version = 'protnlm_evidencer_uniprot_2022_04';
my $source_url = 'https://www.uniprot.org';
my $annotation_url = 'https://colab.research.google.com/github/google-research/google-research/blob/master/protnlm/protnlm_evidencer_uniprot_2022_04.ipynb';
my $annotation_type = 'AI Annotations'; 

my $date = `date '+%Y-%m-%d' -r $hits`; 
$date =~ s/\s+//g;

open OUT, ">protnlm.moop.tsv" or die "Can't open protnlm.moop.tsv for writing $! \n";

print OUT "## Annotation Source: $source\n";
print OUT "## Annotation Source Version: $source_version\n";
print OUT "## Annotation Source URL: $source_url\n";
print OUT "## Annotation Accession URL: $annotation_url\n";
print OUT "## Annotation Type: $annotation_type\n";
print OUT "## Annotation Creation Date: $date\n"; 
print OUT join("\t","## Gene", "Accession","Accession_Description","Score"),"\n";

open TSV, $hits or die "Can't open uniprot top hits file:$hits $! \n";
<TSV>;

my %annot;

#seqid protein_name pred_score
#ACI1_HiC_scaffold_1000_000001.1	C2h2-type zn-finger protein	0.014340
while (my $line = <TSV>){
  chomp $line;
  my ($t_id, $hit_desc, $score) = split "\t" , $line;
  next if $score < 0.2; # uniprot uses this cutoff
  if (exists $annot{$t_id} and $score > $annot{$t_id}{score} ){
    $annot{$t_id}{hit}=join("\t",$t_id,'-',$hit_desc,$score);
    $annot{$t_id}{score}=$score;
  }else{
    $annot{$t_id}{hit}=join("\t",$t_id,'-',$hit_desc,$score);
    $annot{$t_id}{score}=$score;
  }
}
foreach my $t_id (sort keys %annot){
  my $hit = $annot{$t_id}{hit};
  print OUT $hit ,"\n";
}






