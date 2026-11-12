#!/bin/bash
# Build crosswalk.sqlite for MOOP from the RosettaStone pipeline outputs.
#
#   build_crosswalk.sh -c rosettastone.conf          # everything from the config
#   build_crosswalk.sh -o out.sqlite --organism Org --aliases aliases.txt \
#       --ref NAME HUB MAPPINGS T2G [--ref ...] [--transfer-hub SET ...] [--probe-set SET ...]
#
# Inputs and output come from the config (-c) and/or options; options win.
#   -c FILE              rosettastone.conf. Per reference in REFERENCES: hub from the config,
#                        $OUT_DIR/filtered/<ref>_mappings.txt and $OUT_DIR/refdb/<ref>/t2g.tsv.
#                        Also TRANSFER_HUBS, the probe set names, $OUT_DIR/aliases/aliases.txt,
#                        CROSSWALK_ORGANISM (default: the SPECIES_DIR folder name), CROSSWALK_OUT
#                        and the CROSSWALK_FALLBACK_* keys.
#   -o, --out FILE       output sqlite     [CROSSWALK_OUT, else $OUT_DIR/crosswalk/crosswalk.sqlite]
#   --organism NAME      MOOP organism folder
#   --ref NAME HUB MAPPINGS T2G
#                        a reference: its name, MOOP hub name, step 04 mapping table and step 01
#                        transcript-to-gene table (replaces the config's references)
#   --aliases FILE       alias table (alias, accession, set, type)
#   --transfer-hub SET   publish query set SET as a hub too (repeatable)
#   --probe-set SET      SET is a probe set (repeatable): the fallback does not chain through it
#   --fallback-ids FILE --fallback-mapping FILE [--fallback-hub HUB] [--fallback-rename OLD=NEW]
#   --no-fallback        skip the fallback even if the config sets it
#   --tsv-dir DIR        also write <hub>.mapping files (seq_id, ref_id, seq_id_source) to DIR
#
# What it does (in SQL):
#   1. Every sequence that overlaps a reference gene maps to that gene (one hub per reference).
#      A target that is itself a reference transcript (t2g.tsv) is labelled with the reference's
#      transcripts source (REFERENCE_TRANSCRIPT_SOURCES "ref=label ...", default <ref>_transcripts) and
#      also gives a gene-to-gene row (that reference's gene, labelled with its hub).
#   2. An alias maps wherever its accession maps within the same set; aliases of aliases too.
#   3. Fallback (optional): listed IDs that 1-2 do not reach are taken from the fallback
#      mapping onto the fallback hub, and from there on to the reference genes.
#   4. Transfer hubs: each ID of a transfer-hub set inherits every mapping of the reference gene
#      it overlaps best (most exact exon matches, then longest overlap; ties all kept), per
#      reference, plus its own fallback mappings.
#   Every hub ID also maps to itself.
# Output: the crosswalk/1 format (MOOP docs/CROSSWALK_FORMAT.md). Hub names must match MOOP's
# 'crosswalk' configuration. Needs bash, awk and sqlite3 >= 3.25. Builds next to the output and
# moves it into place only when complete.
set -euo pipefail
source "$(dirname "$0")/../scripts/lib.sh"

