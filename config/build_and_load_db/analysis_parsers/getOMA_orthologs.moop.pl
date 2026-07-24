#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

my $OG       = shift;    # 'Output/OrthologousGroups.txt';
my $THISORG  = shift;
my $OMA_DB_V = shift;

warn "OrthologousGroups Path: $OG\n";
warn "Your Organism: $THISORG\n";
warn "DB VERSION: $OMA_DB_V\n";

# 'Output/OrthologousGroups.txt';
open OG, $OG or die "cant open file OrthologousGroups $OG $! \n";


my %hits;

#OMA00004  CCA3X:CCA3t020725001.1  CCA3Y:CCA3t020725001.1  HUMAN:HUMAN062025 | ENSP00000403181.2; ENST00000447166.2 | ENSG00000221843.4 | C9JG08 | HOG:E0725108 | chromosome 2 open reading frame 16 [Source:HGNC Symbol;Acc:HGNC:25275]; transcript_id=ENST00000447166.2
#OMA00006	ANOCAR:ENSACAT00000046457.1 ENSACAP00000039508.1 pep primary_assembly:AnoCar2.0v2:GL343302.1:1362213:1568346:-1 gene:ENSACAG00000003769.4 transcript:ENSACAT00000046457.1 gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:NEB description:nebulin [Source:UniProtKB Gene Name;Acc:A0A803T7Y2]	CCA3X:CCA3t011306001.1	CCA3Y:CCA3t011306001.1	GALGAL:ENSGALT00010024189.1 ENSGALP00010013774.1 pep primary_assembly:bGalGal1.mat.broiler.GRCg7b:7:34784897:34899708:-1 gene:ENSGALG00010010115.1 transcript:ENSGALT00010024189.1 gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:NEB description:nebulin [Source:UniProtKB Gene Name;Acc:A0A8V0Y5T1]
while ( my $line = <OG> ) {
  chomp $line;
  next if $line     =~ /^#/;
  next unless $line =~ /$THISORG:\S+/;
  my ($id) = $line =~ /$THISORG:(\S+)/;
  my ( $line_id, @output ) = split "\t", $line;
  my $hit_src = 'none';
  foreach my $output (@output) {
    next if $output =~ /$id/;
    #CCA3X:CCA3t020725001.1
    #CCA3Y:CCA3t020725001.1
    #GALGAL:ENSGALT00010024189.1 ENSGALP00010013774.1 pep primary_assembly:bGalGal1.mat.broiler.GRCg7b:7:34784897:34899708:-1 gene:ENSGALG00010010115.1 transcript:ENSGALT00010024189.1 gene_biotype:protein_coding transcript_biotype:protein_coding gene_symbol:NEB description:nebulin [Source:UniProtKB Gene Name;Acc:A0A8V0Y5T1]
    #( VARKO, VARKO027472 | ENSVKKT00000023130.1; ENSVKKP00000022571.1 | ENSVKKG00000015017.1 | A0A8D2LIG3_VARKO; A0A8D2LIG3 | HOG:F0871366.1c.3b.18a | procollagen-lysine,2-oxoglutarate 5-dioxygenase 3 [Source:HGNC Symbol;Acc:HGNC:9083]; transcript_id=ENSVKKT00000023130.1 )
    #( CHICK, CHICK046999 | XP_414864.3 | ARHGAP17 | A0A8V0Z535_CHICK; A0A8V0Z535 | HOG:F0789896 | rho GTPase-activating protein 17 isoform X1 )
    #( HUMAN, HUMAN027331 | ENSP00000289968.6; ENST00000289968.11 | ENSG00000140750.17 | Q68EM7; RHG17_HUMAN | HOG:F0789896 | Rho GTPase activating protein 17 [Source:HGNC Symbol;Acc:HGNC:18239]; transcript_id=ENST00000289968.11 )
    #( MOUSE, MOUSE056865 | ENSMUST00000106442.9; ENSMUSP00000102050.3 | ENSMUSG00000030766.16 | RHG17_MOUSE; Q3UIA2 | HOG:F0789896 | Rho GTPase activating protein 17 [Source:MGI Symbol;Acc:MGI:1917747]; transcript_id=ENSMUST00000106442.9 )
    #( SCEUN, SCEUN036668 | XP_042336688.1 | ARHGAP17 | HOG:F0789896 | rho GTPase-activating protein 17 isoform X1 )
    #OMA00013  KILLIFISH:XP_070397754.1 obscurin isoform X3 [Nothobranchius furzeri] NOTFU:NOTFU014734 | XP_015812179.1 | obscn | HOG:F0793129.1a.1a | obscurin; The sequence of the model RefSeq protein was modified relative to this genomic sequence to represent the inferred CDS: added 798 bases not found in genome assembly; Derived by automated computational analysis using gene prediction method: Gnomon.  RATNO:RATNO001933 | ENSRNOP00000069172.2; ENSRNOT00000084697.2 | ENSRNOG00000058068.2 | HOG:F0793129.4b | obscurin, cytoskeletal calmodulin and titin- interacting RhoGEF [Source:RGD Symbol;Acc:631335]; transcript_id=ENSRNOT00000084697.2  ZEBRAFISH:XP_073797116.1 obscurin isoform X2 [Danio rerio]
    my ( $ORG, $ids ) = $output =~ /^(\S+):(.+)/;
    my @other_ids = ($ids);
    my @parts = split /\|/ , $output;
    my $last = pop @parts;
    if ($last =~ /transcript_id=(\S+)\.?\d*/){
        my $transcript_id = $1;
        push @other_ids, $transcript_id;
        $last =~ s/; transcript_id=.*//;
    }
    my $description = $last; 
    $description =~ s/^\s*(.*?)\s*$/$1/;
    foreach my $part (@parts){
     $part =~ s/$ORG://;
     foreach my $each (split /\;/ , $part){
       $each =~ s/\s*(\S+)\s*/$1/;
       push @other_ids, $each;
     }
    }
    foreach my $oid (@other_ids){
      $description = defined $description ? $description : 'None';
      $description = 'None' if length $description < 2;
      my $out =  join( "\t", $id, $oid, $description, "ORTHOLOG_GROUP" );
      my $dbxref = 'Ensembl';  
      if ($oid =~ /ENS.*P\d+/){
        $dbxref = 'Ensembl';  
        $hits{$ORG}{$dbxref}{"$id-$oid"}=$out;
      }elsif ($oid =~ /XP_/){
        $dbxref = 'RefSeq';
        $hits{$ORG}{$dbxref}{"$id-$oid"}=$out;
      }#elsif ($oid =~ /\w+\_\w+/){
      #  $dbxref = 'UniProt';
      #  $hits{$ORG}{$dbxref}{"$id-$oid"}=$out;
      #}


      
      
    }
  }
}

