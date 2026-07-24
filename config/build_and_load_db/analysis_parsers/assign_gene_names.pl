#!/usr/bin/perl
use strict;
use warnings;
use Data::Dumper;


my $isoforms = shift; 
#cat make_CCA_isoforms.sh
#perl makeTranscriptGroups.CCALY.pl ~/sciproj/SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/rbbh/CCA3.fa > Chamaeleo_calyptratus/isoforms.tsv

my @annotation_files = @ARGV;

my %all_ids;
my %tgroups;
my %selected_ids;
my %group_names;
my %names;

open TGROUPS, $isoforms or die "cant open transcript grouping file: $isoforms $! \n";
# CCA3t002600001.1;CCA3t002600002.1;CCA3t002600003.1	None CCA3t002600001.1 
# [semicolon sep list of isoforms]	[selected transcript. optional] [gene id. optional] 
while (my $line = <TGROUPS>){
    chomp $line;
    my ($tids,$selected,$group_id) = split /\t/ , $line;
    my @tids = split /;/ , $tids;
    if (!defined $group_id){
      $group_id = $line;
    }
    if(defined $selected and $selected ne 'None'){
      $selected_ids{$group_id}=$selected;
    }
    foreach my $tid (@tids){
      $tgroups{$group_id}{$tid}++;
      $all_ids{$tid}=$group_id;
      if(defined $selected and $selected ne 'None'){
        $selected_ids{$tid}=$selected;
      }
    }
}

my %file_order;
my $rank=0; # 0=best
my %src_info;
foreach my $annotation_file (@annotation_files){
  $file_order{$annotation_file}=$rank;
  my $src;
  my $type;
  ## Annotation Source: UniProtKB/Swiss-Prot
  ## Annotation Source Version: 2024_06
  ## Annotation Source URL: https://www.uniprot.org
  ## Annotation Accession URL: https://www.uniprot.org/uniprotkb/
  ## Annotation Type: Homologs
  open INFILE, $annotation_file or die "Cant open annotation file:$annotation_file $! \n";
  while (my $line = <INFILE>){
    chomp $line;
    if ($line =~ /## Annotation Source:\s*(.+)\s*$/){
      $src = $1;
    }elsif($line =~ /## Annotation Type:\s*(.+)\s*$/){
      $type = $1;
    }
    if($line =~ /^#/){
      next;
    }
    $src_info{$rank}{src}=$src;
    $src_info{$rank}{type}=$type;
    my ($id, $hit_id, $hit_desc, $score) = split /\t/, $line;
    my $sort_score = $score;
    if ($score !~ /\d+/){
      $sort_score = 0;
    }
    ## CCA3t000003001.1  Q2UVH8  ACR: Acrosin OS=Meleagris gallopavo OX=9103 GN=ACR PE=1 SV=1  7.81e-18
    # annotation files are in order of preference, so we will only store the first hit we come across
    # infiles should be sorted by score
    my $group_id = $all_ids{$id};
    # if we already have a selected id, record the info here, we dont want to record anyohter id's hit info
    if(exists $selected_ids{$group_id} and $selected_ids{$group_id} eq $id){
      $group_names{$group_id}{$rank}{$sort_score}{hit_desc}=$hit_desc;
      $group_names{$group_id}{$rank}{$sort_score}{hit_id}=$hit_id;
      $group_names{$group_id}{$rank}{$sort_score}{this_id}=$id;
      $group_names{$group_id}{$rank}{$sort_score}{score}=$score;
    }
    elsif(!exists $group_names{$group_id}){
      $group_names{$group_id}{$rank}{$sort_score}{hit_desc}=$hit_desc;
      $group_names{$group_id}{$rank}{$sort_score}{hit_id}=$hit_id;
      $group_names{$group_id}{$rank}{$sort_score}{this_id}=$id;
      $group_names{$group_id}{$rank}{$sort_score}{score}=$score;
    }
  }
  close INFILE;
  $rank++;
}
foreach my $this_group (sort keys %group_names){
  # sort by src rank 0=best  $names{group_id}{id}{src_rank}
  foreach my $rank (sort { $a <=> $b } keys %{$group_names{$this_group}}){
    # find the best scoring (smallest evalue) transcript with a hit to this src
    my $best_score = 100000000;
    my $best_score_id;
    foreach my $score (sort { $a <=> $b } keys %{$group_names{$this_group}{$rank}} ){
      $best_score = $score;
      $best_score_id = $group_names{$this_group}{$rank}{$score}{this_id};
      last; # only need the single best score
    }
    # group_id selected_id hit_desc hit_id score
    $names{$this_group}{hit_desc} = $group_names{$this_group}{$rank}{$best_score}{hit_desc};
    $names{$this_group}{hit_id} = $group_names{$this_group}{$rank}{$best_score}{hit_id};
    $names{$this_group}{score} = $best_score;
    $names{$this_group}{rank} = $rank;
    $names{$this_group}{selected} = $best_score_id;
    $selected_ids{$this_group}=$best_score_id;
    last; # we dont need to look at info from lower ranked src annotations
  }
}

print "ID\tMAINID\tGroupId\tDesc\tNote\n";
foreach my $group_id (sort keys %names){
  my $selected_id = $selected_ids{$group_id};
  my $hit_desc = $names{$group_id}{hit_desc};
  my $hit_id = $names{$group_id}{hit_id};
  my $sort_score = $names{$group_id}{score};
  my $rank = $names{$group_id}{rank};
  my $score = $group_names{$group_id}{$rank}{$sort_score}{score};
  my $src = $src_info{$rank}{src};
  $src =~ s/[\s)(]/_/g;
  $src =~ s/_+/_/g;
  $src =~ s/_$//g;
  my $type = $src_info{$rank}{type};
  $type =~ s/\s+/_/g;
  foreach my $id (sort keys %{$tgroups{$group_id}}){
    my $main_id = $selected_id;
    if ($main_id eq $id){
      $main_id = "SELF";
    }
    print join("\t",$id,$main_id,$group_id,$hit_desc,"$src|$type|$selected_id|$hit_id|$score"),"\n";
  }
} 
