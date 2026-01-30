#!/usr/bin/perl
use strict;
use warnings;
use DBI;

sub usage {
    my ($msg) = @_;
    print STDERR "ERROR: $msg\n" if $msg;
    print STDERR <<'EOF';
Usage: import_genes_sqlite.pl <organism.sqlite> <genes.tsv>

Required arguments:
  organism.sqlite     SQLite database file (created with create_schema_sqlite.sql)
  genes.tsv           Gene features TSV file with MOOP metadata headers

genes.tsv format (with metadata headers):
  ## Genus: <genus>
  ## Species: <species>
  ## Optional: Common Name, NCBI Taxon ID, Genome Accession, Genome Name, Genome Description, Species Subtype
  ## Uniquename    This_Type    Parent_Uniquename    This_Name    This_Description
  <gene_id>    <type>    <parent_id>    <name>    <description>

Example:
  perl import_genes_sqlite.pl organism.sqlite genes.tsv
  
Generates genes.tsv using:
  perl make_moopFeatureTable_fromGFF.pl genomic.gff organisms.tsv Genus species accession > genes.tsv
EOF
    exit 1;
}

# Validate arguments
usage("Missing required arguments") unless @ARGV == 2;

my $dbfile = shift;
my $feature_file = shift;

# Validate input files exist
usage("Database file does not exist: $dbfile") unless -e $dbfile;
usage("Database file is not readable: $dbfile") unless -r $dbfile;
usage("Feature file does not exist: $feature_file") unless -e $feature_file;
usage("Feature file is not readable: $feature_file") unless -r $feature_file;

my $dbh = DBI->connect("dbi:SQLite:dbname=$dbfile", "", "", { RaiseError => 1, PrintError => 0, AutoCommit => 0 });

# Read the tab-delimited file
open FEATURES, $feature_file or usage("Cannot open file $feature_file: $!");

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
  if ($line =~ /## Genus: (.+)/){
    $genus = $1;
  }elsif($line =~ /## Species: (.+)/){
    $species = $1;
  }elsif($line =~ /## Species Subtype: (.+)/){ ## Biotype:C4, Biotype:Sexual, Strain:C1234, Cultivar:XYZ1
    $species_subtype = $1;  
  }elsif($line =~ /## Common Name: (.+)/){
    $common_name = $1;
  }elsif($line =~ /## NCBI Taxon ID: (.+)/){
    $taxon_id = $1;
  }elsif($line =~ /## Genome Accession: (.+)/){
    $genome_accession = $1;
  }elsif($line =~ /## Genome Name: (.+)/){
    $genome_name = $1;
  }elsif($line =~ /## Genome Description: (.+)/){
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

#Prepare the SQL for checking and inserting/updating features
my $get_feature_sql = $dbh->prepare("
    SELECT feature_id, feature_name, feature_description, organism_id, feature_type, feature_uniquename, genome_id, parent_feature_id 
    FROM feature 
    WHERE feature_uniquename = ?
");

my $update_feature_sql = $dbh->prepare("
    UPDATE feature 
    SET feature_name = ?, feature_description = ?, organism_id = ?, feature_type = ?, genome_id = ?, parent_feature_id = ?
    WHERE feature_id = ?
");

my $insert_feature_sql = $dbh->prepare("
    INSERT INTO feature (feature_name, feature_description, organism_id, feature_type, feature_uniquename, genome_id, parent_feature_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
    
# Prepare the statement to check if the genome exists based on genome_version
my $get_genome_sql = $dbh->prepare("
	SELECT genome_id 
	FROM genome 
	WHERE genome_accession = ? and organism_id = ?
");

# Prepare the statement to insert a new genome if it doesn't exist
my $insert_genome_sql = $dbh->prepare("
	INSERT INTO genome (organism_id, genome_description, genome_accession, genome_name) 
	VALUES (?, ?, ?, ?)
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
  # If the genome for organism doesn't exist, insert it
  $insert_genome_sql->execute($organism_id, $genome_description, $genome_accession, $genome_name);
  $genome_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
}


# close and reopen so that we don't miss the first feature line
close FEATURES;
open FEATURES, $feature_file or die "Cannot open file $feature_file: $! \n";
while (my $line = <FEATURES>) {
    chomp $line;
    next if $line =~ /^#/;
    my ($this_unique_name, $this_type, $parent_unique_name, $parent_type, $this_name, $this_description) = split /\t/, $line;

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
	#INSERT INTO feature (feature_name, feature_description, organism_id, feature_type, feature_uniquename, genome_id, parent_feature_id)
        $insert_feature_sql->execute('', '', $organism_id, $parent_type, $parent_unique_name, $genome_id, '');
	$parent_feature_id = $dbh->selectrow_array('SELECT last_insert_rowid()');
    }

    # Check if the feature already exists
    $get_feature_sql->execute($this_unique_name);
    my ($feature_id, $existing_name, $existing_description, $existing_organism_id, $existing_type, $existing_uniquename, $existing_genome_id, $existing_parent_id) = $get_feature_sql->fetchrow_array;

    # If the feature exists, check for any differences and update if needed
    if ($feature_id) {
        if ($existing_name ne $this_name || $existing_description ne $this_description || $existing_organism_id != $organism_id || $existing_type ne $this_type ||  $existing_genome_id != $genome_id) {
            #Update Order: feature_uniquename, feature_name, feature_description, organism_id, feature_type, genome_id, parent_feature_id
	    print "Updating: $this_name, $this_description, $organism_id, $this_type, $genome_id, $parent_feature_id\n";
            $update_feature_sql->execute($this_name, $this_description, $organism_id, $this_type, $genome_id, $parent_feature_id, $feature_id);
        }
    }else {
      # If the feature doesn't exist, insert it
      $insert_feature_sql->execute($this_name, $this_description, $organism_id, $this_type, $this_unique_name, $genome_id, $parent_feature_id);
    }
}

# Commit changes and disconnect
$dbh->commit;
$dbh->disconnect;

print "Data loaded successfully!\n";

