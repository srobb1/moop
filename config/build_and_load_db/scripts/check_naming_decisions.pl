#!/usr/bin/env perl
# Can a gene's name be worked out from its row of naming_decisions.tsv alone? Reads ONLY that table
# (cutoffs from its own # header) and checks two things for every gene:
#
#   1. THE CHOICE -- from the nine step cells (S1..S9) and Closest_human, re-run the rules that pick
#      the step (assign_gene_names_v2.pl decide_name: first step that gives a name; steps 5-7 skipped
#      after an OMA co-ortholog family; the transposon override of step 3) and compare with Step and Name.
#   2. EACH STEP'S VERDICT -- where the row's columns hold a step's inputs, recompute whether that step
#      gives a name and compare with what its cell says. A step whose inputs are not in the columns is
#      counted as "cannot tell", with the missing input named: that is what the table still lacks.
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
foreach my $column (qw(SwissProt_best_hit Best_hit_each_search Closest_human_used_for_naming)) {
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
my (%verdict, %verdict_example);
sub record {
  my ($step, $outcome, $row, $cell) = @_;
  my $key = "S$step\t$outcome";
  $verdict{$key}++;
  push @{$verdict_example{$key}}, "$row->{ID}: " . substr($cell, 0, 160) if @{$verdict_example{$key} // []} < $examples;
}
sub compare {   # predicted 1/0 against the cell
  my ($step, $predicted, $row, $cell, $mismatch_hint) = @_;
  my $said = cell_named($cell);
  if ($predicted == $said) {
    record($step, 'agrees', $row, $cell);
  } else {
    record($step, 'DISAGREES (' . ($said ? 'cell names it' : 'cell gives no name: ' . why_pattern($cell)) . ')'
                . ($mismatch_hint ? " -- $mismatch_hint" : ''), $row, $cell);
  }
}

foreach my $row (@rows) {
  my %cell = map { my $step = $_; ($step => $row->{$step_columns[$step - 1]} // '') } 1 .. 9;
  record(1, $cell{1} =~ /no human-curated name/ ? 'agrees (no such input in this run)' : 'cannot tell: curated names are not a column', $row, $cell{1});
  record(2, $cell{2} =~ /no naming species/ ? 'agrees (no such input in this run)'
          : $cell{2} =~ /^NAMED: the gene set's own name/ ? ($row->{Native_name} ne '' ? 'agrees (Native_name column)' : 'DISAGREES: native, no Native_name')
          : 'cannot tell: naming-species genes are not a column', $row, $cell{2});
  my $closest = closest_human($row);
  my $tree = tree($row);
  my $is_skipped = sub { my ($step) = @_; $cell{$step} =~ /(?:^|; )(?:skipped|passed over)[:;]/ };
  # the columns round (whole %, one-digit E-values): a value shown AT a cutoff may be either side of it
  my $at_margin = sub { my (@pairs) = @_; while (my ($value, $cutoff) = splice @pairs, 0, 2) { return 1 if abs($value - $cutoff) < 1 } return 0 };

  # step 3: the closest human gene is an OMA ortholog (tier 1-2)
  if (!$closest or $closest->{tier} > 2) {
    compare(3, 0, $row, $cell{3});
  } elsif (cell_named($cell{3})) {
    record(3, 'agrees', $row, $cell{3});
  } else {
    record(3, 'cannot tell: OMA tier 1-2 but no name -- ' . why_pattern($cell{3}), $row, $cell{3});
  }

  # step 4: transposable-element Pfam domains are not a column
  record(4, $cell{4} =~ /no transposable-element Pfam domain/ ? 'cannot tell: TE Pfam domains not a column (cell: none)'
                                                             : 'cannot tell: TE Pfam domains not a column', $row, $cell{4});

  # step 5: a trusted placement with one human gene, which is also the single closest human gene (tier 3-5)
  if ($is_skipped->(5)) {
    record(5, 'skipped by the choice rules', $row, $cell{5});
  } elsif (!$tree) {
    compare(5, 0, $row, $cell{5});
  } else {
    my $agrees = ($tree->{trusted} and $tree->{placement} eq 'ortholog_1' and @{$tree->{humans}} == 1 and $closest
                  and $closest->{tier} >= 3 and $closest->{tier} <= 5 and !$closest->{family} and $closest->{genes}[0] eq $tree->{humans}[0]) ? 1 : 0;
    compare(5, $agrees, $row, $cell{5}, $agrees ? 'informative-name check is not in the columns' : '');
  }

  # step 6: the best human gene full-length, no paralog within the tie margin, no contradicting tree
  if ($is_skipped->(6)) {
    record(6, 'skipped by the choice rules', $row, $cell{6});
  } elsif (($row->{Best_human_hit} // '') eq '') {
    compare(6, 0, $row, $cell{6});
  } elsif ($row->{Best_hit_evalue} > $cut{full_evalue}) {
    compare(6, 0, $row, $cell{6}, 'best human hit E above the cutoff: assumed no weaker human hit passes');
  } elsif ($row->{Best_hit_full_length} ne 'yes') {
    compare(6, 0, $row, $cell{6});
  } else {
    my ($second_pct) = ($row->{Second_human_hit} // '') =~ /\((\d+)%\)$/;
    my $tied = (defined $second_pct and $second_pct >= 100 * $cut{tie}) ? 1 : 0;
    # (the column rounds to whole %: a second gene at 94.6% shows as 95%)
    my $near_cut = (defined $second_pct and abs($second_pct - 100 * $cut{tie}) < 1) ? 1 : 0;
    my $tree_other = ($tree and $tree->{trusted} and ($tree->{placement} eq 'ortholog_1' or $tree->{placement} eq 'co-orthologs')
                      and !grep { my $human = $_; $human eq $row->{Best_human_hit} } @{$tree->{humans}}) ? 1 : 0;
    if ($near_cut) {
      record(6, 'cannot tell: second human gene at the tie margin, the column rounds to whole %', $row, $cell{6});
    } elsif ($tied) {
      record(6, 'cannot tell: paralog tie -- which tied genes are reciprocal hits, their HGNC group and PANTHER family are not columns'
                . (cell_named($cell{6}) ? ' (cell names it)' : ' (cell: no name)'), $row, $cell{6});
    } elsif ($tree_other) {
      compare(6, 0, $row, $cell{6}, 'treeC');
    } else {
      compare(6, 1, $row, $cell{6}, 'informative-name check is not in the columns');
    }
  }

  # step 7: best Swiss-Prot hit, another species, full-length, no full-length human hit, not below the best human hit
  if ($is_skipped->(7)) {
    record(7, 'skipped by the choice rules', $row, $cell{7});
  } elsif (($row->{SwissProt_best_hit} // '') eq '') {
    compare(7, 0, $row, $cell{7});
  } elsif ($row->{SwissProt_species} =~ /^Homo sapiens/) {
    compare(7, 0, $row, $cell{7});
  } elsif ($at_margin->($row->{SwissProt_qcov}, $cut{full_cov}, $row->{SwissProt_tcov}, $cut{full_cov}) or $row->{SwissProt_evalue} == $cut{full_evalue}) {
    record(7, 'cannot tell: Swiss-Prot coverage or E-value at the cutoff, the column rounds', $row, $cell{7});
  } elsif (!($row->{SwissProt_evalue} <= $cut{full_evalue} and $row->{SwissProt_qcov} >= $cut{full_cov} and $row->{SwissProt_tcov} >= $cut{full_cov})) {
    compare(7, 0, $row, $cell{7});
  } elsif (($row->{Best_hit_full_length} // '') eq 'yes') {
    compare(7, 0, $row, $cell{7});
  } elsif (($row->{Best_hit_bits} // '') ne '' and $row->{Best_hit_bits} > $row->{SwissProt_bits}) {
    compare(7, 0, $row, $cell{7});
  } elsif (cell_named($cell{7})) {
    record(7, 'agrees (as far as the columns go: Swiss-Prot ties and other human genes\' full-length hits are not columns)', $row, $cell{7});
  } else {
    record(7, 'cannot tell: ' . why_pattern($cell{7}), $row, $cell{7});
  }

  # step 8: the best-covered PANTHER family covers enough of its model
  if (($row->{PANTHER_best} // '') eq '') {
    compare(8, 0, $row, $cell{8});
  } elsif ($at_margin->($row->{PANTHER_model_cov}, $cut{family_cov})) {
    record(8, 'cannot tell: PANTHER model coverage at the cutoff, the column rounds', $row, $cell{8});
  } elsif ($row->{PANTHER_model_cov} >= $cut{family_cov}) {
    compare(8, 1, $row, $cell{8}, 'repeat-built family / informative-name check are not columns');
  } elsif (cell_named($cell{8})) {
    record(8, 'cannot tell: names it below the model cutoff (residue coverage or another family; not columns)', $row, $cell{8});
  } else {
    record(8, 'agrees', $row, $cell{8});
  }

  # step 9: InterPro domains are not a column
  record(9, 'cannot tell: InterPro domains not a column', $row, $cell{9});
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
