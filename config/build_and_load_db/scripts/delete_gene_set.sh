#!/usr/bin/bash
# delete_gene_set.sh — remove ONE gene set from an organism database, leaving the
# organism's other gene sets untouched.
#
# Usage: delete_gene_set.sh <organism.sqlite> <gene_set_name>
#
# Used by a narrowed reload (moop_process_genome_data_v2.sbatch Org Asm GeneSet with
# MOOP_RELOAD=1). Reloading a gene set on top of itself is NOT enough: both loaders
# upsert -- load_genes_sqlite.pl matches on (uniquename, gene_set_id) and updates,
# load_annotations_sqlite.pl on (feature_id, annotation_id) -- so a re-run corrects
# what is still present and adds what is new, but leaves behind every row the new
# files no longer contain. Commit 3566673 stopped emitting :cds/:pep rows for
# non-coding transcripts; without this deletion, reloading with the fixed parser would
# add the correct rows, keep all the bogus ones, and report success.
#
# Deletes are EXPLICIT rather than relying on ON DELETE CASCADE, for two reasons:
#
#   1. PRAGMA foreign_keys is OFF by default in the sqlite3 CLI as well as in every
#      client library. It is per-connection and cannot live in the schema. That is
#      exactly what made every FOREIGN KEY in this schema decorative until 2026-07-24
#      (see create_schema_sqlite.sql), and it would fail silently here too: the
#      gene_set row would go and every feature would stay, orphaned.
#   2. Databases built before that fix do not have working FKs at all. Explicit
#      deletes work on both.
#
# The annotation sweep at the end matters separately. annotation and
# annotation_source are NOT owned by a gene set -- they are shared -- so deleting
# features leaves annotation rows that nothing points at. That is precisely the
# ORPHAN_ANNOT condition check_status.sh reports.

set -uo pipefail

DB=${1:?Usage: $0 <organism.sqlite> <gene_set_name>}
GENE_SET=${2:?Usage: $0 <organism.sqlite> <gene_set_name>}

if [ ! -s "$DB" ]; then
  echo "  $DB does not exist yet — nothing to delete."
  exit 0
fi

# Nothing to do if this gene set was never loaded. Not an error: a narrowed reload of
# a gene set that failed to load last time is a completely normal thing to want.
present=$(sqlite3 "$DB" \
  "SELECT COUNT(*) FROM gene_set WHERE gene_set_name = '${GENE_SET//\'/\'\'}';" 2>/dev/null)
if [ "${present:-0}" -eq 0 ]; then
  echo "  gene set '$GENE_SET' is not in $DB — nothing to delete."
  exit 0
fi

before=$(sqlite3 "$DB" "SELECT COUNT(*) FROM feature;" 2>/dev/null)

sqlite3 "$DB" <<SQL || { echo "ERROR: failed to delete gene set '$GENE_SET' from $DB" >&2; exit 1; }
PRAGMA foreign_keys = ON;
BEGIN;

CREATE TEMP TABLE doomed_gs AS
  SELECT gene_set_id FROM gene_set WHERE gene_set_name = '${GENE_SET//\'/\'\'}';

DELETE FROM feature_annotation
 WHERE feature_id IN (SELECT feature_id FROM feature
                       WHERE gene_set_id IN (SELECT gene_set_id FROM doomed_gs));

DELETE FROM feature   WHERE gene_set_id IN (SELECT gene_set_id FROM doomed_gs);
DELETE FROM gene_set  WHERE gene_set_id IN (SELECT gene_set_id FROM doomed_gs);

-- Shared rows that nothing points at any more.
DELETE FROM annotation
 WHERE annotation_id NOT IN (SELECT annotation_id FROM feature_annotation);
DELETE FROM annotation_source
 WHERE annotation_source_id NOT IN (SELECT annotation_source_id FROM annotation);

COMMIT;
SQL

after=$(sqlite3 "$DB" "SELECT COUNT(*) FROM feature;" 2>/dev/null)
echo "  removed gene set '$GENE_SET': features $before -> $after"
