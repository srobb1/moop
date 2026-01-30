/**
 * MOOP SQLite Schema
 * 
 * Core tables for storing organism genomes and annotations
 * 
 * TABLES:
 * - organism: Species information
 * - genome: Genome assemblies per organism
 * - feature: Gene/mRNA features from GFF files
 * - annotation_source: Source/tool information for annotations
 * - annotation: Individual annotation hits
 * - feature_annotation: Links features to annotations with scores
 * 
 * INDEXES:
 * - feature_uniquename_idx: Fast lookup by feature ID
 * - annotation_accession_idx: Fast lookup by accession
 */

CREATE TABLE organism (
    organism_id INTEGER PRIMARY KEY AUTOINCREMENT,
    genus TEXT NOT NULL,
    species TEXT NOT NULL,
    subtype TEXT, 
    common_name TEXT,
    taxon_id INTEGER
);


CREATE TABLE genome (
    genome_id INTEGER PRIMARY KEY AUTOINCREMENT,
    organism_id INTEGER NOT NULL,
    genome_description TEXT NOT NULL,
    genome_name TEXT NOT NULL,
    genome_accession TEXT NOT NULL,
    FOREIGN KEY (organism_id) REFERENCES organism(organism_id),
    CONSTRAINT unique_organism_version UNIQUE (organism_id, genome_accession)
);


CREATE TABLE feature (
    feature_id INTEGER PRIMARY KEY AUTOINCREMENT,
    feature_name TEXT,
    feature_description TEXT,
    organism_id INTEGER NOT NULL,
    feature_type TEXT NOT NULL,
    feature_uniquename TEXT NOT NULL UNIQUE,
    parent_feature_id INTEGER,
    genome_id INTEGER,
    FOREIGN KEY (parent_feature_id) REFERENCES feature(feature_id) ON DELETE CASCADE,
    FOREIGN KEY (organism_id) REFERENCES organism(organism_id) ON DELETE CASCADE,
    FOREIGN KEY (genome_id) REFERENCES genome(genome_id) ON DELETE SET NULL
);


CREATE UNIQUE INDEX feature_uniquename_idx
ON feature (feature_uniquename);


CREATE TABLE annotation_source (
    annotation_source_id INTEGER PRIMARY KEY AUTOINCREMENT,
    annotation_source_name TEXT NOT NULL,
    annotation_source_version TEXT NOT NULL,
    annotation_accession_url TEXT,
    annotation_source_url TEXT,
    annotation_type TEXT NOT NULL
);


CREATE TABLE annotation (
    annotation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    annotation_source_id INTEGER NOT NULL,
    annotation_accession TEXT NOT NULL,
    annotation_description TEXT,
    FOREIGN KEY (annotation_source_id) REFERENCES annotation_source(annotation_source_id) ON DELETE CASCADE
);


CREATE INDEX annotation_accession_idx
ON annotation (annotation_accession);


CREATE TABLE feature_annotation (
    feature_annotation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    feature_id INTEGER NOT NULL,
    annotation_id INTEGER NOT NULL,
    score TEXT NOT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (annotation_id) REFERENCES annotation(annotation_id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES feature(feature_id) ON DELETE CASCADE
);
