/**
 * MOOP SQLite Schema
 *
 * ---------------------------------------------------------------------------
 * FOREIGN KEYS ARE OFF BY DEFAULT IN SQLITE.
 *
 * Every FOREIGN KEY and every ON DELETE CASCADE below is inert unless the
 * connection runs:
 *
 *     PRAGMA foreign_keys = ON;
 *
 * It is a per-CONNECTION pragma -- it cannot be stored in the schema file, and
 * it must be set by every client that writes. The loaders do this; see
 * load_genes_sqlite.pl and load_annotations_sqlite.pl. Until 2026-07-24 nothing
 * set it anywhere, so deleting a gene silently left its mRNA/CDS/protein and
 * every annotation behind.
 * ---------------------------------------------------------------------------
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
    -- Was REFERENCES organisms(organism_id) -- a table that does not exist.
    -- Harmless only while foreign_keys was OFF; with it ON, every genome insert
    -- fails with "no such table: main.organisms".
    FOREIGN KEY (organism_id) REFERENCES organism(organism_id) ON DELETE CASCADE,
    CONSTRAINT unique_organism_version UNIQUE (organism_id, genome_accession)
);


CREATE TABLE gene_set (
    gene_set_id          INTEGER PRIMARY KEY AUTOINCREMENT,
    genome_id            INTEGER NOT NULL REFERENCES genome(genome_id) ON DELETE CASCADE,
    gene_set_name        TEXT NOT NULL,
    gene_set_description TEXT,
    UNIQUE (genome_id, gene_set_name)
);


CREATE TABLE feature (
    feature_id INTEGER PRIMARY KEY AUTOINCREMENT,
    feature_name TEXT,
    feature_description TEXT,
    organism_id INTEGER NOT NULL,
    feature_type TEXT NOT NULL,
    feature_uniquename TEXT NOT NULL,
    -- parent_feature_id: a real SQL NULL means "this feature is a root".
    -- NEVER the string 'NULL' and never the empty string -- SQLite stores text
    -- that will not coerce to a number as TEXT even in an INTEGER column, and
    -- then "WHERE parent_feature_id IS NULL" matches nothing at all. A feature
    -- must also never be its own parent (it makes recursive walks non-terminating).
    parent_feature_id INTEGER,
    gene_set_id INTEGER NOT NULL,
    -- Uniqueness is per GENE SET, not global. gene_set_id already determines the
    -- genome (assembly) and organism through gene_set.UNIQUE(genome_id,gene_set_name)
    -- and genome.UNIQUE(organism_id,genome_accession), so naming the assembly here
    -- as well would be redundant.
    --
    -- Global uniqueness was wrong: two gene sets for the same genome cannot share
    -- an ID scheme, and because the loader looks a feature up by uniquename alone,
    -- loading the second set would silently REASSIGN the first set's features
    -- instead of raising a conflict.
    UNIQUE (gene_set_id, feature_uniquename),
    FOREIGN KEY (parent_feature_id) REFERENCES feature(feature_id) ON DELETE CASCADE,
    FOREIGN KEY (organism_id) REFERENCES organism(organism_id) ON DELETE CASCADE,
    FOREIGN KEY (gene_set_id) REFERENCES gene_set(gene_set_id) ON DELETE CASCADE
);


-- Lookup by uniquename alone (searches, and the loader's parent resolution).
-- The UNIQUE(gene_set_id, feature_uniquename) index above leads with gene_set_id,
-- so it cannot serve this. NOT unique any more -- that is the point.
--
-- NOTE: this replaces the old feature_unqiuename_idx, which was an exact duplicate
-- of the implicit index created by the old inline "feature_uniquename ... UNIQUE".
-- Two identical B-trees on one column, both written on every insert.
CREATE INDEX feature_uniquename_idx
ON feature (feature_uniquename);

CREATE INDEX feature_gene_set_id_idx
ON feature (gene_set_id);

CREATE INDEX feature_parent_feature_id_idx
ON feature (parent_feature_id);

CREATE INDEX feature_type_idx
ON feature (feature_type);


CREATE TABLE annotation_source (
    annotation_source_id INTEGER PRIMARY KEY AUTOINCREMENT,
    annotation_source_name TEXT NOT NULL,
    annotation_source_version TEXT NOT NULL,
    annotation_accession_url TEXT,
    annotation_source_url TEXT,
    annotation_type TEXT NOT NULL,
    -- The loader already looks a source up by exactly this pair, but nothing
    -- enforced it. Untrimmed header values then produced "Ensembl Homo sapiens "
    -- alongside "Ensembl Homo sapiens": two sources a user cannot tell apart in
    -- the Annotation Search / MOOPmart pickers, each holding half the annotations.
    -- Enforcing it here makes that class of duplicate impossible, not merely fixed.
    UNIQUE (annotation_source_name, annotation_source_version)
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
    -- score is REAL and NULLABLE.
    --
    -- It used to be "TEXT NOT NULL" holding e-values like 2.9e-72 alongside the
    -- literal string "-" for annotation types that carry no score (43% of rows in
    -- the sampled organism). As TEXT it sorted lexicographically, so "100" ordered
    -- before "9" and every "-" row sorted first.
    --
    -- Do NOT load "-" as 0.0: zero is the strongest possible e-value, so those
    -- rows would masquerade as the most significant hits. Load them as NULL,
    -- which means "no score" and sorts apart from real values.
    score REAL,
    date DATE NOT NULL,
    -- One annotation can attach to one feature once. Deduplication previously
    -- existed only in the loader's in-memory hash, so it protected a single run
    -- and nothing else.
    UNIQUE (feature_id, annotation_id),
    FOREIGN KEY (annotation_id) REFERENCES annotation(annotation_id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES feature(feature_id) ON DELETE CASCADE
);

-- feature_id is served by the leading column of UNIQUE(feature_id, annotation_id),
-- so only the reverse direction needs its own index.
CREATE INDEX feature_annotation_annotation_id_idx
ON feature_annotation (annotation_id);
