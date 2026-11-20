#!/usr/bin/env perl
## patch_genome_names.pl -- set genome.genome_name in every data/*/organism.sqlite from metadata.yaml,
## using the same rule as the parsers (analysis_parsers/GenomeName.pm), without rebuilding anything.
##
## Why (2026-11-20): genome_name used to hold simrbase-prefix (SMED, PMZ). The parsers now write the
## genome-name from metadata.yaml (commit 4ccba1b1), but that only reaches a database on its next build.
## A full run_all_v2.sh --reload would rebuild every gene set to change one column; this changes the
## column in place.
##
## Usage:
##   (run from build_and_load_db/v2, or set REPO to it)
##   scripts/patch_genome_names.pl            # dry run: before/after table, writes nothing
##   scripts/patch_genome_names.pl --apply    # write the changes
##   scripts/patch_genome_names.pl [--apply] Organism_one Organism_two ...   # just these organisms
##
## Safety:
##   * Each organism is patched under its build lock (data/<org>/.moop_build.lock, the flock the
##     build job holds for its whole run). If a build holds it, that organism is SKIPPED, not waited on.
##   * A genome row is matched to its assembly the way paths.sh data_assembly() names it: the
##     genome-accession of the assembly's gene sets, else the assembly directory name.
##   * Gene sets of one assembly that give different names: the row is left alone and reported.
##   * A genome row with no assembly in $GENOMES: left alone and reported.
## After --apply, run copy2moop to put the files live.

use strict;
use warnings;
use DBI;
use Fcntl qw(:flock);
use File::Basename qw(dirname basename);
use FindBin qw($Bin);
use lib "$Bin/../analysis_parsers";
use GenomeName qw(genome_name_from_metadata);

my $apply = 0;
my @only;
for my $arg (@ARGV) {
    if    ($arg eq '--apply')                { $apply = 1 }
    elsif ($arg eq '-h' or $arg eq '--help') { print_usage(); exit 0 }
    elsif ($arg =~ /^-/)                     { die "Unknown option $arg\n" }
    else                                     { push @only, $arg }
}

my $GENOMES = $ENV{GENOMES} // '/n/sci/SCI-004223-SBGENOMES/genomes/v2';
## data/ comes from REPO, else the current directory -- NOT from $Bin: scripts/ is a symlink into the
## git checkout and FindBin resolves it, which walks out of the v2 tree (same trap as copy2moop_v2.sh).
my $REPO    = $ENV{REPO} // '.';
my $DATA    = "$REPO/data";
-d $GENOMES or die "No GENOMES dir $GENOMES\n";
-d $DATA    or die "No data dir $DATA: run from build_and_load_db/v2 or set REPO\n";

my @orgs = @only ? @only
         : sort map { basename(dirname($_)) } glob("$DATA/*/organism.sqlite");

my @rows;        # [org, accession, old, new, status]
my %count;
for my $org (@orgs) {
    my $db_path = "$DATA/$org/organism.sqlite";
    unless (-f $db_path) { push @rows, [$org, '-', '-', '-', 'no organism.sqlite']; $count{missing}++; next }

    open my $LOCK, '>>', "$DATA/$org/.moop_build.lock" or die "Can't open lock for $org: $!\n";
    unless (flock($LOCK, LOCK_EX | LOCK_NB)) {
        push @rows, [$org, '-', '-', '-', 'SKIPPED: build running (lock held)'];
        $count{locked}++;
        close $LOCK;
        next;
    }

    my $want = names_for_organism($org);    # accession -> {name => ..., error => ...}

    my $dbh = DBI->connect("dbi:SQLite:dbname=$db_path", '', '',
                           { RaiseError => 1, AutoCommit => 1, sqlite_open_flags => 0 });
    $dbh->do('PRAGMA busy_timeout = 30000');
    my $genomes = $dbh->selectall_arrayref(
        'SELECT genome_id, genome_accession, genome_name FROM genome ORDER BY genome_id');

    my @updates;
    for my $genome (@$genomes) {
        my ($id, $acc, $old) = @$genome;
        my $info = $want->{$acc};
        if (!$info) {
            push @rows, [$org, $acc, $old, '?', 'no assembly in GENOMES for this accession'];
            $count{unmatched}++;
        } elsif ($info->{error}) {
            push @rows, [$org, $acc, $old, '?', $info->{error}];
            $count{conflict}++;
        } elsif ($info->{name} eq $old) {
            push @rows, [$org, $acc, $old, $old, 'same'];
            $count{same}++;
        } else {
            push @rows, [$org, $acc, $old, $info->{name}, $apply ? 'UPDATED' : 'would update'];
            push @updates, [$info->{name}, $id];
            $count{change}++;
        }
    }

    if ($apply and @updates) {
        $dbh->begin_work;
        my $sth = $dbh->prepare('UPDATE genome SET genome_name = ? WHERE genome_id = ?');
        for my $update (@updates) { $sth->execute(@$update) }
        $dbh->commit;
    }
    $dbh->disconnect;
    close $LOCK;
}