foreach my $ORG (sort keys %hits){
  foreach my $db (sort keys %{$hits{$ORG}}){
    my $db_url = "https://www.ensembl.org/Multi/Search/Results?q=";
    if ($db =~ /RefSeq/){
      $db_url = "https://www.ncbi.nlm.nih.gov/search/all/?term="; 
    }elsif ($db =~ /UniProt/){
      $db_url = "https://www.uniprot.org/uniprotkb/";
    }
    my $date = `date '+%Y-%m-%d' -r $OG`;
    $date =~ s/\s+//g;
    my $analysis = $ORG;
    print "Starting: $ORG.$db.oma_orthologs.moop.tsv\n";
    open OUT, ">$ORG.$db.oma_orthologs.moop.tsv" or die "Can't open $ORG.$db.oma_orthologs.moop.tsv for writing $! \n";
    print OUT "## Annotation Source: OMA ORTHOLOGS ($analysis)
## Annotation Source Version: $OMA_DB_V
## Annotation Accession URL: https://omabrowser.org/oma/home/
## Annotation Source URL: $db_url 
## Annotation Type: Orthologs
## Annotation Creation Date: $date\n";
    print OUT join("\t","## Gene","${analysis}_ORTHOLOG","Description","ORTHOLOG_TYPE"),"\n";
    foreach my $each (sort keys %{$hits{$ORG}{$db}}){
      print OUT "$hits{$ORG}{$db}{$each}\n";
    }   
    close OUT;
  }
}

print "Finished\n";