CONF=; org=; out=; ALIASES=; TSVDIR=; NOFB=
FB_IDS=; FB_MAP=; FB_HUB=; FB_RENAME=
CLI_REFS=(); THUBS=(); PROBES=()
usage() { sed -n '2,/^set -/p' "$0" | sed '$d; s/^# \{0,1\}//' >&2; exit 2; }
while [ $# -gt 0 ]; do
    case $1 in
        -c) CONF=$2; shift 2 ;;
        -o|--out) out=$2; shift 2 ;;
        --organism) org=$2; shift 2 ;;
        --ref) [ $# -ge 5 ] || die "--ref needs NAME HUB MAPPINGS T2G"
               CLI_REFS+=("$2|$3|$4|$5"); shift 5 ;;
        --aliases) ALIASES=$2; shift 2 ;;
        --transfer-hub) THUBS+=("$2"); shift 2 ;;
        --probe-set) PROBES+=("$2"); shift 2 ;;
        --fallback-ids) FB_IDS=$2; shift 2 ;;
        --fallback-mapping) FB_MAP=$2; shift 2 ;;
        --fallback-hub) FB_HUB=$2; shift 2 ;;
        --fallback-rename) FB_RENAME="$FB_RENAME $2"; shift 2 ;;
        --no-fallback) NOFB=1; shift ;;
        --tsv-dir) TSVDIR=$2; shift 2 ;;
        -h|--help) usage ;;
        *) echo "unknown argument: $1" >&2; usage ;;
    esac
done

