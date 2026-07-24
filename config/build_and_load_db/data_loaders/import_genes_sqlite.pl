#!/usr/bin/perl
use strict;
use warnings;
use DBI;

# Database connection details
my $dbfile = shift;
my $dbh = DBI->connect("dbi:SQLite:dbname=$dbfile", "", "", { RaiseError => 1, PrintError => 0, AutoCommit => 0 });

# Foreign keys are OFF by default in SQLite, and it is a per-CONNECTION setting --
# it cannot be baked into create_schema_sqlite.sql. Without this, every FOREIGN KEY
# and ON DELETE CASCADE in the schema is decorative.
$dbh->do("PRAGMA foreign_keys = ON");

# Read the tab-delimited file
my $feature_file = shift;   #tab delimited: Uniquename,This_Type,Parent_Uniquename,Parent_Type,This_Name,This_Description
my $gene_set_name = shift // 'primary';

open FEATURES, $feature_file or die "Cannot open file $feature_file: $! \n";

# Get organism and genome information from feature file header

## Genus: Anoura
## Species: caudifer
## Species Subtype: none
## Common Name: Tailed tailless bat
## NCBI Taxon ID: 27642
## Genome Accession: GCA_004027475.1
## Genome Name: ACA1
## Genome Description: Assembly from NCBI. Gene models were called using Helixer by smr
## Uniquename	This_Type	Parent_uniquename	This_Name	This_Description

my ($genus,$species,$species_subtype,$common_name,$taxon_id,$genome_accession,$genome_name,$genome_description);

# Set all non required organism information to NULL
($species_subtype, $taxon_id ,$common_name) = ('','','');

while (my $line = <FEATURES>){
  last if $line !~ /^##/;
  if ($line =~ /## Genus:\s*(.+?)\s*$/){
    $genus = $1;
  }elsif($line =~ /## Species:\s*(.+?)\s*$/){
    $species = $1;
  }elsif($line =~ /## Species Subtype:\s*(.+?)\s*$/){ ## Biotype:C4, Biotype:Sexual, Strain:C1234, Cultivar:XYZ1
    $species_subtype = $1;
  }elsif($line =~ /## Common Name:\s*(.+?)\s*$/){
    $common_name = $1;
  }elsif($line =~ /## NCBI Taxon ID:\s*(.+?)\s*$/){
    $taxon_id = $1;
  }elsif($line =~ /## Genome Accession:\s*(.+?)\s*$/){
    $genome_accession = $1;
  }elsif($line =~ /## Genome Name:\s*(.+?)\s*$/){
    $genome_name = $1;
  }elsif($line =~ /## Genome Description:\s*(.+?)\s*$/){
    $genome_description = $1;
  }
}

