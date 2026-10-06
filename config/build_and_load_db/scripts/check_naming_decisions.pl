#!/usr/bin/env perl
# Can a gene's name be worked out from its row of naming_decisions.tsv alone? Reads ONLY that table
# (cutoffs from its own # header) and checks two things for every gene:
#
#   1. THE CHOICE -- from the nine step cells (S1..S9) and Closest_human, re-run the rules that pick
#      the step (assign_gene_names_v2.pl decide_name: first step that gives a name; steps 5-7 skipped
#      after an OMA co-ortholog family; the transposon override of step 3) and compare with Step and Name.
#   2. EACH STEP'S VERDICT -- from the row's columns that hold each step's inputs, recompute whether that
#      step gives a name and compare with what its cell says. "cannot tell" names an input the table lacks;
#      "DISAGREES" is a rule this script and assign_gene_names_v2.pl apply differently.
#
# Usage: check_naming_decisions.pl naming_decisions.tsv [examples per line, default 3]
# Exit 0 when every choice replays (part 1); part 2 is a report.
use strict;
use warnings;

my ($file, $examples) = @ARGV;
die "Usage: $0 naming_decisions.tsv [examples per line]\n" unless defined $file;
$examples //= 3;

# ---- cutoffs, from the table's own header
my %cut = (full_evalue => undef, full_cov => undef, tie => undef, tree_evalue => undef, tree_cov => undef, family_cov => undef);
my (@columns, @rows);
open my $fh, '<', $file or die "cant read $file $!\n";
while (my $line = <$fh>) {
  chomp $line;
  if ($line =~ /^#/) {
    @cut{qw(full_evalue full_cov)} = ($1, $2) if $line =~ /full-length hit \(names, step 6\): E <= (\S+), >= (\d+)% of this protein/;
    $cut{tie} = 1 - $1 / 100 if $line =~ /paralog tie: another human gene scoring within (\d+)%/;
    @cut{qw(tree_evalue tree_cov)} = ($1, $2) if $line =~ /PANTHER tree placement trusted: its PANTHER match E <= (\S+), >= (\d+)%/;
    $cut{family_cov} = $1 if $line =~ /PANTHER family name \(step 8\): the match covers >= (\d+)%/;
    next;
  }
  my @fields = split /\t/, $line, -1;
  if (!@columns) {
    @columns = @fields;
    next;
  }
  my %row;
  @row{@columns} = @fields;
  push @rows, \%row;
}
close $fh;
foreach my $name (sort keys %cut) {
  die "$file: cutoff '$name' not found in the # header (a table from an older script?)\n" unless defined $cut{$name};
}
foreach my $column (qw(SwissProt_best_hit Closest_human_used_for_naming Naming_species_hits OMA_checks TE_Pfam_domain Human_hits_ranked Human_tie_family
                        SwissProt_within_tie PANTHER_family_used InterPro_domain)) {
  die "$file: no $column column (written by assign_gene_names_v2.pl from 2026-10-06)\n" unless grep { my $have = $_; $have eq $column } @columns;
}
my @step_columns = map { my $step = $_; (grep { my $column = $_; $column =~ /^S${step}_/ } @columns)[0] } 1 .. 9;