## report
my @head = ('organism', 'genome_accession', 'genome_name now', 'genome_name new', 'status');
my @width = map { length $_ } @head;
for my $row (@rows) {
    for my $i (0 .. $#$row) {
        my $len = length($row->[$i] eq '' ? '(empty)' : $row->[$i]);
        $width[$i] = $len if $len > $width[$i];
    }
}
my $fmt = join('  ', map { "%-${_}s" } @width) . "\n";
printf $fmt, @head;
printf $fmt, map { '-' x $_ } @width;
for my $row (@rows) { printf $fmt, map { $_ eq '' ? '(empty)' : $_ } @$row }
print "\n";
printf "%s: %d changed, %d already right, %d skipped (build running), %d conflicting names, %d unmatched rows, %d missing dbs\n",
    ($apply ? 'APPLIED' : 'DRY RUN'), map { $count{$_} // 0 } qw(change same locked conflict unmatched missing);
print "Nothing written. Rerun with --apply to write these changes.\n" if !$apply and $count{change};
print "Now run copy2moop to put the changed organism.sqlite files live.\n" if $apply and $count{change};

## accession (as data_assembly() in paths.sh names the assembly) -> wanted genome_name
sub names_for_organism {
    my ($org) = @_;
    my %by_acc;
    for my $asm_dir (sort grep { -d $_ } glob("$GENOMES/$org/*")) {
        my $asm = basename($asm_dir);
        my @metas = sort grep { -f $_ } glob("$asm_dir/*/metadata.yaml");
        next unless @metas;

        my (%accs, %names);
        for my $meta (@metas) {
            my $acc = read_accession($meta);
            $accs{$acc} = 1 if $acc ne '' and $acc ne 'NA';
            push @{ $names{ genome_name_from_metadata($meta) } }, $meta;
        }
        my @acc_list = sort keys %accs;
        my $key = @acc_list ? $acc_list[0] : $asm;
        my %info;
        if (@acc_list > 1) {
            $info{error} = "gene sets of $asm disagree on genome-accession: @acc_list";
        } elsif (keys %names > 1) {
            $info{error} = "gene sets of $asm disagree on genome-name: "
                         . join(' | ', map { ($_ eq '' ? '(empty)' : $_) . ' <- ' . join(',', map { basename(dirname($_)) } @{ $names{$_} }) } sort keys %names);
        } else {
            ($info{name}) = keys %names;
        }
        if ($by_acc{$key}) {
            $by_acc{$key} = { error => "two assembly dirs map to accession $key" };
        } else {
            $by_acc{$key} = \%info;
        }
    }
    return \%by_acc;
}

## genome-accession as the parsers read it: the first word of the value.
sub read_accession {
    my ($meta) = @_;
    open my $META, '<', $meta or die "Can't open $meta: $!\n";
    my $acc = '';
    while (my $line = <$META>) {
        if ($line =~ /^genome-accession:[ \t]*(\S*)/) { $acc = $1; last }
    }
    close $META;
    $acc =~ s/^(["'])(.*)\1$/$2/;
    return $acc;
}

sub print_usage {
    print "Usage: $0 [--apply] [Organism ...]\n"
        . "  Dry run by default: prints genome_name now vs from metadata.yaml (GenomeName.pm rule).\n"
        . "  --apply writes the changes into data/<org>/organism.sqlite (skips organisms with a build running).\n";
}
