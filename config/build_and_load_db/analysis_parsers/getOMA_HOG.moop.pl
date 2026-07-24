#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

my $HOG_dir    = shift;    # Output/HOGFasta
my $THISORG       = shift;
my $OMA_DB_V      = shift;

warn "HOGs Path: $HOG_dir\n";
warn "Your Organism: $THISORG\n";
warn "DB VERSION: $OMA_DB_V\n";

#head ~/sciproj/Malabaricus/orthologs/DANRE_HUMAN_MOUSE_NOTFU_RATNO_RATRT/OMA.2.7.0/Output/HOGFasta/HOG21250.fa
#>DANMAL_NP_001121707.1 [DANMAL]
#MKSTSPPPPPAFVRVSERDLTEIELHSVDSINDLHRTHSEQHSKGVQPPRPPPPSTNGSLHMQDRPVVYRTVQAGRRPCM
#SRLNKICTSTWGHYFLACTAVIAFLIILILIFSSL
#>ENSDARG00000102199.3 [DANRE]
#MSCSLEKVLGDARTLLERLKEHDTAAESLIEQSSVLGQKIHSMKEVGNTLPDKYMEENTEYQELSRYKPHVLLSQENTQI
#KELQQENRELWLSLEEHQYALELIMGRYRKQMLQMMMEKKELDTKPVLSLHQNHAKEVQSQLGRICEMGQVMRQAVQMDD
#QHYCSVKERLAQLEIENKELRGLLSISSVKQHREEKNPPETTSETVEKQES
#>ENSG00000052723.12 [HUMAN]
#MSCTIEKILTDAKTLLERLREHDAAAESLVDQSAALHRRVAAMREAGTALPDQVRQRYQEDASDMKDMSKYKPHILLSQE
#NTQIRDLQQENRELWISLEEHQDALELIMSKYRKQMLQLMVAKKAVDAEPVLKAHQSHSAEIESQIDRICEMGEVMRKAV
#QVDDDQFCKIQEKLAQLELENKELRELLSISSESLQARKENSMDTASQAIK

my %hogs;

my @hog_fastas = <$HOG_dir/HOG*.fa>;
foreach my $hog_file (@hog_fastas){
  open HOG, $hog_file or die "Cant open HOG FASTA Dir: $hog_file $! \n";
  my ($hog_group) = $hog_file =~ /(HOG\d+)/;
  while (my $line = <HOG>){
    chomp $line;
    next if $line !~ /^>/;
    my ($id,$org) = $line =~ /^>(\S+).*\[(\S+)\]$/;
    $hogs{$hog_group}{$id}++;
  }
}
my $db_url = 'test';
my $date = `date '+%Y-%m-%d' -r $hog_fastas[0]`;
  $date =~ s/\s+//g;
  my $outfile = "$OTHERORG.$db.oma_hog.moop.tsv";
  print "Starting: $outfile\n";
  open OUT, ">$outfile" or die "Can't open $outfile for writing $! \n";
  print OUT "## Annotation Source: OMA HOMOLOGOUS ORTHOLOGS GROUPS
## Annotation Source Version: $OMA_DB_V
## Annotation Accession URL: https://omabrowser.org/oma/home/
## Annotation Source URL: $db_url
## Annotation Type: Orthologs
## Annotation Creation Date: $date\n";
  print OUT join("\t","## Gene","HOG_ID","Description","ORTHOLOG_TYPE"),"\n";
  foreach my $each (sort keys %{$hogs{$db}{$group}{}){
    print OUT "$hits{$db}{$each}\n";
  }
  close OUT;
}

foreach my $group (sort keys %hogs){
    my @members;
  if (exists $hogs{$group}{$THISORG}){
    foreach my $org (sort keys %{$hogs{$group}}){
      foreach my $id  (sort keys %{$hogs{$group}{$org}}){
        push @members, "$org:$id";
      }
    }
    print "$group\t", join(";",@members),"\n";
  }
}


__END__
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
