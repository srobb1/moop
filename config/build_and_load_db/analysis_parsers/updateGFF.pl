#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;
use URI::Escape;

# --keep-source-names (RefSeq/Ensembl, notes/GFF_FASTA_ID_ALIGNMENT_PLAN.md): a line keeps the
# source's own Name= wherever this script would otherwise name it by its own ID or leave it
# unnamed -- a gene naming v2 left without a symbol (LOC116613694, not 116613694), non-coding
# genes and transcripts not in geneNames.tsv (LOC125561164, tRNA symbols), CDS lines. A symbol
# from geneNames.tsv still replaces it. Without the switch (other/lift) nothing changes.
my $keep_source_names = 0;
if (@ARGV && $ARGV[0] eq '--keep-source-names'){
  shift;
  $keep_source_names = 1;
}
my $gff = shift;
my $names = shift;
my $notes_file = shift;

my @notes = '';
if (defined $notes_file){
  open NOTES, $notes_file or die "Can't open notes file: $notes_file $! \n";
  while (my $line = <NOTES>){
    chomp $line;
    push @notes, $line;
  }
}
my $notes = join("\n",@notes);

open GFF, $gff or die "cant open gff:$gff $! \n";
open NAMES, $names or die "cant open names:$names $! \n";

my %names;
while (my $line = <NAMES>){
  chomp $line;
  # ID	MAINID	GroupId	Desc	Note
  # CCA3t000001001.1	SELF	CCA3g000001000.1	NME6: NME/NM23 nucleoside diphosphate kinase 6	OMA_HOMSAP|Orthologs|CCA3t000001001.1|ENSP00000406642.1|ORTHOLOG_GROUP

	my ($id, $mainid, $groupid, $name, $source) = split "\t" , $line;
  my $sym = 'None';
  if ($name =~ /^(\S+):\s*(.+)/){
    $sym = $1;
    $name = $2;
  }
	$name =~ s/^\s*//;
	$name =~ s/\s*$//;
  $name =~ s/\s+/ /g;
  
  # is this too agressive?
  #$name =~ s/[^a-zA-Z0-9 ]/uri_escape($&)/ge;
  #[Source:HGNC Symbol;Acc:HGNC:12569]
  $name =~ s/\[Source.+?\]//;
  #OS=Mus musculus OX=10090 GN=Ntn1 PE=1 SV=3
  $name =~ s/\s+OS=.+$//;
  

  $name =~ s/=/\%3D/g;
  $name =~ s/;/\%3B/g;
  
  # No symbol or no description: left empty, and the line gets no Name= / Note= (2026-11-17).
  # It used to be filled with this row's id -- for a gene, its main TRANSCRIPT's id, so the
  # database showed "A2h1T0002261.3" as the name of gene A2h1G0002261 (64 gene sets, ~70% of
  # genes in the Trinity/SIMR ones) and, with no description, as its description too. An
  # unnamed feature has a blank name in the database (load_genes_sqlite.pl) and JBrowse labels
  # it by its ID.
	if (!$sym or $sym eq 'None' or $sym eq $id){
    $sym = '';
	}
	if (!$name or $name eq 'None' or $name eq $id){
		$name = '';
	}
  my $gid = $groupid;
  # The gene's own row (ID = GroupId: RefSeq/Ensembl geneNames.tsv) names the gene, as it does in
  # the database (parse_GFF3_to_MOOP_TSV.pl). Without one, the group's last row, as always. A
  # protein shared by two genes otherwise gave a gene the other gene's name (Bradyrhizobium, 42).
  if ($id eq $gid){
    $names{$gid}{isGene}=$id;
    $names{$gid}{isGeneSelf}=1;
  }elsif (!$names{$gid}{isGeneSelf}){
    $names{$gid}{isGene}=$id;
  }
	$names{$id}{note}=$name;
	$names{$id}{name}=$sym; 
  $source =~ s/PROTNLM\|.+\|(.+)/PROTNLM|$1/;
	$names{$id}{source}=$source;
}
while (my $line = <GFF>){
  chomp $line;
  if ($line =~ /[\(\)\[\]]/){
    $line =~ s/[\(\)\[\]]/uri_escape($&)/ge;
  }
	my ($gid, $tid, $main_tid);
  my ($source_name) = $line =~ /(?:\t|;)Name=([^;]+)/;
  if($line =~ /merged_Name=[^;]+/){
    $line =~ s/merged_Name=[^;]+;?//;
  }
  if($line =~ /merged_Note=[^;]+/){
    $line =~ s/merged_Note=[^;]+;?//;
  }
  if($line =~ /merged_Parent=[^;]+/){
    $line =~ s/merged_Parent=[^;]+;?//;
  }
  if($line =~ /merged_ID=[^;]+/){
    $line =~ s/merged_ID=[^;]+;?//;
  }
  if($line =~ /Name=[^;]+/ and $line !~ /\tApollo\t/){
    $line =~ s/Name=[^;]+;?//;
  }
  if($line =~ /Note=[^;]+/  and $line !~ /\tApollo\t/){
    $line =~ s/Note=[^;]+;?//;
  }
  if($line =~ /namesrc=[^;]+/ and $line !~ /\tApollo\t/){
    $line =~ s/namesrc=[^;]+;?//;
  }
	if ($line =~ /\tgene\t/){
	  my ($gid) = $line =~ /ID=([^;]+)/;
		if (exists $names{$gid}{isGene}){
       $main_tid = $names{$gid}{isGene};
			 $line = add_name_note($line, $names{$main_tid});
		}
	}elsif($line =~ /\t(?:mRNA|transcript)\t/){ # transcript: Trinity/make_fake_genome_from_t2g.pl, smed_20140614, Turritopsis
    my ($id) = $line =~ /ID=([^;]+)/;
		if (exists $names{$id}){
       $line = add_name_note($line, $names{$id});
		}
	}
  if ($keep_source_names and defined $source_name and $line !~ /\tApollo\t/){
    my ($own_id) = $line =~ /(?:\t|;)ID=([^;]+)/;
    my ($new_name) = $line =~ /(?:\t|;)Name=([^;]*)/;
    if (!defined $new_name){
      $line .= ";Name=$source_name";
    }elsif (defined $own_id and $new_name eq $own_id){
      $line =~ s/((?:\t|;)Name=)\Q$new_name\E(;|$)/$1$source_name$2/;
    }
  }
  # (a gene or transcript with no name keeps no Name=: no Name=<ID> fallback, 2026-11-17)
  $line =~ s/;+/;/g;
  $line =~ s/\s*;/;/g;
	print $line,"\n";

  # if there are update notes, add them in after the gff-version line
  if ($notes and $line =~ /version/){
    $notes =~ s/^\n//;
    print $notes,"\n";
  }
}


# Name=, Note= and namesrc= for one gene or transcript line, from its geneNames.tsv row. An empty
# name or description adds nothing (no Name= / Note=). An Apollo line that already has a Name= or
# Note= keeps it, and the pipeline's value goes in auto_name= / auto_note= beside it.
sub add_name_note {
  my ($line, $row) = @_;
  foreach my $pair (['Name', 'auto_name', $row->{name}], ['Note', 'auto_note', $row->{note}]) {
    my ($tag, $auto_tag, $value) = @$pair;
    next if !defined $value or $value eq '';
    if ($line =~ /\tApollo\t.*\b$tag=/){
      $line .= ";$auto_tag=$value";
    }elsif($line =~ /;$tag=([^;]*)/){
      my $old = $1;
      $line =~ s/;$tag=\Q$old\E/;$tag=$value/;
    }else{
      $line .= ";$tag=$value";
    }
  }
  if ($line =~ /\tApollo\t.*namesrc=/){
    $line .= ";auto_namesrc=$row->{source}";
  }else{
    $line .= ";namesrc=$row->{source}";
  }
  return $line;
}
