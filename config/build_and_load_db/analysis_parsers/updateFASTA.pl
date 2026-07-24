#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;

my $fasta = shift;
my $names = shift;

open FASTA, $fasta or die "cant open FASTA:$fasta $! \n";
open NAMES, $names or die "cant open names:$names $! \n";

my %names;
while (my $line = <NAMES>){
  chomp $line;
  # ID  MAINID  GroupId Desc  Note
  # CCA3t000001001.1  SELF  CCA3g000001000.1  NME6: NME/NM23 nucleoside diphosphate kinase 6  OMA_HOMSAP|Orthologs|CCA3t000001001.1|ENSP00000406642.1|ORTHOLOG_GROUP

  my ($id, $mainid, $groupid, $name, $source) = split "\t" , $line;
  my $sym = 'None';
  if ($name =~ /^(\S+):\s*(.+)/){
    $sym = $1;
    $name = $2;
  }

	$name =~ s/^\s*//;
	$name =~ s/\s*$//;
	if (!$sym or $sym eq 'None'){
    $sym = $id;
	}
	if (!$name or $name eq 'None'){
		$name = $id;
	}
	my $gid = $id;
	if ($mainid eq 'SELF'){
    $mainid = $id;
	}
  if (!$source){
    die "NO SOURCE: (id:$id) (main:$mainid) (name:$name) (sym:$sym)\n";
  }
	#$gid =~ s/CCA1t(\d{6})...\.(\d+)/CCA1g${1}000.$2/;
	#$names{$id}{isGene}=$mainid;
	$names{$id}{main}=$mainid;
	$names{$id}{note}=$name;
	$names{$id}{name}=$sym; 
	$names{$id}{source}=$source;
}
while (my $line = <FASTA>){
  chomp $line;
	my ($tid, $main_tid);
	if ($line =~ /^>(\S+)/){
	  my $id = $1;
		if (exists $names{$id}){
			 my $name = $names{$id}{name};
			 my $note = $names{$id}{note};
			 my $source = $names{$id}{source};
       if ($id ne $name and $id ne $note){
         $line = ">$id $name $note $source";
       }else{
         $line = ">$id";
       }
		}
	}
	print $line,"\n";
}

