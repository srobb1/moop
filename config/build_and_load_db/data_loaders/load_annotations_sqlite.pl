#!/usr/bin/perl
use strict;
use warnings;
use DBI;
use File::Basename;
use POSIX qw(_exit);

# Usage: perl load_annotations_sqlite.pl genes.sqlite annotations1.tsv [annotations2.tsv ...]
#
# Loads one or more MOOP annotation TSVs into the shared per-organism
# organism.sqlite. The feature / feature_annotation caches are built ONCE
# for the whole invocation rather than once per file: organism.sqlite is
# shared across every geneset of an organism, so for organisms with several
# genesets these tables can be large, and reloading them per file made a
# geneset's ~35 annotation files scale as O(files * db_size) instead of
# O(db_size + total_rows).
my $dbfile      = shift;
my @annot_files = @ARGV;

die "Usage: $0 genes.sqlite annotations.tsv [annotations2.tsv ...]\n"
    if !$dbfile or !@annot_files;

# Connect with AutoCommit off for one big transaction
my $dbh = DBI->connect(
    "dbi:SQLite:dbname=$dbfile", "", "",
    { RaiseError => 1, PrintError => 0, AutoCommit => 1 }
) or die $DBI::errstr;

# Foreign keys are OFF by default and the setting is per-CONNECTION, so it cannot
# live in create_schema_sqlite.sql. Without it every FK and ON DELETE CASCADE in
# the schema is decorative.
$dbh->do("PRAGMA foreign_keys = ON");

# Speedup PRAGMAs for bulk load.
#
# WARNING: synchronous=OFF with journal_mode=MEMORY means a crash or power loss
# mid-load can leave the database CORRUPT, not merely incomplete -- there is no
# on-disk rollback journal to recover from. Acceptable only because these
# databases are rebuildable from source. Keep the previous organism.sqlite until
# the load finishes and its checks pass.
$dbh->do("PRAGMA synchronous = OFF");
$dbh->do("PRAGMA journal_mode = MEMORY");
$dbh->do("PRAGMA temp_store = MEMORY");

# Now start transaction for bulk insert
$dbh->{AutoCommit} = 0;

# Prepare statements (reused across all files)
my $sth_get_annotation_source = $dbh->prepare(q{
    SELECT annotation_source_id, annotation_accession_url, annotation_source_url, annotation_type
    FROM annotation_source
    WHERE annotation_source_name = ? AND annotation_source_version = ?
});

my $sth_insert_annotation_source = $dbh->prepare(q{
    INSERT INTO annotation_source (annotation_source_name, annotation_source_version, annotation_accession_url, annotation_source_url, annotation_type)
    VALUES (?, ?, ?, ?, ?)
});

my $sth_update_annotation_source = $dbh->prepare(q{
    UPDATE annotation_source
    SET annotation_accession_url = ? , annotation_source_url = ?, annotation_type = ?
    WHERE annotation_source_id = ?
});

my $sth_insert_annotation = $dbh->prepare(q{
    INSERT INTO annotation (annotation_source_id, annotation_accession, annotation_description)
    VALUES (?, ?, ?)
});

my $sth_get_annotations_for_source = $dbh->prepare(q{
    SELECT annotation_id, annotation_accession, annotation_description
    FROM annotation
    WHERE annotation_source_id = ?
});

my $sth_insert_feature_annotation = $dbh->prepare(q{
    INSERT INTO feature_annotation (feature_id, annotation_id, score, date)
    VALUES (?, ?, ?, ?)
});

my $sth_update_feature_annotation = $dbh->prepare(q{
    UPDATE feature_annotation
    SET score = ?, date = ?
    WHERE feature_annotation_id = ?
});

# Caches - shared across every file processed in this invocation
my (%annotation_cache, %feature_cache, %feature_annotation_cache,
    %feature_type_cache, %parent_cache, %source_cache_loaded, %ambiguous_uniquename);

my $count_not_found = 0;

# Preload feature cache once for the whole run
{
    my $sth = $dbh->prepare(q{
        SELECT feature_id, feature_uniquename, feature_type, parent_feature_id FROM feature
    });
    $sth->execute();
    while (my ($fid, $uname, $ftype, $parent_fid) = $sth->fetchrow_array) {
        if (defined $uname) {
            # feature_uniquename is unique per GENE SET, not globally, so the same
            # ID can legitimately exist in two gene sets. An annotation file names
            # features by uniquename alone and carries no gene set, so such an ID
            # is genuinely ambiguous -- record it and refuse it rather than
            # silently annotating whichever one happened to load last.
            if (exists $feature_cache{$uname}) {
                $ambiguous_uniquename{$uname} = 1;
            } else {
                $feature_cache{$uname} = $fid;
            }
        }
        $feature_type_cache{$fid} = $ftype  if defined $ftype;
        $parent_cache{$fid}       = $parent_fid if defined $parent_fid;
    }
}

