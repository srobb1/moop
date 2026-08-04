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
# A truncated or failed geneNames.tsv would otherwise blank EVERY header below, since
# nothing would match. Dying here leaves the FASTA untouched and fails the build loudly,
# which is recoverable; a silently emptied FASTA is not.
if (!%names){
  die "updateFASTA: no usable rows in $names -- refusing to rewrite $fasta\n";
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
		#
		#   TransDecoder ORF MARKER       bkew.kc1.000000_0_1.16.p1       appended to the
		#                                 bkew.kc1.000000_0_1.16.p1:pep   TRANSCRIPT id when
		#                                                                 ORFs were called
		#
		# The third convention was missed until 2026-08-04 and cost Bipalium_kewense every
		# one of its 45,518 protein and CDS descriptions on a re-run that was supposed to
		# restore them. Its transcript ids are bare (bkew.kc1.000000_0_1.16) and match
		# geneNames.tsv, so transcript.nt.fa came out perfect -- 17,489 descriptions, exactly
		# what organism.sqlite holds -- while protein.aa.fa and cds.nt.fa carry ".p1" on top
		# of ":pep"/":cds", missed every lookup, and were cleared to bare ids by the
		# not-found branch below. Compare Bradypodion_ventrale, whose ids are just
		# id/:pep/:cds with no ORF marker: all three files landed on 20,232 of 20,232.
		#
		# ⚠️ The not-found branch RETRACTS (rewrites to ">$id"), so a missed lookup does not
		# merely fail to add a description -- it DELETES one. Thirteen further gene sets
		# share this id shape and still hold 50-90% real protein descriptions from an older
		# pipeline; without this ladder, re-running them would zero every one.
		#
		# CANDIDATE LADDER, most specific first, first hit wins. Not an unconditional strip:
		# a gene set whose geneNames.tsv really is keyed on the full suffixed id still
		# matches at candidate 1 and is untouched by the looser rules below it. Each rung
		# builds on the previous, so the last is prefix + suffix + ORF marker removed.
		#
		# 🔑 THE EMITTED ID IS NEVER REWRITTEN -- every branch below interpolates $id, not
		# $key. The header id has to keep matching features_coords.tsv, features.tsv and
		# feature_uniquename in organism.sqlite, or a BLAST hit and a gene page can no longer
		# say which feature they belong to, and neither can retrieve its GFF lines or build a
		# JBrowse link. Normalising is for FINDING the description, nothing else. This is the
		# same rule that makes Ensembl's "CDS:" prefix safe to look past but never to remove:
		# strip it for real and 30,802 FlyBase CDS ids collide with their protein ids.
		my @candidates;
		my $c = $id;
		push @candidates, $c;                                    # 1. exact, as it appears
		(my $c_np = $c)    =~ s/^(?:cds-|CDS:)//;
		push @candidates, $c_np    if $c_np    ne $c;             # 2. type prefix dropped
		(my $c_nps = $c_np) =~ s/:(?:pep|cds)$//;
		push @candidates, $c_nps   if $c_nps   ne $c_np;          # 3. + type suffix dropped
		(my $c_npso = $c_nps) =~ s/\.p\d+$//;
		push @candidates, $c_npso  if $c_npso  ne $c_nps;         # 4. + ORF marker dropped

		my $key;
		foreach my $candidate (@candidates){
			if (exists $names{$candidate}){
				$key = $candidate;
				last;
			}
		}

		# Every form of the id we considered. The have-a-description test below asks "is this
		# text just the id repeated?", and geneNames.tsv may store any one of these shapes,
		# so the comparison has to cover all of them rather than the single matched key.
		my %is_id_like = map { $_ => 1 } (@candidates, $id);

		if (!defined $key){
			# Not in geneNames.tsv: this feature has no name any more. Strip whatever the
			# header carried, so a description cannot outlive the annotation that produced
			# it -- the same retraction rule as the has-a-row-but-nothing-to-say case below.
			# Previously such lines passed through untouched and kept a stale description
			# indefinitely.
			$line = ">$id";
		}
		else {
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
       # Compared against %is_id_like, not $id alone -- a ":pep"/":cds"/".p1" header must not
       # be mistaken for a symbol that happens to differ from its own id. Checking every rung
       # of the ladder rather than just the matched $key means it does not matter which id
       # shape geneNames.tsv happened to store.
       my $have_desc = !exists $is_id_like{$note};
       if ($have_desc){
         my $label = !exists $is_id_like{$name} ? $name : $key;
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

