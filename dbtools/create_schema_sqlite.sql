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

-- ============================================================================
-- MOOP SQLite Database Schema
-- ============================================================================
-- 
-- Core tables for storing organism genomes, gene features, and annotations.
-- 
-- TABLES:
--   organism          - Species information (genus, species, common name)
--   genome            - Genome assemblies per organism
--   feature           - Gene/mRNA features parsed from GFF3 files
--   annotation_source - Metadata about analysis sources (DIAMOND, InterProScan, etc.)
--   annotation        - Individual annotation hits from analysis tools
--   feature_annotation - Links features to annotations with scores/dates
--
-- KEY RELATIONSHIPS:
--   - One organism can have multiple genomes (different assemblies/versions)
--   - Each genome contains many features (genes, mRNAs)
--   - Features link to annotations through feature_annotation table
--   - Annotations reference a source (tool) and accession (hit ID)
--
-- PERFORMANCE:
--   - feature_uniquename_idx for fast gene lookups
--   - annotation_accession_idx for fast hit lookups
--   - Foreign keys with CASCADE for data integrity
--
-- ============================================================================

CREATE TABLE organism (
    organism_id INTEGER PRIMARY KEY AUTOINCREMENT,
    genus TEXT NOT NULL,
    species TEXT NOT NULL,
    subtype TEXT,                  -- e.g., 'subspecies', 'strain'
    common_name TEXT,              -- e.g., 'Human', 'Mouse'
    taxon_id INTEGER               -- NCBI taxonomy ID (optional)
);


CREATE TABLE genome (
    genome_id INTEGER PRIMARY KEY AUTOINCREMENT,
    organism_id INTEGER NOT NULL,
    genome_description TEXT NOT NULL,    -- e.g., 'Reference genome GRCh38'
    genome_name TEXT NOT NULL,           -- e.g., 'GRCh38.p13'
    genome_accession TEXT NOT NULL,      -- e.g., 'GCA_000001405.39'
    FOREIGN KEY (organism_id) REFERENCES organism(organism_id),
    CONSTRAINT unique_organism_version UNIQUE (organism_id, genome_accession)
);


CREATE TABLE feature (
    feature_id INTEGER PRIMARY KEY AUTOINCREMENT,
    feature_name TEXT,                   -- e.g., 'BRCA1'
    feature_description TEXT,            -- descriptive information
    organism_id INTEGER NOT NULL,
    feature_type TEXT NOT NULL,          -- 'gene', 'mRNA', 'exon', etc.
    feature_uniquename TEXT NOT NULL UNIQUE,  -- e.g., 'AT1G01010'
    parent_feature_id INTEGER,           -- for hierarchical features (mRNA->exon)
    genome_id INTEGER,
    FOREIGN KEY (parent_feature_id) REFERENCES feature(feature_id) ON DELETE CASCADE,
    FOREIGN KEY (organism_id) REFERENCES organism(organism_id) ON DELETE CASCADE,
    FOREIGN KEY (genome_id) REFERENCES genome(genome_id) ON DELETE SET NULL
);


-- Index for fast lookups by feature uniquename (used in web searches)
CREATE UNIQUE INDEX feature_uniquename_idx
ON feature (feature_uniquename);


CREATE TABLE annotation_source (
    annotation_source_id INTEGER PRIMARY KEY AUTOINCREMENT,
    annotation_source_name TEXT NOT NULL,     -- e.g., 'DIAMOND', 'InterProScan', 'Pfam'
    annotation_source_version TEXT NOT NULL,  -- tool version used
    annotation_accession_url TEXT,            -- URL template for accession links
    annotation_source_url TEXT,               -- tool/database website
    annotation_type TEXT NOT NULL             -- 'BLASTP', 'InterPro', 'GO', etc.
);


CREATE TABLE annotation (
    annotation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    annotation_source_id INTEGER NOT NULL,
    annotation_accession TEXT NOT NULL,      -- e.g., 'sp|P12345|BRCA1_HUMAN' for UniProt
    annotation_description TEXT,             -- hit description/title
    FOREIGN KEY (annotation_source_id) REFERENCES annotation_source(annotation_source_id) ON DELETE CASCADE
);


-- Index for fast lookups by accession (used in link resolution)
CREATE INDEX annotation_accession_idx
ON annotation (annotation_accession);


CREATE TABLE feature_annotation (
    feature_annotation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    feature_id INTEGER NOT NULL,
    annotation_id INTEGER NOT NULL,
    score TEXT NOT NULL,                     -- e.g., 'e-value', bit score, percent identity
    date DATE NOT NULL,                      -- when analysis was run
    FOREIGN KEY (annotation_id) REFERENCES annotation(annotation_id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES feature(feature_id) ON DELETE CASCADE
);
