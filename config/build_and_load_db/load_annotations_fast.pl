#!/usr/bin/perl
use strict;
use warnings;
use DBI;
use File::Basename;

# Usage: perl load_annotations_fast.pl genes.sqlite annotations.tsv
my $dbfile      = shift;
my $annot_file  = shift;

if (!$dbfile or !$annot_file) {
    die "Usage: $0 genes.sqlite annotations.tsv\n";
}

# Connect with AutoCommit off for one big transaction
my $dbh = DBI->connect(
    "dbi:SQLite:dbname=$dbfile", "", "",
    { RaiseError => 1, PrintError => 0, AutoCommit => 1 }
) or die $DBI::errstr;

# Speedup PRAGMAs for bulk load
$dbh->do("PRAGMA synchronous = OFF");
$dbh->do("PRAGMA journal_mode = MEMORY");
$dbh->do("PRAGMA temp_store = MEMORY");

# Now start transaction for bulk insert
$dbh->{AutoCommit} = 0;

# Prepare statements (reused)
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

my $sth_insert_feature_annotation = $dbh->prepare(q{
    INSERT INTO feature_annotation (feature_id, annotation_id, score, date)
    VALUES (?, ?, ?, ?)
});

my $sth_update_feature_annotation = $dbh->prepare(q{
    UPDATE feature_annotation
    SET score = ?, date = ?
    WHERE feature_annotation_id = ?
});

# Caches
my (%annotation_cache, %feature_cache, %feature_annotation_cache);

# Counters
my ($count_insert, $count_update, $count_annotations, $count_existing) = (0, 0, 0, 0);

# Parse header for metadata
my ($source, $source_version, $source_url, $accession_url, $date, $annotation_type);
open my $FH, '<', $annot_file or die "Cannot open file $annot_file: $!\n";

my $header_count = 0;
while (my $line = <$FH>) {
    chomp $line;
    if ($line =~ /^##/) {
        $header_count++;
        if    ($line =~ /^## Annotation Source: (.+)$/) { $source         = $1 }
        elsif ($line =~ /^## Annotation Source Version: (.+)$/)   { $source_version = $1 }
        elsif ($line =~ /^## Annotation Source URL: (.+)$/)       { $source_url     = $1 }
        elsif ($line =~ /^## Annotation Accession URL: (.+)$/)    { $accession_url  = $1 }
        elsif ($line =~ /^## Annotation Creation Date: (.+)$/)    { $date           = $1 }
        elsif ($line =~ /^## Annotation Type: (.+)$/)              { $annotation_type = $1 }
        next;
    }
    last; # hit data
}
die "


File has no metadata header

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
$source         //= die "## Annotation Source: is required in header\n";
$source_version //= die "## Annotation Source Version: is required in header\n";
$source_url     //= die "## Annotation Source URL is required in header\n";
$accession_url  //= die "## Annotation Accession URL: is required in header\n";
$date           //= die "## Annotation Creation Date: is required in header\n";
$annotation_type//= die "## Annotation Type: is required in header\n";

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

# Preload annotation cache
{
    my $sth = $dbh->prepare(q{
        SELECT annotation_id, annotation_accession, annotation_description
        FROM annotation
        WHERE annotation_source_id = ?
    });
    $sth->execute($source_id);
    while (my ($aid, $acc, $desc) = $sth->fetchrow_array) {
        $annotation_cache{ join('|', $acc // '', $desc // '', $source_id) } = $aid;
    }
}

# Preload feature cache
{
    my $sth = $dbh->prepare(q{ SELECT feature_id, feature_uniquename FROM feature });
    $sth->execute();
    while (my ($fid, $uname) = $sth->fetchrow_array) {
        $feature_cache{$uname} = $fid if defined $uname;
    }
}

# Preload feature_annotation cache
{
    my $sth = $dbh->prepare(q{
        SELECT feature_annotation_id, feature_id, annotation_id, score, date
        FROM feature_annotation
    });
    $sth->execute();
    while (my ($faid, $fid, $aid, $score, $d) = $sth->fetchrow_array) {
        $feature_annotation_cache{ join('|', $fid, $aid) } = {
            id    => $faid,
            score => $score // '',
            date  => $d // '',
        };
    }
}

# Process data lines
seek($FH, 0, 0);
while (my $line = <$FH>) {
    chomp $line;
    next if $line =~ /^##/ || $line =~ /^#/ || $line =~ /^\s*$/;

    my ($unique_name, $accession, $annotation_description, $hit_score) = split /\t/, $line, 4;
    $accession             //= '';
    $annotation_description//= '';
    $hit_score             //= '';

    $count_annotations++;

    # Get or insert annotation
    my $akey = join('|', $accession, $annotation_description, $source_id);
    my $annotation_id = $annotation_cache{$akey};
    if (!$annotation_id) {
        $sth_insert_annotation->execute($source_id, $accession, $annotation_description);
        $annotation_id = $dbh->sqlite_last_insert_rowid();
        $annotation_cache{$akey} = $annotation_id;
    }

    # Lookup feature_id
    my $feature_id = $feature_cache{$unique_name};
    unless (defined $feature_id) {
        warn "NOT Found: feature: $unique_name\n";
        next;
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
        if (($existing->{score} // '') ne ($hit_score // '') ||
            ($existing->{date} // '')  ne ($date // '')) {
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
$sth_insert_feature_annotation->finish();
$sth_update_feature_annotation->finish();

$dbh->disconnect;


print "Total Annotations processed: $count_annotations\n";
print "Inserted feature_annotation rows: $count_insert\n";
print "Updated feature_annotation rows: $count_update\n";
print "Already existing: $count_existing\n";
print "Done.\n";

