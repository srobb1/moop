#!/usr/bin/perl
use strict;
use warnings;
use Digest::MD5 qw(md5_hex);

# Is this gene set one of the OMA reference genomes? Compares protein sequences exactly.
#
#   find_reference_genome.pl REFERENCE_DB_DIR target_protein.aa.fa [id_map_out.tsv]
#
# REFERENCE_DB_DIR is an OMA run's DB/ (one <CODE>.fa per reference genome). Prints one line
# per reference with any identical proteins, best first:
#   CODE <TAB> percent of the target's proteins identical to one of CODE's <TAB> identical <TAB> target proteins
# Exit code 0 when a reference holds >= 50% of the target's proteins (the gene set IS that
# reference genome, e.g. a RefSeq/Ensembl annotation that OMA also exported), else 1.
#
# With id_map_out.tsv, also writes for the best reference: reference entry id (first word of
# its FASTA header, e.g. NEMVE000123) <TAB> target protein id, for every identical pair --
# how OMA's results for the reference are put back on the gene set's own ids.
#
# Measured Sep 2026 against BRAFL_CALMI_CAPTE_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE_NEMVE:
# Nematostella RS_101 100% NEMVE, Drosophila FB_Rel_6.54 99.5% DROME (both references);
# Nematostella NV2 17.9% NEMVE (same species, other annotation); bats <= 2% HUMAN.

my $IS_REFERENCE = 50;   # percent

my $usage = "usage: $0 REFERENCE_DB_DIR target_protein.aa.fa [id_map_out.tsv]\n";
my $db_dir  = shift or die $usage;
my $target  = shift or die $usage;
my $map_out = shift;

my %reference_ids;   # sequence md5 -> CODE -> [ reference entry ids ]
foreach my $fasta (glob "$db_dir/*.fa") {
  my ($code) = $fasta =~ m{([^/]+)\.fa$};
  my $sequences = read_fasta($fasta);
  foreach my $id (keys %$sequences) {
    push @{$reference_ids{md5_hex($sequences->{$id})}{$code}}, $id;
  }
}
die "no reference FASTAs in $db_dir\n" unless %reference_ids;

my $target_sequences = read_fasta($target);
my $total = scalar keys %$target_sequences;
die "no proteins in $target\n" unless $total;

my (%identical, %pairs);
foreach my $target_id (keys %$target_sequences) {
  my $by_code = $reference_ids{md5_hex($target_sequences->{$target_id})} or next;
  foreach my $code (keys %$by_code) {
    $identical{$code}++;
    foreach my $reference_id (@{$by_code->{$code}}) {
      push @{$pairs{$code}}, [$reference_id, $target_id];
    }
  }
}

my @ranked = sort { $identical{$b} <=> $identical{$a} or $a cmp $b } keys %identical;
foreach my $code (@ranked) {
  printf "%s\t%.1f\t%d\t%d\n", $code, 100 * $identical{$code} / $total, $identical{$code}, $total;
}

my $best = $ranked[0];
my $is_reference = defined $best && 100 * $identical{$best} / $total >= $IS_REFERENCE;
if (defined $map_out and $is_reference) {
  open my $map_fh, '>', $map_out or die "cant write $map_out $!\n";
  foreach my $pair (sort { $a->[0] cmp $b->[0] or $a->[1] cmp $b->[1] } @{$pairs{$best}}) {
    print $map_fh join("\t", @$pair), "\n";
  }
  close $map_fh;
}
exit($is_reference ? 0 : 1);

# id (first word of the header) -> sequence, upper case, without stop/gap characters
sub read_fasta {
  my ($file) = @_;
  my (%sequence, $id);
  my $open = $file =~ /\.gz$/ ? "gzip -dc '$file' |" : "< $file";
  open my $fh, $open or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    if ($line =~ /^>(\S+)/) {
      $id = $1;
      $sequence{$id} = '';
    } elsif (defined $id) {
      $line =~ s/[\s*.]//g;
      $sequence{$id} .= uc $line;
    }
  }
  close $fh;
  return \%sequence;
}