# Preload feature_annotation cache once for the whole run
{
    my $sth = $dbh->prepare(q{
        SELECT feature_annotation_id, feature_id, annotation_id, score, date FROM feature_annotation
    });
    $sth->execute();
    while (my ($faid, $fid, $aid, $score, $d) = $sth->fetchrow_array) {
        # Keep score as-is (possibly undef): NULL and 0.0 are different facts.
        $feature_annotation_cache{ join('|', $fid, $aid) } = {
            id    => $faid,
            score => $score,
            date  => $d // '',
        };
    }
}

# Walk up parent chain to find the mRNA/transcript to associate annotations with.
# For eukaryotes:  protein -> CDS -> mRNA  (returns mRNA)
# For bacteria:    protein -> CDS -> gene  (no mRNA; returns CDS, one level above protein)
# For mRNA input:  returns immediately
#
# Memoized per starting feature, and guarded against cycles in
# parent_feature_id (seen in the wild: T2G-path genesets whose protein IDs
# have no ".p<N>" ORF suffix make parse_transcript2gene_to_MOOP_TSV.pl
# emit a protein row with the same uniquename as its own parent mRNA row,
# which load_genes_sqlite.pl then collapses into one self-parented row —
# parent_feature_id = feature_id. Without this guard that's an infinite
# loop; every annotation row for such a feature hits it, so this can hang
# forever within seconds of starting. On a cycle we just attach the
# annotation to the starting feature itself instead of hanging.
my %target_cache;
sub find_annotation_target {
    my ($fid) = @_;
    return $target_cache{$fid} if exists $target_cache{$fid};
    my $cur  = $fid;
    my $last = $fid;
    my %visited;
    while (defined $cur) {
        if ($visited{$cur}++) {
            warn "WARNING: cyclic parent_feature_id chain detected starting at feature $fid (loop back to $cur) — attaching annotations directly to $fid\n";
            return $target_cache{$fid} = $fid;
        }
        my $type = $feature_type_cache{$cur} // '';
        return $target_cache{$fid} = $cur if $type eq 'mRNA' || $type eq 'transcript';
        $last = $cur;
        $cur  = $parent_cache{$cur};
    }

    # No mRNA/transcript anywhere in the chain. Organelle and bacterial genes go
    # gene -> CDS with no transcript level, so this is normal, not an error.
    #
    # Attach to the ROOT reached (the gene), not the input's direct parent. The
    # old fallback returned the direct parent, which stranded mitochondrial
    # annotations on the CDS -- 13 YP_ features were enough to put 'cds' in the
    # annotated-types list for a whole organism and render an empty CDS card on
    # every transcript page. A gene with no isoforms IS the unit.
    return $target_cache{$fid} = $last;
}

# Totals across all files in this invocation
my ($total_annotations, $total_insert, $total_update, $total_existing) = (0, 0, 0, 0);

for my $annot_file (@annot_files) {
    load_one_file($annot_file);
}

# Commit safely
eval { $dbh->commit; 1 }
    or do {
        my $err = $@ || 'Unknown error';
        warn "Transaction commit failed: $err\nRolling back...\n";
        eval { $dbh->rollback };
    };

# Now turn AutoCommit back on
$dbh->{AutoCommit} = 1;

# Restore defaults for future ops
$dbh->do("PRAGMA journal_mode = DELETE");
$dbh->do("PRAGMA synchronous = FULL");

$sth_get_annotation_source->finish();
$sth_insert_annotation_source->finish();
$sth_update_annotation_source->finish();
$sth_insert_annotation->finish();
$sth_get_annotations_for_source->finish();
$sth_insert_feature_annotation->finish();
$sth_update_feature_annotation->finish();

$dbh->disconnect;

print "Total Annotations processed: $total_annotations\n";
print "Inserted feature_annotation rows: $total_insert\n";
print "Updated feature_annotation rows: $total_update\n";
print "Already existing: $total_existing\n";
print "Features not found: $count_not_found\n";

# ----------------------------------------------------------------------
# Load sanity check. A load that attaches nothing is a FAILED load even though
# every statement succeeded -- that is how an organism ended up with 306,781
# annotations and zero features while this script printed "Done."
#
# Exits via _exit() rather than die() for the same reason the success path does:
# global destruction of these caches has been seen to segfault.
# ----------------------------------------------------------------------
my $attached = $total_insert + $total_update + $total_existing;

