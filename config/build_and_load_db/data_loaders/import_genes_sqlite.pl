#!/usr/bin/perl
use strict;
use warnings;
use DBI;

# Database connection details
my $dbfile = shift;
my $dbh = DBI->connect("dbi:SQLite:dbname=$dbfile", "", "", { RaiseError => 1, PrintError => 0, AutoCommit => 0 });

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
my $get_feature_sql = $dbh->prepare("
    SELECT feature_id, feature_name, feature_description, organism_id, feature_type, feature_uniquename, gene_set_id, parent_feature_id
    FROM feature
    WHERE feature_uniquename = ?
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
while (my $line = <FEATURES>) {
    chomp $line;
    next if $line =~ /^#/;
    my ($this_unique_name, $this_type, $parent_unique_name, $parent_type, $this_name, $this_description) = split /\t/, $line;
    foreach my $each ($this_unique_name, $this_type, $parent_unique_name, $parent_type, $this_name, $this_description) {
      $each =~ s/^\s+|\s+$//g if defined $each;
    }

    if ($this_name eq $this_unique_name  ){
      $this_name = '';
    }
    if ($this_description eq $this_unique_name){
      $this_description = '';
    }

    # Check if the parent already exists
    my $parent_feature_id = 'NULL';
    if ($parent_unique_name eq ''){
      $parent_unique_name = 'NULL';
    }else{
      $get_feature_sql->execute($parent_unique_name);
      ($parent_feature_id) = $get_feature_sql->fetchrow_array;
    }

    # if parent does not exist but there is an actual parent_unique_name insert it
    if (!$parent_feature_id and $parent_unique_name ne 'NULL'){
        $insert_feature_sql->execute('', '', $organism_id, $parent_type, $parent_unique_name, $gene_set_id, '');
        $parent_feature_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
    }

    # Check if the feature already exists
    $get_feature_sql->execute($this_unique_name);
    my ($feature_id, $existing_name, $existing_description, $existing_organism_id, $existing_type, $existing_uniquename, $existing_gene_set_id, $existing_parent_id) = $get_feature_sql->fetchrow_array;

    # If the feature exists, check for any differences and update if needed
    if ($feature_id) {
        if ($existing_name ne $this_name || $existing_description ne $this_description || $existing_organism_id != $organism_id || $existing_type ne $this_type || $existing_gene_set_id != $gene_set_id) {
            print "Updating: $this_name, $this_description, $organism_id, $this_type, $gene_set_id, $parent_feature_id\n";
            $update_feature_sql->execute($this_name, $this_description, $organism_id, $this_type, $gene_set_id, $parent_feature_id, $feature_id);
        }
    }else {
      # If the feature doesn't exist, insert it
      $insert_feature_sql->execute($this_name, $this_description, $organism_id, $this_type, $this_unique_name, $gene_set_id, $parent_feature_id);
    }
}

# Commit changes and disconnect
$dbh->commit;
$dbh->disconnect;

print "Data loaded successfully!\n";
