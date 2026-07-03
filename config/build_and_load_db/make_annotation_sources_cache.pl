#!/usr/bin/perl
use strict;
use warnings;
use DBI;

# Queries the DB for annotation counts grouped by type and source,
# writes annotation_sources_cache.json.
# Usage: perl make_annotation_sources_cache.pl organism.sqlite annotation_sources_cache.json

my $dbfile  = shift or die "Usage: $0 organism.sqlite annotation_sources_cache.json\n";
my $outfile = shift or die "Usage: $0 organism.sqlite annotation_sources_cache.json\n";

my $dbh = DBI->connect("dbi:SQLite:dbname=$dbfile", "", "", { RaiseError => 1 });

my $sth = $dbh->prepare(q{
    SELECT   src.annotation_type,
             src.annotation_source_name,
             COUNT(DISTINCT fa.feature_id) AS cnt
    FROM     feature_annotation fa
    JOIN     annotation ann ON fa.annotation_id = ann.annotation_id
    JOIN     annotation_source src ON ann.annotation_source_id = src.annotation_source_id
    GROUP BY src.annotation_type, src.annotation_source_name
    ORDER BY src.annotation_type, cnt DESC
});
$sth->execute;

my (%by_type, @type_order);
while (my ($type, $name, $cnt) = $sth->fetchrow_array) {
    push @type_order, $type unless exists $by_type{$type};
    push @{ $by_type{$type} }, { name => $name, count => $cnt + 0 };
}
$dbh->disconnect;

open my $out, '>', $outfile or die "Can't write $outfile: $!\n";
print $out "{\n";
for my $i (0 .. $#type_order) {
    my $type  = $type_order[$i];
    my $comma = $i < $#type_order ? ',' : '';
    print $out '  ' . _js($type) . ": [\n";
    my @entries = @{ $by_type{$type} };
    for my $j (0 .. $#entries) {
        my $e      = $entries[$j];
        my $ecomma = $j < $#entries ? ',' : '';
        print $out '    {"name": ' . _js($e->{name}) . ', "count": ' . $e->{count} . "}$ecomma\n";
    }
    print $out "  ]$comma\n";
}
print $out "}\n";
close $out;

print "Wrote $outfile\n";

sub _js {
    my $s = shift // '';
    $s =~ s/\\/\\\\/g;
    $s =~ s/"/\\"/g;
    return qq{"$s"};
}
