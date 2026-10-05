#!/usr/bin/perl
use strict;
use warnings;

# Every naming pathway a gene can take, with how many genes take it per species and one real
# example each (the gene, its name, and the provenance sentence the site shows). Pathways are
# recognised from the decision table alone (Step, the tag of Pipeline_name, the Reason and the
# S<n> columns), so this can be rerun on any gene set after the rules change.
#
# Usage: naming_pathways.pl Label=naming_decisions.tsv ... > pathways.tsv
# Output (TSV): pathway, step, then per label: count and one example (gene | name | provenance)

my @PATHWAYS = (
  # [ id, step, test on a row (hash of columns + tag set) ]
  ['2a native name kept',                         2, sub { $_[0]{Step} =~ /^2 native/ and $_[0]{native_kept} }],
  ['3a OMA 1:1 ortholog',                         3, sub { $_[0]{step} == 3 and $_[0]{tag}{'1to1'} and !$_[0]{tag}{te} }],
  ['3b OMA many:1, copies here (N to 1)',         3, sub { $_[0]{step} == 3 and grep { /^\d+to1$/ and $_ ne '1to1' } keys %{$_[0]{tag}} and !$_[0]{tag}{te} }],
  ['3c OMA many:1 kept as one copy (mto1)',       3, sub { $_[0]{step} == 3 and $_[0]{tag}{mto1} }],
  ['3d OMA ortholog of a domesticated transposon gene (te)', 3, sub { $_[0]{step} == 3 and $_[0]{tag}{te} }],
  ['3e OMA pairwise 1 gene, HOG several -> family', 3, sub { $_[0]{step} == 3 and $_[0]{tag}{fam} and $_[0]{Reason} =~ /^OMA pairs it 1:1 with human .*OMA's HOG makes it co-ortholog/ }],
  ['3f OMA co-orthologs -> HGNC group family',    3, sub { $_[0]{step} == 3 and $_[0]{tag}{fam} and $_[0]{Reason} =~ /in the HGNC group/ }],
  ['3g OMA co-orthologs -> PANTHER family',       3, sub { $_[0]{step} == 3 and $_[0]{tag}{fam} and $_[0]{Reason} =~ /PANTHER family/ }],
  ['4a transposable element',                     4, sub { $_[0]{step} == 4 }],
  ['5a PANTHER tree, closest human by RBH',       5, sub { $_[0]{step} == 5 and $_[0]{tag}{rbh} }],
  ['5b PANTHER tree, closest human by best hit',  5, sub { $_[0]{step} == 5 and $_[0]{tag}{bh} }],
  ['5c PANTHER tree, closest human via another species', 5, sub { $_[0]{step} == 5 and !$_[0]{tag}{rbh} and !$_[0]{tag}{bh} }],
  ['6a full-length reciprocal best hit (-like)',  6, sub { $_[0]{step} == 6 and $_[0]{tag}{rbh} and !$_[0]{tag}{'tie-rbh'} and !$_[0]{tag}{'tie-grp'} }],
  ['6b full-length best hit (-like)',             6, sub { $_[0]{step} == 6 and $_[0]{tag}{bh} and !$_[0]{tag}{'tie-grp'} }],
  ['6c paralog tie, one reciprocal best hit decides (-like)', 6, sub { $_[0]{step} == 6 and $_[0]{tag}{'tie-rbh'} }],
  ['6e paralog tie -> PANTHER family',            6, sub { $_[0]{step} == 6 and $_[0]{tag}{'tie-grp'} and $_[0]{Reason} =~ /human genes of PANTHER family/ }],
  ['6d paralog tie -> HGNC group family',         6, sub { $_[0]{step} == 6 and $_[0]{tag}{'tie-grp'} and $_[0]{Reason} =~ /human genes of the HGNC group/ }],
  ['7a Swiss-Prot protein of another species (-like (species))', 7, sub { $_[0]{step} == 7 }],
  ['8a PANTHER family, InterPro\'s curated name', 8, sub { $_[0]{step} == 8 and $_[0]{tag}{pthr} and $_[0]{Reason} =~ /\(InterPro / }],
  ['8b PANTHER family, PANTHER\'s own name',      8, sub { $_[0]{step} == 8 and $_[0]{tag}{pthr} and $_[0]{Reason} !~ /\(InterPro / }],
  ['8c PANTHER family built of repeats -> repeat name', 8, sub { $_[0]{step} == 8 and $_[0]{tag}{rpt} }],
  ['9a InterPro domain',                          9, sub { $_[0]{step} == 9 and $_[0]{Reason} !~ /repeat/i }],
  ['9b InterPro repeat',                          9, sub { $_[0]{step} == 9 and $_[0]{Reason} =~ /repeat/i }],
  ['0a no name: hits did not pass the naming tests', 0, sub { $_[0]{step} == 0 and $_[0]{none_reason} =~ /did not pass/ }],
  ['0b no name: no hits',                         0, sub { $_[0]{step} == 0 and $_[0]{none_reason} =~ /no hits/ }],
);

# marks that a step was passed over on the way: the name the gene got carries them, or (treeC) its provenance
my @MARKS = (
  ['omaX  OMA ortholog set aside: nothing supports it',              sub { $_[0]{tag}{omaX} }],
  ['omaC  OMA name withheld: best hit another gene AND other PANTHER family', sub { $_[0]{tag}{omaC} }],
  ['omaR  OMA many:1 pairing mostly rejected',                       sub { $_[0]{tag}{omaR} }],
  ['treeC step-6 name withheld: the PANTHER tree places it elsewhere', sub { ($_[0]{step} == 0 or $_[0]{step} > 6) and ($_[0]{S6_full_length_human_hit} // "") =~ /^(?:not used|not reached); withheld \(treeC\)|^not used: withheld \(treeC\)/ }],
);

my (@labels, %count, %example, %mark_count, %mark_example, %unclassified);
foreach my $arg (@ARGV) {
  my ($label, $file) = split /=/, $arg, 2;
  push @labels, $label;
  open my $fh, '<', $file or die "cant open $file $!\n";
  my @header;
  while (my $line = <$fh>) {
    next if $line =~ /^#/;
    chomp $line;
    my @f = split /\t/, $line, -1;
    if (!@header) { @header = @f; next; }
    my %row;
    @row{@header} = @f;
    my $pipeline = $row{Pipeline_name} // '';
    my ($step) = $pipeline =~ /\(step (\d+)\)\s*$/;
    my ($tags) = $pipeline =~ /\[([^\[\]]*)\]\s*\(step \d+\)\s*$/;
    $row{step} = $step // 0;
    $row{tag} = { map { my $tag = $_; ($tag => 1) } split /\|/, ($tags // '') };
    $row{native_kept} = ($row{Step} // '') =~ /^2 native/ ? 1 : 0;
    # a gene set that keeps its own names: the pipeline's rule is in the chosen step's column
    # ("not reached; the pipeline's pick without the native name: NAME -- RULE")
    $row{native_reason} = $row{Reason};
    if ($row{native_kept} and defined $step) {
      my ($column) = grep { my $name = $_; $name =~ /^S${step}_/ } @header;
      my ($rule) = ($row{$column} // '') =~ / -- (.*)$/;
      $row{Reason} = $rule if defined $rule;
    }
    $row{none_reason} = $row{step} == 0 ? ($row{Step} =~ /^none/ ? $row{Step} : ($row{S9_InterPro_domain} // '') . ' ' . ($row{Reason} // '')) : '';
    if ($row{step} == 0 and $row{none_reason} !~ /did not pass|no hits/) {
      # a native-name gene set: the pipeline's own outcome, from its evidence
      $row{none_reason} = (($row{Best_human_hit} // '') eq '' and ($row{PANTHER_best} // '') eq '') ? 'no hits' : 'did not pass';
    }
    my $example = join(' | ', $row{GroupId}, ($row{step} ? $pipeline : 'None'), ($row{step} ? $row{Reason} : ''));
    my @matched;
    foreach my $pathway (@PATHWAYS) {
      my ($id, $pathway_step, $test) = @$pathway;
      next if $id =~ /^2a/;   # counted below: it is the shown name, not the pipeline's
      if ($test->(\%row)) { push @matched, $id; last; }
    }
    if (@matched) { $count{$matched[0]}{$label}++; $example{$matched[0]}{$label} //= $example; }
    else          { $unclassified{$label}++; $example{'?? unclassified'}{$label} //= $example; $count{'?? unclassified'}{$label}++; }
    if ($PATHWAYS[0][2]->(\%row)) { $count{$PATHWAYS[0][0]}{$label}++; $example{$PATHWAYS[0][0]}{$label} //= join(' | ', $row{GroupId}, $row{Native_name}, $row{native_reason}); }
    foreach my $mark (@MARKS) {
      my ($id, $test) = @$mark;
      next unless $test->(\%row);
      $mark_count{$id}{$label}++;
      $mark_example{$id}{$label} //= $example;
    }
  }
  close $fh;
}

print join("\t", 'pathway', map { my $label = $_; ("$label genes", "$label example") } @labels), "\n";
foreach my $id ((map { my $pathway = $_; $pathway->[0] } @PATHWAYS), '?? unclassified') {
  next unless $count{$id};
  print join("\t", $id, map { my $label = $_; ($count{$id}{$label} // 0, $example{$id}{$label} // '') } @labels), "\n";
}
foreach my $mark (@MARKS) {
  my $id = $mark->[0];
  next unless $mark_count{$id};
  print join("\t", "MARK $id", map { my $label = $_; ($mark_count{$id}{$label} // 0, $mark_example{$id}{$label} // '') } @labels), "\n";
}