# Prepare the Organism SQL statements
my $insert_organism_sql = $dbh->prepare("
    INSERT INTO organism (genus, species, common_name, taxon_id, subtype)
    VALUES (?, ?, ?, ?, ?)
");

my $get_organism_sql = $dbh->prepare("
    SELECT organism_id
    FROM organism
    WHERE genus = ?
      AND species = ?
      AND subtype = ?
");

# Prepare the statement to check if the genome exists based on genome_version
my $get_genome_sql = $dbh->prepare("
    SELECT genome_id
    FROM genome
    WHERE genome_accession = ? AND organism_id = ?
");

# Prepare the statement to insert a new genome if it doesn't exist
my $insert_genome_sql = $dbh->prepare("
    INSERT INTO genome (organism_id, genome_description, genome_accession, genome_name)
    VALUES (?, ?, ?, ?)
");

# Prepare gene_set SQL statements
my $get_gene_set_sql = $dbh->prepare("
    SELECT gene_set_id
    FROM gene_set
    WHERE genome_id = ? AND gene_set_name = ?
");

my $insert_gene_set_sql = $dbh->prepare("
    INSERT INTO gene_set (genome_id, gene_set_name)
    VALUES (?, ?)
");

#Prepare the SQL for checking and inserting/updating features
# Scoped by gene_set_id: feature_uniquename is unique PER GENE SET, not globally.
# Looking up by uniquename alone would find another gene set's feature and the
# UPDATE below would then reassign it -- silently moving features between sets.
my $get_feature_sql = $dbh->prepare("
    SELECT feature_id, feature_name, feature_description, organism_id, feature_type, feature_uniquename, gene_set_id, parent_feature_id
    FROM feature
    WHERE feature_uniquename = ? AND gene_set_id = ?
");

my $update_feature_sql = $dbh->prepare("
    UPDATE feature
    SET feature_name = ?, feature_description = ?, organism_id = ?, feature_type = ?, gene_set_id = ?, parent_feature_id = ?
    WHERE feature_id = ?
");

my $insert_feature_sql = $dbh->prepare("
    INSERT INTO feature (feature_name, feature_description, organism_id, feature_type, feature_uniquename, gene_set_id, parent_feature_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

## Check that the organism is in the DB, if it isn't insert it
$get_organism_sql->execute($genus, $species, $species_subtype);
my $organism_id = $get_organism_sql->fetchrow_array;
if (!$organism_id) {
  $insert_organism_sql->execute($genus, $species, $common_name, $taxon_id, $species_subtype);
  $organism_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
}

# Check if the genome for the organism already exists
$get_genome_sql->execute($genome_accession, $organism_id);
my $genome_id = $get_genome_sql->fetchrow_array;
if (!$genome_id) {
  $insert_genome_sql->execute($organism_id, $genome_description, $genome_accession, $genome_name);
  $genome_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
}

# Check if the gene_set already exists, insert if not
$get_gene_set_sql->execute($genome_id, $gene_set_name);
my $gene_set_id = $get_gene_set_sql->fetchrow_array;
if (!$gene_set_id) {
  $insert_gene_set_sql->execute($genome_id, $gene_set_name);
  $gene_set_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
}

# close and reopen so that we don't miss the first feature line
close FEATURES;
open FEATURES, $feature_file or die "Cannot open file $feature_file: $! \n";

my $inserted_rows      = 0;
my $updated_rows       = 0;
my $self_parent_rows   = 0;   # source rows whose parent == their own id
my $self_parent_example;

while (my $line = <FEATURES>) {
    chomp $line;
    next if $line =~ /^#/;
    my ($this_unique_name, $this_type, $parent_unique_name, $parent_type, $this_name, $this_description) = split /\t/, $line;
    # Normalise every column to a defined, trimmed string. A short line would
    # otherwise leave trailing columns undef and warn on every comparison below.
    foreach my $each ($this_unique_name, $this_type, $parent_unique_name, $parent_type, $this_name, $this_description) {
      $each = '' if !defined $each;
      $each =~ s/^\s+|\s+$//g;
    }

    if ($this_name eq $this_unique_name  ){
      $this_name = '';
    }
    if ($this_description eq $this_unique_name){
      $this_description = '';
    }

    # ------------------------------------------------------------------
    # Resolve the parent.
    #
    # undef is the ONLY way to write a real SQL NULL through DBI. The string
    # 'NULL' and the empty string are VALUES, not absences -- and SQLite stores
    # either one happily in an INTEGER column, because text that will not
    # coerce to a number is kept as text. That is what made every root feature
    # unreachable: "WHERE parent_feature_id IS NULL" matched nothing at all.
    #
    # A feature must also never be its own parent. Some GFFs carry geneID= on
    # the gene line pointing at itself; mapped straight to a parent column that
    # becomes parent == self, and any recursive walk up the tree loops forever.
    # ------------------------------------------------------------------
    my $parent_feature_id = undef;   # undef => real SQL NULL => this is a root

    if ($parent_unique_name ne ''
        && uc($parent_unique_name) ne 'NULL'
        && $parent_unique_name ne $this_unique_name) {

        $get_feature_sql->execute($parent_unique_name, $gene_set_id);
        ($parent_feature_id) = $get_feature_sql->fetchrow_array;

        # Parent is named but not loaded yet -> create it as a root itself.
        if (!$parent_feature_id) {
            $insert_feature_sql->execute('', '', $organism_id, $parent_type,
                                         $parent_unique_name, $gene_set_id, undef);
            $parent_feature_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
        }
    }
    elsif ($parent_unique_name ne '' && $parent_unique_name eq $this_unique_name) {
        # Report rather than silently absorb: this is a defect in the source
        # GFF/TSV, and it is worth knowing which gene sets carry it.
        $self_parent_rows++;
        $self_parent_example = $this_unique_name if !defined $self_parent_example;
    }

    # Check if the feature already exists
    $get_feature_sql->execute($this_unique_name, $gene_set_id);
    my ($feature_id, $existing_name, $existing_description, $existing_organism_id, $existing_type, $existing_uniquename, $existing_gene_set_id, $existing_parent_id) = $get_feature_sql->fetchrow_array;

    # If the feature exists, check for any differences and update if needed.
    #
    # parent_feature_id MUST be part of this comparison. It is written by the
    # UPDATE below, but it used not to be checked here -- so a corrected parent
    # only got saved when some *other* column happened to change too, and a
    # re-load over a bad database silently left the bad parents in place.
    # Comparing with // -1 also makes an old 'NULL'/'' parent differ from a
    # real undef, so re-loading repairs those rows.
    if ($feature_id) {
        my $parent_changed =
            ($existing_parent_id   // -1) ne ($parent_feature_id // -1);

        if ($parent_changed
            || ($existing_name        // '') ne $this_name
            || ($existing_description // '') ne $this_description
            || ($existing_organism_id // -1) ne $organism_id
            || ($existing_type        // '') ne $this_type
            || ($existing_gene_set_id // -1) ne $gene_set_id) {
            $updated_rows++;
            $update_feature_sql->execute($this_name, $this_description, $organism_id, $this_type, $gene_set_id, $parent_feature_id, $feature_id);
        }
    }else {
      # If the feature doesn't exist, insert it
      $inserted_rows++;
      $insert_feature_sql->execute($this_name, $this_description, $organism_id, $this_type, $this_unique_name, $gene_set_id, $parent_feature_id);
    }
}

# Commit changes
$dbh->commit;

print "Data loaded successfully!  inserted=$inserted_rows updated=$updated_rows\n";

# ----------------------------------------------------------------------
# Post-load integrity checks.
#
# These are cheap and they catch, at load time, the exact class of bug that
# is otherwise invisible until a user gets a silently empty result: a root
# feature that no "IS NULL" test can find, or a cycle that makes a recursive
# walk up the tree run forever.
#
# They cover the WHOLE database, not just this load, so pre-existing damage
# from an older loader shows up here too.
# ----------------------------------------------------------------------
my ($bad_parent_type) = $dbh->selectrow_array(
    "SELECT COUNT(*) FROM feature
      WHERE typeof(parent_feature_id) NOT IN ('integer','null')");

my ($self_parents) = $dbh->selectrow_array(
    "SELECT COUNT(*) FROM feature WHERE parent_feature_id = feature_id");

my ($dangling) = $dbh->selectrow_array(
    "SELECT COUNT(*) FROM feature c
      WHERE c.parent_feature_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM feature p
                         WHERE p.feature_id = c.parent_feature_id)");

my ($roots) = $dbh->selectrow_array(
    "SELECT COUNT(*) FROM feature WHERE parent_feature_id IS NULL");

my ($total) = $dbh->selectrow_array("SELECT COUNT(*) FROM feature");

print "\n--- integrity check ---\n";
printf "  features                 %d\n", $total;
printf "  roots (parent IS NULL)   %d\n", $roots;

my @problems;
push @problems, "the feature file produced NO features at all -- organism, genome and"
    . " gene_set rows were still created, so this looks like a successful load"
    . " while leaving nothing for annotations to attach to"
    if !$total;
push @problems, "$bad_parent_type feature(s) store a non-integer, non-NULL parent_feature_id"
    . " (the string 'NULL' or '' -- these roots are unreachable by IS NULL)"
    if $bad_parent_type;
push @problems, "$self_parents feature(s) are their own parent (a recursive walk will not terminate)"
    if $self_parents;
push @problems, "$dangling feature(s) point at a parent_feature_id that does not exist"
    if $dangling;
push @problems, "0 roots -- nothing can bubble up to a top-level feature"
    if !$roots && $total;
push @problems, "$self_parent_rows source row(s) named themselves as their own parent"
    . " and were loaded as roots instead (e.g. $self_parent_example)"
    . " -- check for geneID=/Parent= pointing at the row's own ID"
    if $self_parent_rows;

if (@problems) {
    print "  !! PROBLEMS FOUND\n";
    foreach my $problem (@problems) {
        print "     - $problem\n";
    }
    print "  !! The database loaded, but features above will not resolve correctly.\n";
} else {
    print "  OK - no parent/hierarchy problems found\n";
}
print "-----------------------\n";

$dbh->disconnect;
