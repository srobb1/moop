#!/usr/bin/env bash
#
# check_sequence_id_match.sh — do the sequence FASTAs use the same IDs as the database?
#
# WHY THIS EXISTS
#
# Sequence retrieval joins two things that are built by different pipelines: feature
# uniquenames in organism.sqlite (loaded from the GFF `ID` attribute) and the keys inside
# transcript.nt.fa / cds.nt.fa / protein.aa.fa (written by whatever produced the FASTA).
# Nothing enforces that they agree, and when they disagree NOTHING REPORTS IT — blastdbcmd
# simply finds no entry, and the user gets a page with fewer sequence types than it should
# have and no error at all.
#
# That is exactly what happened, found 2026-07-23: on 25 RefSeq-derived gene sets the CDS
# FASTA carried NCBI's native headers
#
#     >lcl|NW_003546242.1_cds_XP_019850797.1_1
#
# while the database (and feature_coords.tsv, which agreed with the database) used the GFF
# ID attribute
#
#     cds-XP_019850797.1
#
# so CDS retrieval returned nothing on those organisms while mRNA and protein worked. The
# fix is on the build side: emit the GFF ID as the FASTA header, then re-run makeblastdb.
# Run this afterwards to confirm, and periodically to catch it coming back.
#
# USAGE
#   scripts/check_sequence_id_match.sh [organisms_dir]
#
# Exit status: 0 = every gene set matched, 1 = at least one mismatch.

set -uo pipefail

ORGDIR="${1:-/var/www/html/moop/organisms}"

if [ ! -d "$ORGDIR" ]; then
    echo "Not a directory: $ORGDIR" >&2
    exit 2
fi
command -v sqlite3 >/dev/null || { echo "sqlite3 not found" >&2; exit 2; }

# sequence type -> (fasta filename, DB feature_type list)
declare -A FASTA=( [transcript]=transcript.nt.fa [cds]=cds.nt.fa [protein]=protein.aa.fa )
declare -A TYPES=( [transcript]="'mRNA','transcript'" [cds]="'cds','CDS'" [protein]="'protein','polypeptide'" )

problems=0
checked=0

printf "%-30s %-18s %-14s %-10s %s\n" ORGANISM ASSEMBLY GENE_SET TYPE RESULT
printf '%.0s-' {1..96}; echo

for gsdir in "$ORGDIR"/*/*/*/; do
    [ -d "$gsdir" ] || continue
    org=$(basename "$(dirname "$(dirname "$gsdir")")")
    asm=$(basename "$(dirname "$gsdir")")
    gs=$(basename "$gsdir")
    db="$ORGDIR/$org/organism.sqlite"
    [ -f "$db" ] || continue

    for seqtype in transcript cds protein; do
        fa="$gsdir${FASTA[$seqtype]}"
        [ -f "$fa" ] || continue

        # One representative uniquename of this type, scoped to THIS gene set.
        id=$(sqlite3 "$db" "
            SELECT f.feature_uniquename
              FROM feature f
              JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
              JOIN genome   g  ON g.genome_id    = gs.genome_id
             WHERE f.feature_type IN (${TYPES[$seqtype]})
               AND gs.gene_set_name = '${gs//\'/\'\'}'
             LIMIT 1;" 2>/dev/null)

        # No features of this type in the DB is not a mismatch — nothing to join.
        [ -n "$id" ] || continue

        checked=$((checked + 1))

        # Exact header-key match: '>' + id + (space or end of line).
        if grep -m1 -qE "^>${id//./\\.}([[:space:]]|$)" "$fa"; then
            continue
        fi

        problems=$((problems + 1))
        first=$(head -1 "$fa" | cut -c1-60)
        printf "%-30s %-18s %-14s %-10s %s\n" "$org" "$asm" "$gs" "$seqtype" "MISMATCH"
        printf "  %-28s db wants : %s\n" "" "$id"
        printf "  %-28s fasta has: %s\n" "" "$first"
    done
done

echo
if [ "$problems" -eq 0 ]; then
    echo "OK — all $checked gene-set/type combinations use matching IDs."
    exit 0
fi
echo "$problems of $checked gene-set/type combinations MISMATCH."
echo "Sequence retrieval returns nothing for those, silently. Fix on the build side:"
echo "emit the GFF ID attribute as the FASTA header, then re-run makeblastdb."
exit 1
