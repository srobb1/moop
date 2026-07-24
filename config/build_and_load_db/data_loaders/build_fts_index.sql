-- ============================================================================
-- MOOP full-text search index  (SQLite FTS5)
-- ============================================================================
-- Run this as the LAST step of building an organism.sqlite, AFTER every gene set
-- (load_genes_sqlite.pl) and every annotation source (load_annotations_sqlite.pl)
-- have been loaded:
--
--     sqlite3 organism.sqlite < build_fts_index.sql
--
-- Safe to re-run any time: it drops and rebuilds from the current tables.
--
-- ATOMIC: the drop + rebuild runs inside ONE transaction (BEGIN IMMEDIATE ... COMMIT).
-- If the process is killed mid-rebuild (cluster OOM, timeout, node failure), SQLite
-- rolls back to the PREVIOUS good index instead of leaving the FTS tables half-built
-- or missing. NOTE: the COMMIT is required — sqlite3 rolls an open transaction back on
-- exit, so without it the whole rebuild is discarded.
--
-- Requires SQLite built with FTS5 (standard in modern sqlite3 and PHP PDO_SQLite).
--
-- Tokenizer 'porter unicode61' = case/accent-insensitive WORD matching with English
-- stemming (searching "binding" also finds bind/binds/bound). It does NOT match
-- mid-word fragments ("inase" will not match "kinase").
--
-- CONTENTLESS (content = ''): FTS5 stores only the inverted index, not a second
-- copy of the indexed text. Without it, FTS5 keeps a full duplicate of every
-- indexed column in an internal _content table -- 307 MB of a 722 MB organism
-- database, roughly 43% of the file, duplicating text that already lives in
-- feature and annotation. That copy competes for page cache against a corpus far
-- larger than RAM, which is where the cold-query cost comes from.
--
-- Safe here because MOOP's queries use these tables ONLY for MATCH and rowid --
-- every displayed column is selected from the real tables via the rowid join
-- (see searchFeaturesAndAnnotations / searchFeaturesByNameDescription in
-- lib/database_queries.php). bm25() ranking still works: it reads the index and
-- _docsize, not _content. There is no snippet()/highlight() call anywhere.
--
-- Constraint to respect: a contentless FTS5 table supports INSERT only -- no
-- UPDATE or DELETE (contentless_delete arrived in SQLite 3.43; this host is on
-- 3.34.1). That is exactly the drop-and-rebuild pattern below, so it costs
-- nothing. If you ever need incremental FTS updates, this must be revisited.
--
-- RECLAIMING SPACE ON EXISTING DATABASES: re-running this script drops the old
-- FTS tables and rebuilds them contentless, but SQLite keeps the freed pages in
-- the file. Run VACUUM afterwards to actually shrink it on disk.
--
-- Two indexes mirror MOOP's two text-search code paths:
--   feature_annotation_search  ->  searchFeaturesAndAnnotations()
--   feature_search             ->  searchFeaturesByNameDescription()  (incl. unannotated features)
-- ============================================================================

BEGIN IMMEDIATE;

DROP TABLE IF EXISTS feature_annotation_search;
DROP TABLE IF EXISTS feature_search;

-- 1) Annotation search: one FTS row per (feature, annotation) pair.
--    rowid = feature_annotation.feature_annotation_id, so MOOP joins straight back
--    to feature_annotation for display. Includes the feature's own name/description
--    so a gene whose NAME matches still turns up with its annotation rows.
CREATE VIRTUAL TABLE feature_annotation_search USING fts5(
    feature_name,
    feature_description,
    annotation_description,
    annotation_accession,
    content = '',
    tokenize = 'porter unicode61'
);
INSERT INTO feature_annotation_search(
    rowid, feature_name, feature_description, annotation_description, annotation_accession)
SELECT fa.feature_annotation_id,
       f.feature_name,
       f.feature_description,
       a.annotation_description,
       a.annotation_accession
FROM feature_annotation fa
JOIN feature    f ON f.feature_id    = fa.feature_id
JOIN annotation a ON a.annotation_id = fa.annotation_id;

-- 2) Gene-only search: one FTS row per feature (rowid = feature.feature_id).
--    Every feature is indexed, INCLUDING features with no annotations (which #1 cannot cover).
CREATE VIRTUAL TABLE feature_search USING fts5(
    feature_name,
    feature_description,
    content = '',
    tokenize = 'porter unicode61'
);
INSERT INTO feature_search(rowid, feature_name, feature_description)
SELECT f.feature_id,
       f.feature_name,
       f.feature_description
FROM feature f;

COMMIT;

-- Post-commit niceties. Safe to interrupt: the index above is already committed and
-- correct, so a kill here only skips the optimization, it never leaves a broken index.
-- 'optimize' merges the FTS b-tree segments written during bulk load (faster first queries).
INSERT INTO feature_annotation_search(feature_annotation_search) VALUES('optimize');
INSERT INTO feature_search(feature_search) VALUES('optimize');

-- Optional: reclaim free pages / shrink the file. VACUUM CANNOT run inside a
-- transaction, so it must stay here, after COMMIT. Uncomment to enable.
-- VACUUM;