# ---- reading a row
# --native: the step that would have named the gene says "the pipeline's pick without the native name: <name>"
my $NAME_MARK = qr/(?:^NAMED: |would name: |the pipeline's pick without the native name: )/;
sub cell_named { my ($cell) = @_; return ($cell =~ $NAME_MARK and $cell !~ /^NAMED: the gene set's own name/) ? 1 : 0 }
sub cell_name {
  my ($cell) = @_;
  my ($name) = $cell =~ /$NAME_MARK(.*?)(?: -- |$)/;
  return $name;
}
# the closest human gene the naming steps used: Closest_human, unless a name replaced it afterwards
sub closest_human {
  my ($row) = @_;
  my $used = $row->{Closest_human_used_for_naming} // 'same';
  my $text = $used eq 'same' ? ($row->{Closest_human} // '') : $used;
  my ($tier, $genes) = $text =~ /^tier (\d+): (.*?) \(/ or return;
  return { tier => $tier, genes => [split m{/}, $genes], family => ($genes =~ m{/} ? 1 : 0) };
}
# "trusted: TreeGrafter places it with human A/B (ortholog_1: ..." -> { trusted, placement, humans }
sub tree {
  my ($row) = @_;
  my ($trust, $humans, $placement) = ($row->{Tree_placement} // '') =~ /^(trusted|weak): TreeGrafter places it with human (\S+) \(([^:]+):/ or return;
  return { trusted => $trust eq 'trusted' ? 1 : 0, placement => $placement, humans => [$humans eq 'none' ? () : split m{/}, $humans] };
}
sub why_pattern {   # a cell's reason with genes, ids and numbers masked, to group like with like
  my ($cell) = @_;
  my $why = $cell =~ s/^(NAMED|not used|not reached|passed over|skipped)[:;]\s*//r;
  $why =~ s/^the pipeline's pick without the native name: //;
  $why =~ s/"[^"]*"/"..."/g;
  $why =~ s/\([^()]*\)/(..)/g;
  $why =~ s/\bhit is [A-Za-z0-9\/.-]+/hit is X/g;
  $why =~ s/\bhuman (?!hit\b|genes?\b)[A-Za-z0-9\/.-]+/human X/g;
  $why =~ s/\b[A-Z][A-Z0-9]*[0-9][A-Z0-9-]*\b/X/g;
  $why =~ s/[0-9.eE+-]*[0-9]+%?/N/g;
  return substr($why, 0, 110);
}

# ---- part 1: the choice
my (%choice, %choice_example);
foreach my $row (@rows) {
  my @cell = ('', map { my $column = $_; $row->{$column} // '' } @step_columns);
  my $named = sub { my ($step) = @_; cell_named($cell[$step]) };
  my $expected;
  if ($row->{Step} =~ /^2 native/) {
    $expected = 2;   # --native: the gene set's own name, kept because informative
  } else {
    my $closest = closest_human($row);
    $expected = $named->(1) ? 1 : $named->(2) ? 2 : undef;
    if (!defined $expected and $cell[3] =~ /^passed over: .*transposon family/ and $named->(4)) {
      $expected = 4;
    }
    my $oma_family = 0;
    if (!defined $expected and $closest and $closest->{tier} <= 2) {
      $expected = 3 if $named->(3);
      $oma_family = $closest->{family};
    }
    $expected //= 4 if $named->(4);
    foreach my $step (5, 6, 7) {
      last if defined $expected;
      next if $oma_family;
      $expected = $step if $named->($step);
    }
    foreach my $step (8, 9) {
      $expected //= $step if $named->($step);
    }
    $expected //= 0;
  }
  my ($step) = $row->{Step} =~ /^(\d)/;
  $step //= 0;
  my $want_name = $expected == 0 ? 'None' : $expected == 2 && $row->{Step} =~ /native/ ? $row->{Name} : cell_name($cell[$expected]);
  # after the choice, a name carries a note of an OMA pairing that did not name the gene (set_aside_note):
  # omaR (a many:1 pairing mostly rejected; not on a step-3 name), else omaC (withheld), else omaX (set aside)
  if (defined $want_name and $want_name ne 'None' and $row->{Step} !~ /native/ and $want_name =~ /\]$/) {
    my $note = ($cell[3] =~ /withheld \(omaR\)/ and $expected != 3) ? 'omaR'
             : $cell[3] =~ /withheld \(omaC\)/ ? 'omaC'
             : $cell[3] =~ /set aside \(omaX\)/ ? 'omaX' : '';
    $want_name =~ s/\]$/|$note]/ if $note ne '';
  }
  my $result = $step != $expected ? "WRONG STEP: table says $step, replay gives $expected"
             : ($want_name // '') ne $row->{Name} ? "WRONG NAME at step $step"
             : 'replays';
  $choice{$result}++;
  push @{$choice_example{$result}}, $row->{ID} if @{$choice_example{$result} // []} < $examples;
}

# ---- part 2: each step's verdict from the columns
# A step's own rule on the words of a name (is it informative, "uncharacterized") is applied to text that is in
# the row; it is not re-implemented here, so a cell saying a name is not informative is accepted as such.
my $UNINFORMATIVE = qr/is not informative|has no informative name|is not an informative name/;
my (%verdict, %verdict_example);
sub record {
  my ($step, $outcome, $row, $cell) = @_;
  my $key = "S$step\t$outcome";
  $verdict{$key}++;
  push @{$verdict_example{$key}}, "$row->{ID}: " . substr($cell, 0, 160) if @{$verdict_example{$key} // []} < $examples;
}
sub compare {   # predicted 1/0 against the cell
  my ($step, $predicted, $row, $cell, $why) = @_;
  my $said = cell_named($cell);
  if ($predicted and !$said and $cell =~ $UNINFORMATIVE) {
    record($step, 'agrees (the name is judged uninformative: a rule on the name in the row)', $row, $cell);
  } elsif ($predicted == $said) {
    record($step, 'agrees', $row, $cell);
  } else {
    record($step, 'DISAGREES: row says ' . ($predicted ? 'a name' : 'no name') . ($why ? " ($why)" : '')
                . ', cell ' . ($said ? 'names it' : 'gives none: ' . why_pattern($cell)), $row, $cell);
  }
}
# "GNAQ 492 bits bh full, E=3.1e-175, 99.7/98.1%; GNA11 479 bits (97.4%) bh full, ..." -> [ { gene, bits, pct, rbh, full } ]
sub ranked_humans {
  my ($row) = @_;
  my @genes;
  foreach my $part (split /; /, $row->{Human_hits_ranked} // '') {
    my ($gene, $bits, $rbh, $full, $evalue) = $part =~ /^(\S+) ([\d.]+) bits (rbh|bh) (full|partial), E=(\S+),/ or next;
    push @genes, { gene => $gene, bits => $bits, rbh => $rbh eq 'rbh', full => $full eq 'full', evalue => $evalue };
  }
  return @genes;
}
sub family_named {   # the shared HGNC group is a family by descent, or (when it is not) a PANTHER family the gene matches
  my ($text) = @_;
  return ($text =~ /\(coherence [\d.]+, a family by descent\)|a smaller HGNC group used|\("[^"]*"\), which the gene matches as a whole member/) ? 1 : 0;
}

foreach my $row (@rows) {
  my %cell = map { my $step = $_; ($step => $row->{$step_columns[$step - 1]} // '') } 1 .. 9;
  record(1, $cell{1} =~ /no human-curated name/ ? 'agrees (no such input in this run)'
          : cell_named($cell{1}) ? 'agrees (the curated name is the input, in the cell)' : 'DISAGREES: curated cell without a name', $row, $cell{1});
  # step 2: the gene set's own name (--native), or the naming species' first candidate with a name, in the order
  # of Naming_species_hits: OMA 1:1 / many:1, full-length RBH, full-length DIAMOND top (not same species), hits file
  if ($cell{2} =~ /no naming species/) {
    record(2, 'agrees (no such input in this run)', $row, $cell{2});
  } elsif ($cell{2} =~ /^NAMED: the gene set's own name/) {
    record(2, $row->{Native_name} ne '' ? 'agrees (Native_name column)' : 'DISAGREES: native, no Native_name', $row, $cell{2});
  } else {
    my ($candidates) = ($row->{Naming_species_hits} // '') =~ /^[^:]+: (.*)$/;
    my $named = 0;
    foreach my $part (split /; /, $candidates // '') {
      $named = 1 if $part =~ /^OMA (?:1:1|many:1) \S+ "[^"]+"/ or $part =~ /^RBH full \S+ "[^"]+"/
                 or $part =~ /^DIAMOND top \S+ "[^"]+" full [^(]*$/ or $part =~ /^hits file \S+ "[^"]+"/;
    }
    compare(2, $named, $row, $cell{2});
  }
  my $closest = closest_human($row);
  my $tree = tree($row);
  my $is_skipped = sub { my ($step) = @_; $cell{$step} =~ /(?:^|; )(?:skipped|passed over)[:;]/ };
  my @humans = ranked_humans($row);

  # step 3: OMA human ortholog(s) the steps used (tier 1-2), and the checks in OMA_checks
  my $oma = $row->{OMA_checks} // '';
  if ($is_skipped->(3)) {
    record(3, 'skipped by the choice rules', $row, $cell{3});
  } elsif (!$closest or $closest->{tier} > 2) {
    compare(3, 0, $row, $cell{3}, 'no OMA human ortholog');
  } elsif ($oma =~ /^fused:|; fused:/) {
    compare(3, 0, $row, $cell{3}, 'fused');
  } elsif ($closest->{family} and !family_named($oma)) {
    compare(3, 0, $row, $cell{3}, 'co-orthologs share no family');
  } elsif ($oma =~ /conflict:/) {
    compare(3, 0, $row, $cell{3}, 'omaC');
  } elsif ($oma =~ /pairing mostly rejected/) {
    compare(3, 0, $row, $cell{3}, 'omaR');
  } else {
    compare(3, 1, $row, $cell{3});
  }

  # step 4: a transposable-element Pfam domain always names
  compare(4, ($row->{TE_Pfam_domain} // '') ne '' ? 1 : 0, $row, $cell{4});

  # step 5: a trusted placement with one human gene, which is also the single closest human gene (tier 3-5)
  if ($is_skipped->(5)) {
    record(5, 'skipped by the choice rules', $row, $cell{5});
  } else {
    my $agrees = ($tree and $tree->{trusted} and $tree->{placement} eq 'ortholog_1' and @{$tree->{humans}} == 1 and $closest
                  and $closest->{tier} >= 3 and $closest->{tier} <= 5 and !$closest->{family} and $closest->{genes}[0] eq $tree->{humans}[0]) ? 1 : 0;
    compare(5, $agrees, $row, $cell{5});
  }

  # step 6: the best human gene (E <= cutoff) full-length; a tie within the margin is decided by one reciprocal
  # hit, else the tied genes' shared family; withheld when a trusted tree places it with other human genes
  my @at_cutoff = grep { my $human = $_; $human->{evalue} <= $cut{full_evalue} } @humans;
  if ($is_skipped->(6)) {
    record(6, 'skipped by the choice rules', $row, $cell{6});
  } elsif (!@at_cutoff) {
    compare(6, 0, $row, $cell{6}, 'no human hit at the cutoff');
  } elsif (!$at_cutoff[0]{full}) {
    compare(6, 0, $row, $cell{6}, 'best human gene partial');
  } else {
    my @tied = grep { my $human = $_; $human->{bits} >= $cut{tie} * $at_cutoff[0]{bits} } @at_cutoff;
    my @reciprocal = grep { my $human = $_; $human->{rbh} and $human->{full} } @tied;
    my ($named, @named_genes) = (1, $at_cutoff[0]{gene});
    if (@tied > 1) {
      if (@reciprocal == 1) {
        @named_genes = ($reciprocal[0]{gene});
      } else {
        $named = family_named($row->{Human_tie_family} // '');
        @named_genes = map { my $human = $_; $human->{gene} } @tied;
      }
    }
    my %named_gene = map { my $gene = $_; ($gene => 1) } @named_genes;
    my $tree_other = ($tree and $tree->{trusted} and ($tree->{placement} eq 'ortholog_1' or $tree->{placement} eq 'co-orthologs')
                      and !grep { my $human = $_; $named_gene{$human} } @{$tree->{humans}}) ? 1 : 0;
    compare(6, ($named and !$tree_other) ? 1 : 0, $row, $cell{6},
            !$named ? 'tie without a shared family' : $tree_other ? 'treeC' : '');
  }

  # step 7: best Swiss-Prot hit, another species, full-length, no other protein within the margin,
  # no full-length human gene, not below the best human hit
  my $sp_full = (($row->{SwissProt_best_hit} // '') ne '' and $row->{SwissProt_evalue} <= $cut{full_evalue}
                 and $row->{SwissProt_qcov} >= $cut{full_cov} and $row->{SwissProt_tcov} >= $cut{full_cov}) ? 1 : 0;
  if ($is_skipped->(7)) {
    record(7, 'skipped by the choice rules', $row, $cell{7});
  } elsif (($row->{SwissProt_best_hit} // '') eq '') {
    compare(7, 0, $row, $cell{7}, 'no Swiss-Prot hit');
  } elsif ($row->{SwissProt_species} =~ /^Homo sapiens/) {
    compare(7, 0, $row, $cell{7}, 'human protein');
  } elsif (!$sp_full) {
    compare(7, 0, $row, $cell{7}, 'not full-length');
  } elsif (($row->{SwissProt_within_tie} // '') =~ /: another protein/) {
    compare(7, 0, $row, $cell{7}, 'Swiss-Prot tie');
  } elsif (grep { my $human = $_; $human->{full} } @humans) {
    compare(7, 0, $row, $cell{7}, 'a full-length human gene');
  } elsif (($row->{Best_hit_bits} // '') ne '' and $row->{Best_hit_bits} > $row->{SwissProt_bits}) {
    compare(7, 0, $row, $cell{7}, 'below the best human hit');
  } else {
    compare(7, 1, $row, $cell{7});
  }

  # step 8: the family passing the model bar (PANTHER_family_used); a repeat-built one names the repeat
  my $family = $row->{PANTHER_family_used} // '';
  compare(8, ($family ne '' and $family !~ /^none passes/) ? 1 : 0, $row, $cell{8});

  # step 9: the best InterPro domain or repeat covering enough of its model
  my $domain = $row->{InterPro_domain} // '';
  compare(9, ($domain ne '' and $domain !~ /^only part of a domain/) ? 1 : 0, $row, $cell{9});
}

# ---- report
printf "%s: %d genes\n\nPART 1 -- the choice (Step and Name from the step cells and Closest_human)\n", $file, scalar @rows;
foreach my $result (sort { $choice{$b} <=> $choice{$a} } keys %choice) {
  printf "  %6d  %s%s\n", $choice{$result}, $result, $result eq 'replays' ? '' : '  e.g. ' . join(', ', @{$choice_example{$result}});
}
print "\nPART 2 -- each step's verdict from the row's columns\n";
foreach my $step (1 .. 9) {
  my @keys = grep { my $key = $_; $key =~ /^S$step\t/ } keys %verdict;
  my $total = 0;
  $total += $verdict{$_} foreach @keys;
  print "  $step_columns[$step - 1]\n";
  foreach my $key (sort { $verdict{$b} <=> $verdict{$a} or $a cmp $b } @keys) {
    (my $outcome = $key) =~ s/^S\d\t//;
    printf "    %6d  %5.1f%%  %s\n", $verdict{$key}, 100 * $verdict{$key} / $total, $outcome;
    if ($outcome !~ /^agrees|^skipped/) {
      print "              e.g. $_\n" foreach @{$verdict_example{$key}};
    }
  }
}
exit(($choice{replays} // 0) == @rows ? 0 : 1);
