#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;
use URI::Escape;

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
  
	if (!$sym or $sym eq 'None'){
    $sym = $id;
	}
	if (!$name or $name eq 'None'){
		$name = $id;
	}
  my $gid = $groupid;
	$names{$gid}{isGene}=$id;
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
			 my $name = $names{$main_tid}{name};
			 $name = $name eq $gid ? $gid : $name;
			 my $note = $names{$main_tid}{note};
			 $note = $note eq $gid ? $gid : $note;
			 my $source = $names{$main_tid}{source};
       if ($line =~ /\tApollo\t.*Name=/){ # if it already has a name, don't add a new one
			   $line .= ";auto_name=$name";  #auto_note=$note;auto_namesrc=$source";
       }elsif($line =~ /;Name=([^;]*)/){
         $line =~ s/;Name=$1/;Name=$name/;
       }else{
			   $line .= ";Name=$name"; #Note=$note;namesrc=$source";
       }
       if ($line =~ /\tApollo\t.*Note=/){
         $line .= ";auto_note=$note"; #auto_namesrc=$source";
       }elsif($line =~ /;Note=([^;]*)/){
         $line =~ s/;Note=$1/;Note=$note/;
       }else{
         $line .= ";Note=$note";# ;namesrc=$source";
       }
       if ($line =~ /\tApollo\t.*namesrc=/){
         $line .= ";auto_namesrc=$source";
       }else{
         $line .= ";namesrc=$source";
       }
		}
	}elsif($line =~ /\tmRNA\t/){
    my ($id) = $line =~ /ID=([^;]+)/;
		if (exists $names{$id}){
       my $name = $names{$id}{name};
       my $note = $names{$id}{note};
       my $source = $names{$id}{source};
       if ($line =~ /\tApollo\t.*Name=/){ # if it already has a name, don't add a new one
         $line .= ";auto_name=$name"; #auto_note=$note;auto_namesrc=$source";
       }elsif($line =~ /;Name=([^;]*)/){
         $line =~ s/;Name=$1/;Name=$name/;
       }else{
         $line .= ";Name=$name" ; #Note=$note;namesrc=$source";
       }
       if ($line =~ /\tApollo\t.*Note=/){
         $line .= ";auto_note=$note"; #auto_namesrc=$source";
       }elsif($line =~ /;Note=([^;]*)/){
         $line =~ s/;Note=$1/;Note=$note/;
       }else{
         $line .= ";Note=$note";# ;namesrc=$source";
       }
       if ($line =~ /\tApollo\t.*namesrc=/){
         $line .= ";auto_namesrc=$source";
       }else{
         $line .= ";namesrc=$source";
       }
		}
	}
  if ($line =~ /\tgene\t/ and $line !~ /Name=/){
    $line =~ s/ID=([^;]+)/ID=$1;Name=$1/;
  }
  if ($line =~ /\tmRNA\t/ and $line !~ /Name=/){
    $line =~ s/ID=([^;]+)/ID=$1;Name=$1/;
  }
  $line =~ s/;+/;/g;
  $line =~ s/\s*;/;/g;
	print $line,"\n";

  # if there are update notes, add them in after the gff-version line
  if ($notes and $line =~ /version/){
    $notes =~ s/^\n//;
    print $notes,"\n";
  }
}