if ($total_annotations && !$attached) {
    print STDERR "\n!! LOAD FAILED: $total_annotations annotation line(s) read, but NONE\n"
               . "!! could be attached to a feature. Every feature lookup missed.\n"
               . "!! Check that the gene set was loaded into $dbfile BEFORE the\n"
               . "!! annotations, and that the IDs match feature_uniquename.\n";
    STDOUT->flush; STDERR->flush;
    _exit(1);
}

if ($count_not_found) {
    my $pct = sprintf '%.1f', 100 * $count_not_found / ($total_annotations || 1);
    print STDERR "\n!! WARNING: $count_not_found of $total_annotations annotation line(s) "
               . "($pct%) referenced a\n!! feature not in the database, and were skipped. A "
               . "sequence-ID mismatch between\n!! the annotation file and the gene set is the "
               . "usual cause (scripts/check_sequence_id_match.sh).\n\n";
}

print "Done.\n";

## All work is committed and the DB handle is already disconnected above, so
## skip Perl's normal global destruction here: with the large caches this
## script builds (feature/feature_annotation tables for genesets with tens
## of thousands of rows), destroying them via Perl's ordinary teardown has
## been observed to segfault on process exit (DBD::SQLite + large nested
## hash cleanup) even though all data was already written successfully.
## _exit() skips DESTROY/END processing entirely and just exits cleanly.
STDOUT->flush;
_exit(0);

