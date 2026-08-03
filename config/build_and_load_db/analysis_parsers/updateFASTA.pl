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

		# geneNames.tsv is keyed on the BARE transcript id, but by the time this runs the
		# FASTA ids may already carry MOOP's type suffix -- rename_generic_fasta.pl and
		# rename_t2g_fasta.pl append ":pep" / ":cds" AFTER this script in the pipeline.
		#
		# On a first build the order works: bare header -> matched here -> suffix appended,
		# description preserved. On a RE-RUN the header arrives suffixed, every lookup
		# missed, and the else-branch below rewrote the line down to a bare ">$id" --
		# silently DELETING names a previous run had written. Measured 2026-08-03 across
		# the live site: 46 of 92 gene sets affected, 115,302 features carrying a name in
		# organism.sqlite and a bare header in the FASTA. Bipalium_kewense had lost every
		# one of its 45,518.
		#
		# Stripping the suffix for the LOOKUP ONLY (the header keeps its id untouched) makes
		# this step idempotent: it can run before or after the rename, any number of times.
		#
		# TWO id conventions have to be normalised, and they sit at opposite ends:
		#
		#   MOOP's own TYPE SUFFIX        NV2t011319001.1:pep      appended by
		#                                 NV2t011319001.1:cds      rename_generic_fasta.pl /
		#                                                          rename_t2g_fasta.pl
		#
		#   Ensembl/RefSeq TYPE PREFIX    CDS:FBpp0291548          straight from the GFF
		#                                 cds-XP_001234.1          (ID=CDS:...)
		#
		# get_names_from_gff.pl already normalises the prefix form the same way
		# (s/^(?:cds-|CDS:)//), so this keeps the two in step.
		#
		# The suffix alternation is deliberately exact -- ":pep" and ":cds", nothing else.
		# A generic /:\w+$/ would be wrong here: Ensembl descriptions are full of colons
		# and the id itself can carry one.
		my $key = $id;
		$key =~ s/^(?:cds-|CDS:)//;
		$key =~ s/:(?:pep|cds)$//;

		if (exists $names{$key}){
			 my $name = $names{$key}{name};
			 my $note = $names{$key}{note};
			 my $source = $names{$key}{source};
       # ONE header shape, always:  >id  NAME  DESCRIPTION  SOURCE
       #
       # NAME falls back to the feature id when geneNames.tsv carries no "SYMBOL: " prefix,
       # exactly as $sym does above. Consistency is the point: every named header has the
       # same four fields in the same order, so anything parsing them can rely on position
       # instead of guessing whether a symbol was present.
       #
       # The old code was `if ($id ne $name and $id ne $note)`, requiring BOTH to differ
       # from the id before writing anything -- so a feature with a real description but no
       # symbol failed the test and had its header cleared to a bare ">$id". That is not a
       # rare shape. Measured across the live site 2026-08-03: of every feature carrying a
       # description in organism.sqlite but a bare FASTA header -- 115,302 of them across
       # 46 of 92 gene sets -- 100% had a description with no "SYMBOL: " prefix and 0% had
       # one. Bipalium_kewense had lost all 45,518 of its names this way.
       #
       # updateGFF.pl never had the bug: it writes Name= and Note= as separate attributes,
       # so the description survived there. That is why the same feature reads
       # "Histone H4" in the GFF and in organism.sqlite, and was blank in the FASTA.
       #
       # $key, not $id, on both comparisons -- a ":pep"/":cds" header must not be mistaken
       # for a symbol that happens to differ from its own id.
       my $have_desc = ($note ne $key and $note ne $id);
       if ($have_desc){
         my $label = ($name ne $key and $name ne $id) ? $name : $key;
         $line = ">$id $label $note $source";
       }else{
         # No description to give. Clearing to a bare id is INTENTIONAL -- it is how a
         # description is RETRACTED when an annotation is removed or reassigned, so a stale
         # name cannot outlive the annotation that produced it.
         $line = ">$id";
       }
		}
	}
	print $line,"\n";
}

