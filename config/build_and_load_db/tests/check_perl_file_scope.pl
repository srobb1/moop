#!/usr/bin/perl
use strict;
use warnings;
use File::Find;
use FindBin;
use Cwd qw(abs_path);

# check_perl_file_scope.pl [FILE ...]
#
# Fails (exit 1) on the Perl "file-scope assignment" trap: a file-level variable that is given
# its value AFTER the script has started working, and that a subroutine reads.
#
#   main_work();                         # runs first: uses %TABLE ...
#   my %TABLE = (Pfam => 1);             # ... which is only assigned when execution gets here
#   sub main_work { ... $TABLE{...} }    # so main_work saw an EMPTY hash -- no error, no warning
#
# "my %TABLE" declares the name at compile time (so strict is satisfied) but assigns it only at
# run time, when execution reaches the line. It silently broke assign_gene_names_v2.pl three
# times on 2026-09-25 (OMA pair ranks, InterProScan E-value analyses, ...). The layout that
# makes it impossible: constants and state at file level, all work in main(), and main();
# as the last line -- see the LAYOUT note in assign_gene_names_v2.pl.
#
# Default: every .pl/.pm under config/build_and_load_db (not old/, not tests/). A file-level
# statement is anything outside a sub at brace depth 0; "work" is any such statement that is
# not use/no/package, a declaration, a sub, BEGIN/END, or "1;". The check is textual, so
# braces inside strings can confuse it -- it errs towards reporting, never towards passing
# code it could not read.

my @files = @ARGV;
if (!@files) {
  my $root = abs_path("$FindBin::Bin/..");   # resolved: "tests/.." would match the tests/ skip below
  find({ no_chdir => 1, wanted => sub {
    return if m{/(?:old|tests)/};
    push @files, $File::Find::name if /\.(?:pl|pm)$/;
  } }, $root);
}

my $problems = 0;
foreach my $file (sort @files) {
  $problems += check_file($file);
}
die "no Perl files found to check\n" unless @files;   # a check that checked nothing must not pass
print $problems ? "$problems problem(s)\n" : "OK: no file-scope assignment after work, in " . scalar(@files) . " file(s)\n";
exit($problems ? 1 : 0);

sub check_file {
  my ($file) = @_;
  open my $fh, '<', $file or die "cant read $file: $!\n";
  my @statements;          # [ first line number, text ]
  my ($text, $start, $depth, $in_pod) = (undef, 0, 0, 0);
  while (my $line = <$fh>) {
    last if $line =~ /^__(?:END|DATA)__\b/;
    if ($line =~ /^=[a-zA-Z]/) { $in_pod = 1; }
    if ($in_pod) { $in_pod = 0 if $line =~ /^=cut\b/; next; }
    my $code = strip_comment($line);
    if (!defined $text) {
      next if $code !~ /\S/;
      ($text, $start) = ('', $.);
    }
    $text .= $code;
    $depth += () = $code =~ /(?<!\\)\{/g;
    $depth -= () = $code =~ /(?<!\\)\}/g;
    if ($depth <= 0 and $code =~ /[;}]\s*$/) {
      push @statements, [$start, $text];
      ($text, $depth) = (undef, 0);
    }
  }
  close $fh;
  push @statements, [$start, $text] if defined $text;

  my (@subs, @late, $first_work);
  foreach my $statement (@statements) {
    my ($line, $body) = @$statement;
    if ($body =~ /^\s*sub\s+\w+/) { push @subs, $body; next; }
    next if $body =~ /^\s*(?:use|no|package|BEGIN|END|require)\b/ or $body =~ /^\s*1;\s*$/;
    if ($body =~ /^\s*(?:my|our)\s*(\([^)]*\)|[\$\@%]\w+)\s*(=(?![=~]))?/) {
      my ($names, $assigns) = ($1, $2);
      push @late, [$line, $names] if $assigns and defined $first_work;
      next;
    }
    $first_work //= $line;
  }

  my $all_subs = join "\n", @subs;
  my $found = 0;
  foreach my $late (@late) {
    my ($line, $names) = @$late;
    foreach my $variable ($names =~ /([\$\@%]\w+)/g) {
      my ($sigil, $name) = $variable =~ /^(.)(\w+)$/;
      my $used = $sigil eq '%' ? $all_subs =~ /(?:%\Q$name\E\b|\$\Q$name\E\s*\{|\@\Q$name\E\s*\{)/
               : $sigil eq '@' ? $all_subs =~ /(?:\@\Q$name\E\b|\$\Q$name\E\s*\[|\$#\Q$name\E\b)/
               :                 $all_subs =~ /\$\Q$name\E\b(?!\s*[\[{])/;
      next unless $used;
      print "$file:$line: $variable is assigned at file level after the script has started "
          . "working (line $first_work), and a sub reads it -- the sub sees it EMPTY if called "
          . "before this line. Move the work into main() and call main(); last.\n";
      $found++;
    }
  }
  return $found;
}

# a line without its comment; keeps '#' inside quotes and regexes like s#..#..# (approximate)
sub strip_comment {
  my ($line) = @_;
  return "\n" if $line =~ /^\s*#/;
  $line =~ s/\s+#(?![^'"]*['"][^'"]*$).*$//;
  return $line;
}