# references: name|hub|mappings|t2g
REFS=("${CLI_REFS[@]}")
if [ -n "$CONF" ]; then
    read_config "$CONF"
    if [ ${#REFS[@]} -eq 0 ]; then
        [ -n "${OUT_DIR:-}" ] || die "$CONF: OUT_DIR is not set"
        for r in "${REF_NAMES[@]}"; do
            REFS+=("$r|${REF_HUB[$r]}|$OUT_DIR/filtered/${r}_mappings.txt|$OUT_DIR/refdb/$r/t2g.tsv")
        done
    fi
    if [ -n "${OUT_DIR:-}" ]; then
        ALIASES=${ALIASES:-$OUT_DIR/aliases/aliases.txt}
        out=${out:-${CROSSWALK_OUT:-$OUT_DIR/crosswalk/crosswalk.sqlite}}
    fi
    out=${out:-${CROSSWALK_OUT:-}}
    org=${org:-${CROSSWALK_ORGANISM:-}}
    [ ${#THUBS[@]} -gt 0 ] || read -r -a THUBS <<< "${TRANSFER_HUBS:-}"
    [ ${#PROBES[@]} -gt 0 ] || PROBES=("${PROBE_NAMES[@]}")
    FB_IDS=${FB_IDS:-${CROSSWALK_FALLBACK_IDS:-}}
    FB_MAP=${FB_MAP:-${CROSSWALK_FALLBACK_MAPPING:-}}
    FB_HUB=${FB_HUB:-${CROSSWALK_FALLBACK_HUB:-}}
    FB_RENAME=${FB_RENAME:-${CROSSWALK_FALLBACK_SET_RENAME:-}}
fi
[ -n "$NOFB" ] && { FB_IDS=; FB_MAP=; }

[ -n "$out" ] || die "no output: give -o, or CROSSWALK_OUT / OUT_DIR in the config"
[ -n "$org" ] || die "no organism: give --organism, or CROSSWALK_ORGANISM in the config"
[[ "$org" =~ $NAME_RE ]] || die "not an organism folder name: $org"
[ ${#REFS[@]} -gt 0 ] || die "no references: give --ref, or -c with REFERENCES"
[ -n "$ALIASES" ] || die "no alias table: give --aliases, or -c with OUT_DIR"
hubs=" "
for x in "${REFS[@]}"; do
    IFS='|' read -r name hub map t2g <<< "$x"
    [[ "$name" =~ $NAME_RE && "$hub" =~ $NAME_RE ]] || die "bad reference/hub name: $name / $hub"
    [[ "$hubs" != *" $hub "* ]] || die "hub $hub is used twice"
    hubs="$hubs$hub "
    for f in "$map" "$t2g"; do [ -r "$f" ] || die "cannot read $f"; done
    head -1 "$map" | grep -q $'^gene_id\ttarget_id\ttranscriptome\t' \
        || die "$map: header is not gene_id<TAB>target_id<TAB>transcriptome..."
done
for h in "${THUBS[@]}"; do
    [[ "$h" =~ $NAME_RE ]] || die "bad transfer hub name: $h"
    [[ "$hubs" != *" $h "* ]] || die "hub $h is used twice"
    hubs="$hubs$h "
done
for p in "${PROBES[@]}"; do [[ "$p" =~ $NAME_RE ]] || die "bad probe set name: $p"; done
if [ -n "$FB_IDS$FB_MAP" ]; then
    [ -n "$FB_IDS" ] && [ -n "$FB_MAP" ] || die "the fallback needs both the ID list and the mapping"
    FB_HUB=${FB_HUB:-${THUBS[0]:-}}
    [ -n "$FB_HUB" ] && [[ " ${THUBS[*]} " == *" $FB_HUB "* ]] \
        || die "the fallback hub (${FB_HUB:-none}) must be one of the transfer hubs (${THUBS[*]:-none})"
    for f in "$FB_IDS" "$FB_MAP"; do [ -r "$f" ] || die "cannot read $f"; done
fi
[ -r "$ALIASES" ] || die "cannot read $ALIASES"
need sqlite3 awk
v=$(sqlite3 --version | awk '{print $1}')
[ "$(printf '%s\n3.25.0\n' "$v" | sort -V | head -1)" = 3.25.0 ] \
    || die "sqlite3 $v is too old (need 3.25+ for window functions)"

mkdir -p "$(dirname "$out")"
tmp="$out.tmp.$$"
work=$(mktemp -d "$(dirname "$out")/.crosswalk_work.XXXXXX")
trap 'rm -rf "$tmp" "$work"' EXIT
rm -f "$tmp"

# Plain tab files for .import (no header lines). Reference number i = position in REFS.
: > "$work/fm.tsv"; : > "$work/t2g.tsv"; : > "$work/refs.tsv"
declare -A TXLABEL=()
for kv in ${REFERENCE_TRANSCRIPT_SOURCES:-}; do TXLABEL[${kv%%=*}]=${kv#*=}; done
i=0
for x in "${REFS[@]}"; do
    i=$((i + 1))
    IFS='|' read -r name hub map t2g <<< "$x"
    tx=${TXLABEL[$name]:-${name}_transcripts}
    [[ "$tx" =~ $NAME_RE ]] || die "bad transcripts source name: $tx"
    printf '%s\t%s\t%s\t%s\n' "$i" "$name" "$hub" "$tx" >> "$work/refs.tsv"
    awk -F'\t' -v OFS='\t' -v i=$i '$1 == "gene_id" {next} {print i, $1, $2, $3, $4 + 0, $5 + 0}' "$map" >> "$work/fm.tsv"
    awk -F'\t' -v OFS='\t' -v i=$i 'NF >= 2 {print i, $1, $2}' "$t2g" >> "$work/t2g.tsv"
done
printf '%s\n' "${THUBS[@]}" | sed '/^$/d' > "$work/th.tsv"
printf '%s\n' "${PROBES[@]}" | sed '/^$/d' > "$work/probes.tsv"
# Alias lines: alias<TAB>accession<TAB>set[<TAB>type]; lines with fewer than 3 tab fields are
# split on whitespace. Fields containing a space are skipped.
awk -F'\t' -v OFS='\t' '/^alias[ \t]+accession/ {next}
    NF < 3 { n = split($0, f, /[ \t]+/); if (n < 3) next; $0 = f[1] "\t" f[2] "\t" f[3] }
    $1 ~ / / || $2 ~ / / || $3 ~ / / || $1 == "" || $2 == "" || $3 == "" {next}
    {print $1, $2, $3}' "$ALIASES" > "$work/al.tsv"
: > "$work/pg.tsv"; : > "$work/bl.tsv"
if [ -n "$FB_MAP" ]; then
    awk -F'\t' 'NF {print $1}' "$FB_IDS" > "$work/pg.tsv"
    awk -F'\t' -v OFS='\t' -v renames="$FB_RENAME" '
        BEGIN { n = split(renames, r, /[ \t]+/)
                for (i = 1; i <= n; i++) if (split(r[i], kv, "=") == 2) map[kv[1]] = kv[2] }
        /seq_id/ || / / {next}
        {s = $3; if (s in map) s = map[s]; print $1, $2, s}' "$FB_MAP" > "$work/bl.tsv"
fi

sqlite3 "$tmp" >/dev/null <<SQL
PRAGMA journal_mode = OFF;
PRAGMA synchronous = OFF;
PRAGMA temp_store = MEMORY;
CREATE TEMP TABLE refs (run INT PRIMARY KEY, name TEXT, hub TEXT, tx TEXT);
CREATE TEMP TABLE fm (run INT, gene_id TEXT, target_id TEXT, transcriptome TEXT,
                      max_overlap INT, exon_overlap INT);
CREATE TEMP TABLE t2g (run INT, id TEXT, gene TEXT);
CREATE TEMP TABLE th (hub TEXT);
CREATE TEMP TABLE probes (sset TEXT);
CREATE TEMP TABLE al (alias TEXT, acc TEXT, aset TEXT);
CREATE TEMP TABLE pg (id TEXT);
CREATE TEMP TABLE bl (id TEXT, ref_id TEXT, bset TEXT);
.mode tabs
.import '$work/refs.tsv' refs
.import '$work/fm.tsv' fm
.import '$work/t2g.tsv' t2g
.import '$work/th.tsv' th
.import '$work/probes.tsv' probes
.import '$work/al.tsv' al
.import '$work/pg.tsv' pg
.import '$work/bl.tsv' bl

-- reference IDs: which reference each transcript/gene ID belongs to
CREATE TEMP TABLE refid AS
SELECT DISTINCT t.id, t.gene, r.name, r.hub, r.tx, (t.id = t.gene) AS is_gene
  FROM t2g t JOIN refs r ON r.run = t.run;
CREATE INDEX temp.refid_id ON refid (id);

-- rel: one row per (run, hub, hub id, query set, query id); run = reference number
CREATE TEMP TABLE rel (run INT, refset TEXT, ref_id TEXT, sset TEXT, id TEXT,
                       UNIQUE (run, refset, ref_id, sset, id));

-- 1. genome mapping; reference transcripts/genes as targets are labelled by their reference
INSERT OR IGNORE INTO rel
SELECT f.run, r.hub, f.gene_id,
       CASE WHEN i.id IS NULL THEN f.transcriptome
            WHEN i.is_gene THEN i.hub
            ELSE i.tx END,
       f.target_id
  FROM fm f JOIN refs r ON r.run = f.run LEFT JOIN refid i ON i.id = f.target_id;
-- a reference transcript as target also gives a gene-level row
INSERT OR IGNORE INTO rel
SELECT f.run, r.hub, f.gene_id, i.hub, i.gene
  FROM fm f JOIN refs r ON r.run = f.run JOIN refid i ON i.id = f.target_id
 WHERE NOT i.is_gene;
CREATE INDEX temp.rel_sid ON rel (sset, id);
CREATE INDEX temp.rel_ref ON rel (run, refset, ref_id);
CREATE INDEX temp.rel_id  ON rel (run, id);

-- 2. aliases, repeated so aliases of aliases resolve
CREATE TEMP VIEW aliasrows AS
SELECT r.run, r.refset, r.ref_id, a.aset, a.alias
  FROM al a JOIN rel r ON r.sset = a.aset AND r.id = a.acc;
INSERT OR IGNORE INTO rel SELECT * FROM aliasrows;
INSERT OR IGNORE INTO rel SELECT * FROM aliasrows;
INSERT OR IGNORE INTO rel SELECT * FROM aliasrows;

-- 3. fallback for listed IDs that 1-2 do not reach
CREATE TEMP TABLE hits AS
SELECT DISTINCT r.run, b.id, b.ref_id, b.bset
  FROM refs r JOIN pg p JOIN bl b ON b.id = p.id
 WHERE NOT EXISTS (SELECT 1 FROM rel x WHERE x.run = r.run AND x.id = p.id);
INSERT OR IGNORE INTO rel SELECT run, '${FB_HUB:-}', ref_id, bset, id FROM hits;
-- ...and on to the reference genes that fallback-hub ID maps to (not through probe sets).
-- Repeated so a listed ID that itself only maps via the fallback passes its genes on.
CREATE TEMP VIEW backmap AS
SELECT h.run, x.refset, x.ref_id, h.bset, h.id
  FROM hits h JOIN rel x ON x.run = h.run AND x.sset = '${FB_HUB:-}' AND x.id = h.ref_id
 WHERE h.bset NOT IN (SELECT sset FROM probes);
INSERT OR IGNORE INTO rel SELECT * FROM backmap;
INSERT OR IGNORE INTO rel SELECT * FROM backmap;
INSERT OR IGNORE INTO rel SELECT * FROM backmap;

-- 4. transfer hubs: each ID takes the mappings of the reference gene it overlaps best
CREATE TEMP TABLE pick AS
SELECT run, thub, tid, refset, ref_id FROM (
  SELECT c.run, c.sset AS thub, c.id AS tid, c.refset, c.ref_id,
         rank() OVER (PARTITION BY c.run, c.sset, c.id
                      ORDER BY coalesce(s.exon_overlap, -1) DESC, coalesce(s.max_overlap, -1) DESC) AS k
    FROM rel c
    JOIN th ON th.hub = c.sset
    JOIN refs r ON r.run = c.run AND r.hub = c.refset
    LEFT JOIN (SELECT run, gene_id, target_id, transcriptome, max(exon_overlap) AS exon_overlap,
                      max(max_overlap) AS max_overlap
                 FROM fm WHERE transcriptome IN (SELECT hub FROM th)
                GROUP BY run, gene_id, target_id, transcriptome) s
           ON s.run = c.run AND s.gene_id = c.ref_id AND s.target_id = c.id
          AND s.transcriptome = c.sset)
 WHERE k = 1;

CREATE TEMP TABLE outrows (hub TEXT, seq_id TEXT, ref_id TEXT, source TEXT);
INSERT INTO outrows
-- reference hubs
SELECT r.hub, x.id, x.ref_id, x.sset FROM rel x JOIN refs r ON r.run = x.run AND r.hub = x.refset
 WHERE x.id <> x.ref_id
UNION
SELECT r.hub, x.ref_id, x.ref_id, r.hub FROM rel x JOIN refs r ON r.run = x.run AND r.hub = x.refset
UNION
-- transfer hubs: inherited mappings, own (fallback) mappings, and self rows
SELECT p.thub, x.id, p.tid, x.sset
  FROM pick p JOIN rel x ON x.run = p.run AND x.refset = p.refset AND x.ref_id = p.ref_id
 WHERE x.id <> p.tid
UNION
SELECT x.refset, x.id, x.ref_id, x.sset FROM rel x JOIN th ON th.hub = x.refset
 WHERE x.id <> x.ref_id
UNION
SELECT thub, tid, tid, thub FROM pick
UNION
SELECT x.refset, x.ref_id, x.ref_id, x.refset FROM rel x JOIN th ON th.hub = x.refset;

-- crosswalk/1 tables (docs/CROSSWALK_FORMAT.md)
CREATE TABLE map (accession TEXT NOT NULL, accession_lc TEXT NOT NULL, source TEXT NOT NULL,
                  ref_id TEXT NOT NULL, hub TEXT NOT NULL);
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
INSERT INTO map (accession, accession_lc, source, ref_id, hub)
SELECT seq_id, lower(seq_id), source, ref_id, hub FROM outrows
 WHERE ref_id <> 'NA' AND coalesce(seq_id, '') <> '' AND coalesce(ref_id, '') <> ''
   AND coalesce(source, '') <> ''
 ORDER BY hub, ref_id, source, seq_id;
CREATE INDEX m_acc ON map (accession_lc);
CREATE INDEX m_ref ON map (ref_id, hub);
ANALYZE;
INSERT INTO meta (key, value) VALUES
  ('format', 'crosswalk/1'),
  ('organism', '$org'),
  ('built_at', strftime('%Y-%m-%dT%H:%M:%S', 'now') || '+00:00'),
  ('hubs', (SELECT '{' || group_concat('"' || hub || '":' || n, ',') || '}'
              FROM (SELECT hub, COUNT(*) AS n FROM map GROUP BY hub ORDER BY hub))),
  ('rows', (SELECT COUNT(*) FROM map)),
  ('accessions', (SELECT COUNT(DISTINCT accession_lc) FROM map)),
  ('genes', (SELECT COUNT(DISTINCT ref_id) FROM map)),
  ('sources', (SELECT COUNT(DISTINCT source) FROM map));
SQL
sqlite3 "$tmp" 'VACUUM;'

check=$(sqlite3 "$tmp" 'PRAGMA integrity_check;')
[ "$check" = "ok" ] || die "integrity check failed: $check"

if [ -n "$TSVDIR" ]; then
    mkdir -p "$TSVDIR"
    for hub in $(sqlite3 "$tmp" 'SELECT DISTINCT hub FROM map;'); do
        { printf 'seq_id\tref_id\tseq_id_source\n'
          sqlite3 -separator $'\t' "$tmp" "SELECT accession, ref_id, source FROM map WHERE hub = '$hub';"
        } > "$TSVDIR/$hub.mapping"
        echo "wrote $TSVDIR/$hub.mapping"
    done
fi

# Names must be MOOP's: check against organism.sqlite before replacing the old crosswalk.
# MOOP_DB is set by MOOP mode (SPECIES_DIR = a MOOP build folder) or by the config.
if [ -n "${MOOP_DB:-}" ]; then
    need python3
    echo "checking names against $MOOP_DB"
    if ! python3 "$RS_HOME/crosswalk/check_crosswalk.py" "$tmp" "$MOOP_DB"; then
        mv "$tmp" "$out.failed"
        die "crosswalk names do not match MOOP's gene sets (see above); kept as $out.failed, $out unchanged"
    fi
fi

chmod 664 "$tmp"
mv "$tmp" "$out"
trap 'rm -rf "$work"' EXIT
sqlite3 "$out" "SELECT key || ': ' || value FROM meta WHERE key IN ('hubs','rows','accessions','genes','sources');"
echo "wrote $out"

# MOOP mode: also put it where copy2moop ships it, the organism's build folder ($SPECIES_DIR/crosswalk.sqlite
# -> organisms/<Organism>/crosswalk.sqlite). Only a crosswalk that passed the name check above gets here.
# A MOOP build of the organism copies its folder back from /scratch with rsync --delete, which would remove
# or revert a file written meanwhile: take the build's lock first (wait up to 12 h). CROSSWALK_PUBLISH=0
# skips this step.
if [ -n "${MOOP_DB:-}" ] && [ "${CROSSWALK_PUBLISH:-1}" != 0 ] && [ -n "${SPECIES_DIR:-}" ]; then
    pub="$SPECIES_DIR/crosswalk.sqlite"
    if [ "$(readlink -f "$out")" != "$(readlink -f "$pub")" ]; then
        exec 8>>"$SPECIES_DIR/.moop_build.lock"
        echo "waiting for any MOOP build of $org to finish (lock $SPECIES_DIR/.moop_build.lock)"
        flock -w 43200 8 || die "a MOOP build of $org held its lock for 12 h; $out NOT copied to $pub"
        cp "$out" "$pub.tmp$$" && chmod 664 "$pub.tmp$$" && mv "$pub.tmp$$" "$pub"
        flock -u 8
        echo "copied to $pub (copy2moop ships it with $org)"
    fi
fi