sub load_one_file {
    my ($annot_file) = @_;

    my ($source, $source_version, $source_url, $accession_url, $date, $annotation_type);
    open my $FH, '<', $annot_file or die "Cannot open file $annot_file: $!\n";

    my $header_count = 0;
    while (my $line = <$FH>) {
        chomp $line;
        if ($line =~ /^##/) {
            $header_count++;
            # NOTE the \s*(.+?)\s*$ form. A plain (.+)$ also captures trailing
            # spaces, which is how "Ensembl Homo sapiens " and "Ensembl Homo sapiens"
            # both ended up in annotation_source: two sources the user cannot tell
            # apart, each holding half the annotations, and picking one silently
            # searches only that half.
            if    ($line =~ /^## Annotation Source:\s*(.+?)\s*$/)         { $source          = $1 }
            elsif ($line =~ /^## Annotation Source Version:\s*(.+?)\s*$/) { $source_version  = $1 }
            elsif ($line =~ /^## Annotation Source URL:\s*(.+?)\s*$/)     { $source_url      = $1 }
            elsif ($line =~ /^## Annotation Accession URL:\s*(.+?)\s*$/)  { $accession_url   = $1 }
            elsif ($line =~ /^## Annotation Creation Date:\s*(.+?)\s*$/)  { $date            = $1 }
            elsif ($line =~ /^## Annotation Type:\s*(.+?)\s*$/)           { $annotation_type = $1 }
            next;
        }
        last; # hit data
    }
    die "


File $annot_file has no metadata header

Example:

## Annotation Source: SwissProt
## Annotation Source Version: 2024_06
## Annotation Source URL: https://www.uniprot.org
## Annotation Accession URL: https://www.uniprot.org/uniprotkb/
## Annotation Type: Homologs
## Annotation Creation Date: 2025-05-22
## Gene Accession Accession_Description Score

These are required for a load

" if !$header_count ;

    # Normalise metadata
    $source         //= die "## Annotation Source: is required in header of $annot_file\n";
    $source_version //= die "## Annotation Source Version: is required in header of $annot_file\n";
    $source_url     //= die "## Annotation Source URL is required in header of $annot_file\n";
    $accession_url  //= die "## Annotation Accession URL: is required in header of $annot_file\n";
    $date           //= die "## Annotation Creation Date: is required in header of $annot_file\n";
    $annotation_type//= die "## Annotation Type: is required in header of $annot_file\n";

    # Ensure annotation_source exists
    $sth_get_annotation_source->execute($source, $source_version);
    my ($source_id, $existing_accession_url, $existing_source_url, $existing_type) =
        $sth_get_annotation_source->fetchrow_array;
    if (!$source_id) {
        $sth_insert_annotation_source->execute($source, $source_version, $accession_url, $source_url, $annotation_type);
        $source_id = $dbh->sqlite_last_insert_rowid();
        print "Inserted annotation_source: $source ($source_version) -> id $source_id\n";
    } else {
        print "Found annotation_source id $source_id for $source ($source_version)\n";
        if (($existing_accession_url // '') ne ($accession_url // '') ||
            ($existing_source_url // '') ne ($source_url // '') ||
            ($existing_type // '') ne ($annotation_type // '')) {
            $sth_update_annotation_source->execute($accession_url, $source_url, $annotation_type, $source_id);
            print "Updated annotation_source id $source_id\n";
        }
    }

    # Preload annotation cache for this source, once per distinct source per run
    unless ($source_cache_loaded{$source_id}++) {
        $sth_get_annotations_for_source->execute($source_id);
        while (my ($aid, $acc, $desc) = $sth_get_annotations_for_source->fetchrow_array) {
            $annotation_cache{ join('|', $acc // '', $desc // '', $source_id) } = $aid;
        }
    }

    # Counters for this file
    my ($count_insert, $count_update, $count_annotations, $count_existing) = (0, 0, 0, 0);

    # Process data lines
    seek($FH, 0, 0);
    while (my $line = <$FH>) {
        chomp $line;
        next if $line =~ /^##/ || $line =~ /^#/ || $line =~ /^\s*$/;

        my ($unique_name, $accession, $annotation_description, $hit_score) = split /\t/, $line, 4;

        # Trim every field. Untrimmed values are silent poison here: a trailing
        # space on $unique_name makes the feature lookup miss, and one on
        # $accession/$annotation_description creates a second, duplicate
        # annotation row that looks identical to the user.
        foreach my $field ($unique_name, $accession, $annotation_description, $hit_score) {
            $field = '' if !defined $field;
            $field =~ s/^\s+|\s+$//g;
        }

        # feature_annotation.score is REAL and nullable. Annotation types that
        # carry no score write "-" (or an empty field); those must become a real
        # NULL, NOT 0.0 -- zero is the strongest possible e-value, so loading "-"
        # as a number would make every score-less row look like the most
        # significant hit on the page. Anything non-numeric is treated the same.
        if ($hit_score eq '' || $hit_score eq '-'
            || $hit_score !~ /^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/) {
            $hit_score = undef;
        }

        $count_annotations++;

        if ($ambiguous_uniquename{$unique_name}) {
            die "\n!! AMBIGUOUS: '$unique_name' exists in more than one gene set in "
              . "$dbfile.\n!! An annotation file identifies features by uniquename "
              . "alone, so there is no way to tell which one is meant. Load this "
              . "file against a database holding a single gene set, or make the IDs "
              . "distinct.\n";
        }

        # Look the feature up FIRST.
        #
        # This used to happen *after* the annotation row was inserted, so a file
        # loaded against a database whose features are missing left every
        # annotation behind with nothing pointing at it -- and still exited 0
        # reporting success. That is how one organism ended up with 306,781
        # annotations, 57 sources and zero features.
        my $feature_id = $feature_cache{$unique_name};
        unless (defined $feature_id) {
            $count_not_found++;
            warn "NOT Found: feature: $unique_name\n" if $count_not_found <= 20;
            warn "... further 'NOT Found' warnings suppressed\n" if $count_not_found == 21;
            next;
        }
        $feature_id = find_annotation_target($feature_id);

        # Get or insert annotation (only now that we know it can be attached)
        my $akey = join('|', $accession, $annotation_description, $source_id);
        my $annotation_id = $annotation_cache{$akey};
        if (!$annotation_id) {
            $sth_insert_annotation->execute($source_id, $accession, $annotation_description);
            $annotation_id = $dbh->sqlite_last_insert_rowid();
            $annotation_cache{$akey} = $annotation_id;
        }

        # Insert/update feature_annotation
        my $fakey = join('|', $feature_id, $annotation_id);
        my $existing = $feature_annotation_cache{$fakey};

        if (!$existing) {
            $sth_insert_feature_annotation->execute($feature_id, $annotation_id, $hit_score, $date);
            my $faid = $dbh->sqlite_last_insert_rowid();
            $feature_annotation_cache{$fakey} = { id => $faid, score => $hit_score, date => $date };
            $count_insert++;
        } else {
            # Compare score NUMERICALLY. A string compare would see the stored
            # REAL round-trip differently from the text in the file (1.89e-314
            # comes back as 1.890000000243e-314) and rewrite every row every run.
            my $old_score = $existing->{score};
            my $score_changed =
                   ((defined $old_score) xor (defined $hit_score))
                || (defined $old_score && defined $hit_score && $old_score != $hit_score);

            if ($score_changed || ($existing->{date} // '') ne ($date // '')) {
                $sth_update_feature_annotation->execute($hit_score, $date, $existing->{id});
                $existing->{score} = $hit_score;
                $existing->{date}  = $date;
                $count_update++;
            } else {
                $count_existing++;
            }
        }
    }
    close $FH;

    print "  $annot_file: $count_annotations annotations, $count_insert inserted, $count_update updated, $count_existing unchanged\n";

    $total_annotations += $count_annotations;
    $total_insert       += $count_insert;
    $total_update       += $count_update;
    $total_existing     += $count_existing;
}
