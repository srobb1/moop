/**
 * MOOP SQLite Schema with FTS5 Full Text Search
 * 
 * EXTENSIONS USED:
 * - FTS5 (Full Text Search 5): Virtual tables for fast text search
 *   Handles word boundaries, phrase queries, relevance ranking automatically
 * - json1: JSON functions (optional, for future use)
 * 
 * STRATEGY:
 * 1. Core tables store actual data with UNIQUE/PRIMARY indexes
 * 2. FTS5 virtual tables (feature_fts, annotation_fts) mirror searchable content
 * 3. Search queries hit FTS5 first (fast), then JOIN back to core tables for full data
 * 4. FTS5 automatically ranks results by relevance (word matches > phrase matches > substring)
 * 
 * PERFORMANCE:
 * - FTS5 indexes are 2-5x faster than LIKE queries
 * - Handles thousands of results efficiently
 * - Word boundary matching is built-in (ABCG5 ranks higher than CABCOCO1 for "ABC")
 * - Phrase queries ("exact phrase") work naturally
 * 
 * MAINTENANCE:
 * - After bulk data import: REBUILD FTS5 tables with:
 *   INSERT INTO feature_fts(feature_fts, rank) VALUES('rebuild', -1);
 *   INSERT INTO annotation_fts(annotation_fts, rank) VALUES('rebuild', -1);
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
    FOREIGN KEY (organism_id) REFERENCES organisms(organism_id),
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


CREATE UNIQUE INDEX feature_unqiuename_idx
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


-- ============================================================================
-- FTS5 VIRTUAL TABLES - Fast Text Search
-- ============================================================================

-- Full text search index for features (gene names and descriptions)
CREATE VIRTUAL TABLE feature_fts USING fts5(
    feature_name,
    feature_description,
    content=feature,
    content_rowid=feature_id
);

-- Full text search index for annotations (descriptions and accessions)
CREATE VIRTUAL TABLE annotation_fts USING fts5(
    annotation_description,
    annotation_accession,
    content=annotation,
    content_rowid=annotation_id
);

-- Triggers to keep FTS5 indexes in sync with base tables (for incremental updates)
CREATE TRIGGER feature_ai AFTER INSERT ON feature BEGIN
  INSERT INTO feature_fts(rowid, feature_name, feature_description) 
  VALUES (new.feature_id, new.feature_name, new.feature_description);
END;

CREATE TRIGGER feature_ad AFTER DELETE ON feature BEGIN
  INSERT INTO feature_fts(feature_fts, rowid, feature_name, feature_description) 
  VALUES('delete', old.feature_id, old.feature_name, old.feature_description);
END;

CREATE TRIGGER feature_au AFTER UPDATE ON feature BEGIN
  INSERT INTO feature_fts(feature_fts, rowid, feature_name, feature_description) 
  VALUES('delete', old.feature_id, old.feature_name, old.feature_description);
  INSERT INTO feature_fts(rowid, feature_name, feature_description) 
  VALUES (new.feature_id, new.feature_name, new.feature_description);
END;

CREATE TRIGGER annotation_ai AFTER INSERT ON annotation BEGIN
  INSERT INTO annotation_fts(rowid, annotation_description, annotation_accession) 
  VALUES (new.annotation_id, new.annotation_description, new.annotation_accession);
END;

CREATE TRIGGER annotation_ad AFTER DELETE ON annotation BEGIN
  INSERT INTO annotation_fts(annotation_fts, rowid, annotation_description, annotation_accession) 
  VALUES('delete', old.annotation_id, old.annotation_description, old.annotation_accession);
END;

CREATE TRIGGER annotation_au AFTER UPDATE ON annotation BEGIN
  INSERT INTO annotation_fts(annotation_fts, rowid, annotation_description, annotation_accession) 
  VALUES('delete', old.annotation_id, old.annotation_description, old.annotation_accession);
  INSERT INTO annotation_fts(rowid, annotation_description, annotation_accession) 
  VALUES (new.annotation_id, new.annotation_description, new.annotation_accession);
END;

