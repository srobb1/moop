#!/usr/bin/bash
# process_one_geneset.sh — build and load ONE gene set.
#
# Usage: process_one_geneset.sh <organism> <assembly> <gene_set>
#
# Called in a loop by scripts/moop_process_genome_data_v2.sbatch, which owns the
# ORGANISM. Everything here is scoped to a single gene set and must stay that way:
# organism.sqlite is shared by all of an organism's gene sets, so anything that
# rewrites the whole database (the FTS index, VACUUM, the annotation-source cache,
# the rsync to moop) belongs to the caller and runs ONCE after every gene set has
# loaded — not once per gene set, which is what it used to do. For a 3-gene-set
# organism that meant three full FTS rebuilds and three VACUUMs of a file growing
# toward 1.8 GB.
#
# This file was moop_process_genome_data_v2.sbatch until 2026-07-27, when the unit of
# work became the organism rather than the gene set. The body is unchanged; what left
# is the SLURM array plumbing at the top and the copy step at the bottom.
#
# Exits non-zero on any failure. The caller stops the organism rather than carrying on
# and copying a half-loaded database to the live site.

THIS_ORG=$1
ASSEMBLY=$2
GENE_SET=$3

[ -n "$THIS_ORG" ] && [ -n "$ASSEMBLY" ] && [ -n "$GENE_SET" ] \
  || { echo "Usage: $0 <organism> <assembly> <gene_set>"; exit 1; }

# Derived from this script's own location so it works however it is invoked. It used
# to be $SLURM_SUBMIT_DIR, which only holds when SLURM starts the process directly.
REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

SCRIPTS=$REPO/scripts
DATA=$REPO/data

## Overridable so a moved tree needs no code edit. It moved once already:
## genomes_v2 -> genomes/v2, and this value was hardcoded in seven files.
source "$(dirname "${BASH_SOURCE[0]}")/paths.sh"


GENESET_DIR=$GENOMES/$THIS_ORG/$ASSEMBLY/$GENE_SET
GENOME_DIR=$GENOMES/$THIS_ORG/$ASSEMBLY
ANALYSIS_DIR=$ANNOTATIONS/$THIS_ORG/$ASSEMBLY/$GENE_SET

ASSEMBLY_DATA=$DATA/$THIS_ORG/$ASSEMBLY
GENESET_DATA=$ASSEMBLY_DATA/$GENE_SET

## Detect which kind of geneset this is
HAS_GFF=false
HAS_T2G=false
[ -s "$GENESET_DIR/genes.gff" ]           && HAS_GFF=true
[ -s "$GENESET_DIR/transcript2gene.txt" ] && HAS_T2G=true

if ! $HAS_GFF && ! $HAS_T2G; then
  ## A reload has ALREADY DROPPED the database by the time we get here, so "nothing to
  ## process" is not a skip -- it is a request to rebuild that produced nothing. Returning
  ## success let the caller carry on and fail three steps later with
  ##     ERROR: organism.sqlite does not exist after processing 1 gene set(s)
  ## which names the symptom and not the cause. That is exactly what happened when
  ## genomes_v2 moved to genomes/v2: the real problem was a directory that no longer
  ## existed, and nothing in the output said so.
  ##
  ## Without --reload this stays a skip: an inactive or not-yet-delivered gene set is a
  ## normal thing to walk past, and the whole run should not stop for it.
  if [ "${MOOP_RELOAD:-0}" = "1" ]; then
    echo "ERROR: $THIS_ORG/$ASSEMBLY/$GENE_SET — reload requested, but there is nothing to build." >&2
    echo "       Looked for, and found neither:" >&2
    echo "         $GENESET_DIR/genes.gff" >&2
    echo "         $GENESET_DIR/transcript2gene.txt" >&2
    if [ ! -d "$GENESET_DIR" ]; then
      echo "       The gene-set directory does not exist at all." >&2
      echo "       GENOMES is currently: $GENOMES" >&2
      echo "       If the data tree moved, set it in scripts/paths.sh (or override GENOMES)." >&2
    fi
    exit 1
  fi
  echo "SKIP: $THIS_ORG/$ASSEMBLY/$GENE_SET — no genes.gff or transcript2gene.txt, nothing to process."
  exit 0
fi

echo "Organism : $THIS_ORG"
echo "Assembly : $ASSEMBLY"
echo "Gene set : $GENE_SET"
echo "Geneset  : $GENESET_DIR"
echo "Analysis : $ANALYSIS_DIR"
echo "Mode     : $($HAS_GFF && echo genome || echo transcriptome)"

## returns true if file exists, is non-empty, and has more than a header line
has_data() { [ -s "$1" ] && [ "$(wc -l < "$1")" -gt 1 ]; }

## Has THIS gene set actually been LOADED into the organism database?
##
## "Built" and "loaded" are different questions and only the second one matters.
## features.tsv existing proves the parser ran; it says nothing about whether the load
## that follows it succeeded. When a load failed, features.tsv was still sitting there,
## so "has_data features.tsv" was satisfied and every future run skipped the gene set
## -- permanently, and silently. It never retried and nothing reported it.
##
## This is the most likely explanation for Schmidtea_mediterranea shipping 1 of its 5
## active gene sets while all five assembly directories copied to the web server
## normally. Checking the RESULT rather than the inputs is the same move check_status.sh
## made in 04f89b7.
geneset_loaded() {
  local db="$DATA/$THIS_ORG/organism.sqlite"
  [ -s "$db" ] || return 1
  local n
  n=$(sqlite3 -readonly "$db" \
        "SELECT COUNT(*) FROM feature f
           JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
          WHERE gs.gene_set_name = '${GENE_SET//\'/\'\'}';" 2>/dev/null)
  [ "${n:-0}" -gt 0 ]
}

## Refuse to load a gene set on top of itself. Both loaders add and update rows but never
## remove them (see delete_gene_set.sh), so a rebuilt gene set loaded over its old rows keeps
## every row the new files no longer contain -- old names, old closest genes -- next to the
## new ones, and nothing reports it. Organism databases are rebuilt, not patched: the build
## stops and says how. A reload (MOOP_RELOAD=1) drops the database, or for a narrowed run
## deletes this gene set, before anything is loaded, so it never reaches this.
refuse_load_on_top() {
  if [ "${MOOP_RELOAD:-0}" != "1" ] && geneset_loaded; then
    echo "ERROR: $THIS_ORG/$ASSEMBLY/$GENE_SET changed and must be loaded again, but it is already" >&2
    echo "       in organism.sqlite, and loading on top would leave its old rows in place." >&2
    echo "       Rebuild the organism's database instead:" >&2
    echo "         MOOP_RELOAD=1 sbatch scripts/moop_process_genome_data_v2.sbatch $THIS_ORG" >&2
    exit 1
  fi
}

infer_source() {
  case "$1" in
    GCF_*) echo "RefSeq"  ;;
    GCA_*) echo "GenBank" ;;
    *)     echo "other"   ;;
  esac
}

write_genome_json() {
  local file="$ASSEMBLY_DATA/genome.json"
  [ -f "$file" ] && return
  printf '{\n  "accession": "%s",\n  "name": "",\n  "source": "%s",\n  "date_added": "%s"\n}\n' \
    "$ASSEMBLY" "$(infer_source "$ASSEMBLY")" "$(date +%Y-%m-%d)" > "$file"
}

write_geneset_json() {
  local source="$1"
  local file="$GENESET_DATA/geneset.json"
  [ -f "$file" ] && return
  printf '{\n  "accession": "%s",\n  "name": "",\n  "source": "%s",\n  "date_added": "%s"\n}\n' \
    "$GENE_SET" "$source" "$(date +%Y-%m-%d)" > "$file"
}

ensure_organism_json() {
  local parents="$1" children="$2"
  local file="$DATA/$THIS_ORG/organism.json"
  [ -f "$file" ] && return
  perl "$REPO/analysis_parsers/make_organisms_config.pl" \
    "$GENESET_DIR/metadata.yaml" "$parents" "$children" \
    && mv organism.json "$file"
}

## log missing input files before doing any work
MISSING_LOG="$REPO/missing_files.log"
log_missing() { echo "$THIS_ORG	$1" >> "$MISSING_LOG"; }

check_missing_files_gff() {
  [ -s "$GENESET_DIR/genes.gff" ]                                      || log_missing "genes.gff"
  [ -s "$GENESET_DIR/protein.aa.fa" ]                                  || log_missing "protein.aa.fa"
  [ -s "$GENESET_DIR/cds.nt.fa" ]                                      || log_missing "cds.nt.fa"
  [ -s "$GENESET_DIR/transcript.nt.fa" ]                               || log_missing "transcript.nt.fa"
  [ -s "$GENESET_DIR/metadata.yaml" ]                                  || log_missing "metadata.yaml"
  [ -f "$ANALYSIS_DIR/diamond/UNIPROT_sprot/diamond_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/diamond/UNIPROT_sprot/diamond_results.tsv" ]     || log_missing "diamond/UNIPROT_sprot/diamond_results.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/eggnog_mapper/eggnog_mapper_results.tsv" ]             || log_missing "eggnog_mapper/eggnog_mapper_results.tsv"
  [ -f "$ANALYSIS_DIR/interproscan/interproscan_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/interproscan/interproscan_results.tsv" ]                || log_missing "interproscan/interproscan_results.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/signalp6/signalp6_results.tsv" ]                || log_missing "signalp6/signalp6_results.tsv"
  [ -f "$ANALYSIS_DIR/deeptmhmm/deeptmhmm_results.gff3" ]             || log_missing "deeptmhmm/deeptmhmm_results.gff3"
}

check_missing_files_t2g() {
  [ -s "$GENESET_DIR/protein2gene.txt" ]                               || log_missing "protein2gene.txt"
  [ -s "$GENESET_DIR/transcript2gene.txt" ]                            || log_missing "transcript2gene.txt"
  [ -s "$GENESET_DIR/protein.aa.fa" ]                                  || log_missing "protein.aa.fa"
  [ -s "$GENESET_DIR/transcript.nt.fa" ]                               || log_missing "transcript.nt.fa"
  [ -s "$GENESET_DIR/metadata.yaml" ]                                  || log_missing "metadata.yaml"
  [ -f "$ANALYSIS_DIR/diamond/UNIPROT_sprot/diamond_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/diamond/UNIPROT_sprot/diamond_results.tsv" ]     || log_missing "diamond/UNIPROT_sprot/diamond_results.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/eggnog_mapper/eggnog_mapper_results.tsv" ]             || log_missing "eggnog_mapper/eggnog_mapper_results.tsv"
  [ -f "$ANALYSIS_DIR/interproscan/interproscan_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/interproscan/interproscan_results.tsv" ]                || log_missing "interproscan/interproscan_results.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/signalp6/signalp6_results.tsv" ]                || log_missing "signalp6/signalp6_results.tsv"
  [ -f "$ANALYSIS_DIR/deeptmhmm/deeptmhmm_results.gff3" ]             || log_missing "deeptmhmm/deeptmhmm_results.gff3"
}

mkdir -p "$GENESET_DATA"
write_genome_json
cd "$GENESET_DATA"
[ -e tophit.tsv ] && rm tophit.tsv

## A reload must invalidate the PARSED ANNOTATION FILES too, not just the database.
##
## Every make_*_moop() below is gated on its own output already existing
## (`has_data PANTHER.iprscan.moop.tsv || make_interproscan_moop`), and those gates
## run BEFORE MOOP_RELOAD sets REBUILD further down. So --reload dropped
## organism.sqlite and then rebuilt it from whatever .moop.tsv files happened to be
## sitting here -- however old.
##
## That is not hypothetical. On Bipalium_kewense the source iprscan.tsv is a SYMLINK
## into the analysis tree, so it silently followed a re-run of InterProScan, while
## the parsed files stayed as generated on 2026-07-06:
##
##   iprscan.tsv               -> symlink, follows the live analysis
##   Pfam.iprscan.moop.tsv        2026-07-06 15:33   ids WITHOUT .p1
##   (source iprscan.tsv today)                      ids WITH    .p1
##
## Nothing strips .p1 anywhere in this pipeline -- the parsed files simply predated
## the ids they were being matched against. 49% of annotation lines failed to attach
## for three weeks, and every reload faithfully reproduced it.
##
## Regenerating is cheap (a parse of files already on disk) and re-running the
## ANALYSIS is not needed. Deleting only the derived .moop.tsv files and the source
## copies/symlinks that feed them; the FASTAs and features.tsv are handled by
## REBUILD further down.
if [ "${MOOP_RELOAD:-0}" = "1" ]; then
  echo "Reload: discarding parsed annotation files so they are rebuilt from the current analysis"
  rm -f ./*.moop.tsv iprscan.tsv tophit.tsv
fi

# ── Diamond / BLAST homologs ──────────────────────────────────────────────────
make_diamond_moop() {
  local DBLAST_DIR="$ANALYSIS_DIR/diamond"
  local SPROT_DIR="$DBLAST_DIR/UNIPROT_sprot"
  local VERSION
  VERSION=$(head -1 "$SPROT_DIR/db_version.txt" 2>/dev/null)

  [ -e tophit.tsv ] && rm tophit.tsv
  if [ -e "$SPROT_DIR/diamond_results.tsv.gz" ]; then
    zcat "$SPROT_DIR/diamond_results.tsv.gz" > tophit.tsv
  else
    ln -s "$SPROT_DIR/diamond_results.tsv" tophit.tsv
  fi
  ls -l tophit.tsv
  perl "$REPO/analysis_parsers/parse_DIAMOND_to_MOOP_TSV.pl" tophit.tsv \
    'UniProtKB/Swiss-Prot' "$VERSION" https://www.uniprot.org https://www.uniprot.org/uniprotkb/

  shopt -s nullglob
  for RESULTS in "$DBLAST_DIR"/ENS_*/; do
    shopt -u nullglob
    TARGET_ORG=$(basename "$RESULTS")
    ORGSTRING="${TARGET_ORG#ENS_}"
    ORG=$(echo "$ORGSTRING" | perl -pe 's/^(\w)/\u$1/; s/_(\w)/ \L$1/g')
    VERSION=$(head -1 "$RESULTS/db_version.txt" 2>/dev/null)
    [ -e tophit.tsv ] && rm tophit.tsv
    if [ -e "$RESULTS/diamond_results.tsv.gz" ]; then
      zcat "$RESULTS/diamond_results.tsv.gz" > tophit.tsv
    elif [ -e "$RESULTS/diamond_results.tsv" ]; then
      ln -s "$RESULTS/diamond_results.tsv" tophit.tsv
    else
      echo "$RESULTS/tophit.tsv does not exist"; continue
    fi
    ls -l tophit.tsv
    perl "$REPO/analysis_parsers/parse_DIAMOND_to_MOOP_TSV.pl" tophit.tsv \
      "Ensembl $ORG" "$VERSION" https://www.ensembl.org/ "https://www.ensembl.org/Multi/Search/Results?q="
  done
  shopt -u nullglob
}
has_data UniProtKB_Swiss-Prot.homologs.moop.tsv \
  || { echo "Building Diamond moop files"; make_diamond_moop; }

# ── EggNOG ────────────────────────────────────────────────────────────────────
make_eggnog_moop() {
  local EDIR="$ANALYSIS_DIR/eggnog_mapper"
  local MAPPERVERSION DBVERSION VERSION
  MAPPERVERSION=$(grep emapper "$EDIR/db_version.txt" 2>/dev/null \
                  | perl -pe 's/.*(emapper-\S+).*/$1/')
  DBVERSION=$(grep 'eggNOG DB version:' "$EDIR/db_version.txt" 2>/dev/null \
              | perl -pe 's/.*?eggNOG DB version: (\S+).*/$1/')
  VERSION="$MAPPERVERSION DB_$DBVERSION"
  echo "perl $REPO/analysis_parsers/parse_EggNOG_to_MOOP_TSV.pl $EDIR/eggnog_mapper_results.tsv $VERSION"
  perl "$REPO/analysis_parsers/parse_EggNOG_to_MOOP_TSV.pl" "$EDIR/eggnog_mapper_results.tsv" "$VERSION"
  source /home/smr/miniconda3/etc/profile.d/conda.sh
  conda activate goatools
  python3 "$REPO/analysis_parsers/reduce_eggnog_go.moop.py" EggNOG2GO.eggnog.moop.tsv
}
has_data EggNOG2GO.eggnog.moop.tsv \
  || { echo "Building EggNOG moop files"; make_eggnog_moop; }

# ── InterProScan ──────────────────────────────────────────────────────────────
make_interproscan_moop() {
  local IDIR="$ANALYSIS_DIR/interproscan"
  local VERSION
  VERSION=$(cat "$IDIR/interproscan_version.txt" 2>/dev/null)
  rm -f iprscan.tsv
  if [ -e "$IDIR/interproscan_results.tsv.gz" ]; then
    zcat "$IDIR/interproscan_results.tsv.gz" > iprscan.tsv
  else
    ln -s "$IDIR/interproscan_results.tsv" iprscan.tsv
  fi
  perl "$REPO/analysis_parsers/parse_InterProScan_to_MOOP_TSV.pl" iprscan.tsv "$VERSION"
}
has_data PANTHER.iprscan.moop.tsv \
  || { echo "Building InterProScan moop files"; make_interproscan_moop; }

# ── ProtNLM ───────────────────────────────────────────────────────────────────
## Not currently run for any organism (checked 2026-09-21: zero protnlm/ directories
## anywhere under $ANNOTATIONS) -- decided to leave it off for now rather than run
## it. Same skip pattern as SignalP/DeepTMHMM below, so a future organism that DOES
## ship protnlm_pred_results.tsv is picked up automatically, with no code change
## needed to turn it back on.
make_protnlm_moop() {
  local PDIR="$ANALYSIS_DIR/protnlm"
  if [ ! -s "$PDIR/protnlm_pred_results.tsv" ]; then
    echo "No ProtNLM results at $PDIR — skipping"
    return 0
  fi
  perl "$REPO/analysis_parsers/parse_ProtNLM_to_MOOP_TSV.pl" "$PDIR/protnlm_pred_results.tsv"
}
has_data protnlm.moop.tsv \
  || { echo "Building ProtNLM moop files"; make_protnlm_moop; }

# ── SignalP 6 ─────────────────────────────────────────────────────────────────
make_signalp_moop() {
  local SDIR="$ANALYSIS_DIR/signalp6"
  if [ ! -s "$SDIR/signalp6_results.tsv" ]; then
    echo "No SignalP results at $SDIR — skipping"
    return 0
  fi
  perl "$REPO/analysis_parsers/parse_SIGNALP_to_MOOP_TSV.pl" "$SDIR/signalp6_results.tsv"
}
has_data SignalP.domains.moop.tsv \
  || { echo "Building SignalP moop files"; make_signalp_moop; }

# ── DeepTMHMM ─────────────────────────────────────────────────────────────────
make_deeptmhmm_moop() {
  local TDIR="$ANALYSIS_DIR/deeptmhmm"
  local VERSION
  if [ ! -s "$TDIR/deeptmhmm_results.gff3" ]; then
    echo "No DeepTMHMM results at $TDIR — skipping"
    return 0
  fi
  ## deeptmhmm_version.txt holds a banner like
  ##   "### DeepTMHMM 1.0 - Academic Version ###"
  ## Strip it to the bare version number -- annotation_source_version is part
  ## of a uniqueness key and is shown as-is in the source picker/MOOPmart, so
  ## the full banner text must not land there.
  VERSION=$(sed -n 's/^#*[[:space:]]*DeepTMHMM[[:space:]]*\([0-9.]*\).*/\1/p' \
              "$TDIR/deeptmhmm_version.txt" 2>/dev/null | head -1)
  VERSION=${VERSION:-1.0}
  perl "$REPO/analysis_parsers/parse_DEEPTMHMM_to_MOOP_TSV.pl" \
    "$TDIR/deeptmhmm_results.gff3" "$VERSION"
}
has_data DeepTMHMM.domains.moop.tsv \
  || { echo "Building DeepTMHMM moop files"; make_deeptmhmm_moop; }

# ── RBBH — reciprocal best BLAST hits ────────────────────────────────────────
make_rbbh_moop() {
  local RBBH_BASE="$ANALYSIS_DIR/rbh_eross"
  local RESULTS TARGET_ORG ORG OUT VERSION REF_DIR DESC cand
  local -a RESULT_TSV FASTA

  shopt -s nullglob
  for RESULTS in "$RBBH_BASE"/*/; do
    shopt -u nullglob
    RESULTS="${RESULTS%/}"
    shopt -s nullglob
    RESULT_TSV=("$RESULTS"/*results.tsv)
    shopt -u nullglob
    [ "${#RESULT_TSV[@]}" -gt 0 ] || continue

    TARGET_ORG=$(basename "$RESULTS")                             # ENS_homo_sapiens
    ORG="${TARGET_ORG#ENS_}"; ORG="${ORG//_/ }"; ORG="${ORG^}"    # Homo sapiens
    OUT="Ensembl_${ORG// /_}.RBBH.moop.tsv"
    has_data "$OUT" && continue

    ## Version follows the reference DB's 'current' symlink (release-113 is the fallback).
    REF_DIR="$REF_DB/$TARGET_ORG/current"
    VERSION=$(basename "$(readlink -f "$REF_DIR" 2>/dev/null)" 2>/dev/null)
    [[ "$VERSION" == release-* ]] || VERSION=release-113

    ## desc.txt (hit_id<TAB>description<TAB>symbol) fills the Accession_Description
    ## column. Look, in order: shipped with the results (old RBBH_v2 tree) -> a shared
    ## copy next to the reference FASTA -> a copy an earlier run left here -> else
    ## generate one here from $REF_DIR/*.pep.all.fa.gz and reuse it next time. The
    ## rbh_eross runs ship no desc.txt, so without this every homolog loads blank.
    DESC=""
    for cand in "$RESULTS/desc.txt" "$REF_DIR/desc.txt" "$TARGET_ORG.desc.txt"; do
      [ -s "$cand" ] && { DESC="$cand"; break; }
    done
    if [ -z "$DESC" ]; then
      shopt -s nullglob
      FASTA=("$REF_DIR"/*.pep.all.fa.gz "$REF_DIR"/*.pep.all.fa)
      shopt -u nullglob
      if [ "${#FASTA[@]}" -gt 0 ]; then
        echo "Building RBBH desc.txt for $TARGET_ORG from ${FASTA[0]}"
        { [[ "${FASTA[0]}" == *.gz ]] && zcat "${FASTA[0]}" || cat "${FASTA[0]}"; } \
          | perl "$REPO/analysis_parsers/rbbh/getDesc_ENS_FA.pl" /dev/stdin > "$TARGET_ORG.desc.txt" \
          && DESC="$TARGET_ORG.desc.txt"
      else
        echo "WARNING: no peptide FASTA under $REF_DIR — $TARGET_ORG RBBH descriptions will be blank" >&2
      fi
    fi

    echo "Building RBBH moop files for $TARGET_ORG"
    perl "$REPO/analysis_parsers/parse_RBBH_to_MOOP_TSV.pl" "$RESULTS" "Ensembl $ORG" "$VERSION" \
      https://www.ensembl.org/ "https://www.ensembl.org/Multi/Search/Results?q=" "$DESC"
  done
  shopt -u nullglob
}
## One .RBBH.moop.tsv per target organism, so the has_data gate is per-target
## inside the loop rather than the single driver line the blocks above use.
make_rbbh_moop

# ── MMseqs2 RBH (rbh_mmseq) ───────────────────────────────────────────────────
## Same reference species as rbh_eross, from a second tool; separate files and a
## separate source ("... (MMseqs2 RBH)") so both show on the gene page. Named
## <Source>.MMseqs.RBBH.moop.tsv so the loader's *.RBBH.moop.tsv pattern takes them.
make_mmseqs_rbh_moop() {
  local RESULTS TARGET_ORG ORG OUT VERSION REF_FASTA
  local -a FASTA
  shopt -s nullglob
  for RESULTS in "$ANALYSIS_DIR/rbh_mmseq"/ENS_*/; do
    RESULTS="${RESULTS%/}"
    [ -s "$RESULTS/rbh_mmseq_results.tsv" ] || continue
    TARGET_ORG=$(basename "$RESULTS")                             # ENS_homo_sapiens
    ORG="${TARGET_ORG#ENS_}"; ORG="${ORG//_/ }"; ORG="${ORG^}"    # Homo sapiens
    OUT="Ensembl_${ORG// /_}.MMseqs.RBBH.moop.tsv"
    has_data "$OUT" && continue
    ## the FASTA the search database was built from, else the release's full proteome
    FASTA=()
    for REF_FASTA in "$REF_DB/$TARGET_ORG/current"/peptide.fa.gz "$REF_DB/$TARGET_ORG/current"/*.pep.all.fa.gz; do
      [ -s "$REF_FASTA" ] && FASTA+=("$REF_FASTA")
    done
    if [ "${#FASTA[@]}" -eq 0 ]; then
      echo "WARNING: no peptide FASTA under $REF_DB/$TARGET_ORG/current — skipping MMseqs2 RBH for $TARGET_ORG" >&2
      continue
    fi
    VERSION=$(awk '{print $NF; exit}' "$RESULTS/db_version.txt" 2>/dev/null)
    echo "Building MMseqs2 RBH moop file for $TARGET_ORG"
    perl "$REPO/analysis_parsers/parse_MMSEQS_RBH_to_MOOP_TSV.pl" "$RESULTS/rbh_mmseq_results.tsv" "${FASTA[0]}" \
      "Ensembl $ORG" "${VERSION:-unknown}" https://www.ensembl.org/ "https://www.ensembl.org/Multi/Search/Results?q="
  done
  shopt -u nullglob
}
make_mmseqs_rbh_moop


# ── OMA (standalone, optional) ────────────────────────────────────────────────
## Not part of the big per-org ANALYSIS_DIR pipeline — OMA is run by hand under
## $OMA_BASE/<organism>/<assembly>/<geneset> (paths.sh). One moop table per partner
## species and id database for the OMA groups, pairwise orthologs and HOG orthologs, so
## users can pick the species they care about; the relationship is in each description.
##
## A gene set that IS one of the template's reference genomes (e.g. Nematostella RefSeq =
## NEMVE, Drosophila FlyBase = DROME) must not have an OMA run of its own. Its orthologs come
## from the template's reference run ($OMA_REFERENCE_RUN) under the reference's code, with
## every OMA id mapped back to this gene set's own protein ids by identical sequence
## (oma_reference_id_map.tsv), so nothing downstream ever shows the reference's OMA ids.
## This is checked for every gene set (cached in oma_reference_check.tsv), so nobody has to
## know it is a reference -- and a reference genome's own OMA run (e.g. one made before this
## check existed) is ignored, since it has the genome in OMA twice.
OMA_DIR="$OMA_BASE/$THIS_ORG/$ASSEMBLY/$GENE_SET"
OMA_SRC="$OMA_DIR"      # the run whose Output/ is read
OMA_CODE=""
OMA_ID_MAP=""
HGNC_TABLE="$REFERENCE_DATA/hgnc/hgnc_complete_set.txt"

IS_REFERENCE_GENOME=false
if [ -d "$OMA_REFERENCE_RUN/DB" ] && [ -s "$GENESET_DIR/protein.aa.fa" ]; then
  ## cached: redone only when protein.aa.fa or the reference run's genomes change
  if [ ! -s oma_reference_check.tsv ] || [ "$GENESET_DIR/protein.aa.fa" -nt oma_reference_check.tsv ] \
     || [ "$OMA_REFERENCE_RUN/DB" -nt oma_reference_check.tsv ]; then
    echo "Checking whether this gene set is one of the OMA reference genomes"
    perl "$REPO/analysis_parsers/find_reference_genome.pl" "$OMA_REFERENCE_RUN/DB" \
      "$GENESET_DIR/protein.aa.fa" oma_reference_id_map.tsv > oma_reference_check.tsv.tmp
    REF_STATUS=$?
    mv oma_reference_check.tsv.tmp oma_reference_check.tsv
    [ $REF_STATUS -eq 0 ] || rm -f oma_reference_id_map.tsv
  fi
  if [ -s oma_reference_id_map.tsv ]; then
    REF_CODE=$(head -1 oma_reference_check.tsv | cut -f1)
    REF_PERCENT=$(head -1 oma_reference_check.tsv | cut -f2)
    echo "This gene set is the OMA reference genome $REF_CODE ($REF_PERCENT% identical proteins)"
    IS_REFERENCE_GENOME=true
    [ -d "$OMA_DIR/Output" ] && [ ! -e "$OMA_DIR/REFERENCE_GENOME.txt" ] \
      && echo "WARNING: ignoring the gene set's own OMA run in $OMA_DIR (it has $REF_CODE in OMA twice); using the reference run. Move that run aside and run make_oma_db_files.pl there to record this." >&2
    if [ -s "$OMA_REFERENCE_RUN/Output/HierarchicalGroups.orthoxml" ]; then
      OMA_SRC="$OMA_REFERENCE_RUN"
      OMA_CODE="$REF_CODE"
      OMA_ID_MAP="$PWD/oma_reference_id_map.tsv"
    else
      echo "WARNING: the reference run ($OMA_REFERENCE_RUN) has not finished; no OMA orthologs for this gene set yet" >&2
    fi
  elif [ -e "$OMA_DIR/REFERENCE_GENOME.txt" ]; then
    echo "WARNING: $OMA_DIR/REFERENCE_GENOME.txt says this is a reference genome, but under 50% of its proteins are identical to one (see oma_reference_check.tsv)" >&2
  fi
fi

if [ -z "$OMA_CODE" ] && ! $IS_REFERENCE_GENOME && [ -d "$OMA_DIR/Output" ] && [ ! -e "$OMA_DIR/REFERENCE_GENOME.txt" ]; then
  FIRST_PROT_ID=$(grep -m1 ">" "$GENESET_DIR/protein.aa.fa" 2>/dev/null | sed 's/^>//' | awk '{print $1}')
  OMA_CODE=$(grep -F -m1 "$FIRST_PROT_ID" "$OMA_DIR/Output/Map-SeqNum-ID.txt" 2>/dev/null | cut -f1)
  [ -z "$OMA_CODE" ] && echo "WARNING: OMA dir found ($OMA_DIR) but couldn't determine this organism's OMA species code from Map-SeqNum-ID.txt"
fi

if [ -n "$OMA_CODE" ]; then
  ## the template a run was made from (README.exportedAllAll), else the run dir name
  OMA_TEMPLATE=$(sed -n 's/^OMA template:[[:space:]]*//p' "$OMA_SRC/README.exportedAllAll" 2>/dev/null | head -1)
  OMA_VERSION="OMA 2.7.0 ${OMA_TEMPLATE:-$(basename "$(dirname "$(realpath "$OMA_SRC/Output")")")}"
  [ -n "$OMA_ID_MAP" ] && OMA_VERSION="$OMA_VERSION reference run"
  echo "OMA species code: $OMA_CODE (version: $OMA_VERSION)"

  ## one file per partner and id database ("$PARTNER.$NAMESPACE.oma_orthologs.moop.tsv"),
  ## never one named after $OMA_CODE itself, so the skip-check looks for any such file
  shopt -s nullglob
  existing_orthologs=(*.oma_orthologs.moop.tsv)
  shopt -u nullglob
  if [ ${#existing_orthologs[@]} -eq 0 ]; then
    echo "Building OMA orthologs for $OMA_CODE"
    perl "$REPO/analysis_parsers/parse_OMA_orthologs_to_MOOP_TSV.pl" \
      "$OMA_SRC/Output/OrthologousGroups.txt" "$OMA_CODE" "$OMA_VERSION" "$HGNC_TABLE" $OMA_ID_MAP
  fi

  ## Pairwise files are named <A>-<B>.txt; only the ones with our code as a whole
  ## side are ours (a substring match would take NEMVE-HUMAN.txt for NEMVEC).
  shopt -s nullglob
  for PAIR_FILE in "$OMA_SRC/Output/PairwiseOrthologs/"*.txt; do
    PAIR_NAME=$(basename "$PAIR_FILE" .txt)
    if [[ "$PAIR_NAME" == "$OMA_CODE-"* ]]; then
      THISORG_FIRST=1
      OTHERORG="${PAIR_NAME#"$OMA_CODE-"}"
    elif [[ "$PAIR_NAME" == *"-$OMA_CODE" ]]; then
      THISORG_FIRST=0
      OTHERORG="${PAIR_NAME%"-$OMA_CODE"}"
    else
      continue
    fi
    has_data "${OTHERORG}."*".oma_pairs.moop.tsv" \
      || { echo "Building OMA pairs for $OMA_CODE vs $OTHERORG"
           perl "$REPO/analysis_parsers/parse_OMA_pairs_to_MOOP_TSV.pl" \
             "$PAIR_FILE" "$OMA_CODE" "$OTHERORG" "$THISORG_FIRST" "$OMA_VERSION" "$HGNC_TABLE" $OMA_ID_MAP; }
  done
  shopt -u nullglob

  shopt -s nullglob
  existing_hogs=(*.oma_hog.moop.tsv)
  shopt -u nullglob
  if [ ${#existing_hogs[@]} -eq 0 ] && [ -s "$OMA_SRC/Output/HierarchicalGroups.orthoxml" ]; then
    echo "Building OMA HOG orthologs for $OMA_CODE"
    perl "$REPO/analysis_parsers/parse_OMA_HOG_to_MOOP_TSV.pl" \
      "$OMA_SRC/Output/HierarchicalGroups.orthoxml" "$OMA_CODE" "$OMA_VERSION" "$HGNC_TABLE" $OMA_ID_MAP \
      || { echo "ERROR: failed to build OMA HOG orthologs"; exit 1; }
  fi

  ## OMA GO terms: the target's predictions, or for a reference genome (reference run) its
  ## exported GO annotation plus OMA's predictions, mapped to this gene set's ids.
  ## go.tsv comes from the run's mapGO/get_OMA_GO_terms.sh.
  if [ -s "$OMA_SRC/mapGO/go.tsv" ] && [ -s "$OMA_SRC/Output/Map-SeqNum-ID.txt" ] && [ -s "$OMA_SRC/Output/gene_function.gaf" ]; then
    has_data "${OMA_CODE}.OMA2GO.moop.tsv" \
      || { echo "Building OMA2GO for $OMA_CODE"
           perl "$REPO/analysis_parsers/parse_OMA2GO_to_MOOP_TSV.pl" "$OMA_CODE" "$OMA_VERSION" \
             "$OMA_SRC/mapGO/go.tsv" "$OMA_SRC/Output/Map-SeqNum-ID.txt" "$OMA_SRC/Output/gene_function.gaf" $OMA_ID_MAP \
             > "${OMA_CODE}.OMA2GO.moop.tsv.tmp" \
             && mv "${OMA_CODE}.OMA2GO.moop.tsv.tmp" "${OMA_CODE}.OMA2GO.moop.tsv" \
             || { rm -f "${OMA_CODE}.OMA2GO.moop.tsv.tmp"; echo "ERROR: failed to build OMA2GO"; exit 1; }
         }
  fi
fi

## remove previous entries for this org and check for missing files
sed -i "/^${THIS_ORG}\t/d" "$MISSING_LOG" 2>/dev/null

# ── Gene naming v2 (notes/NAMING_V2_PLAN.md) ──────────────────────────────────
## assign_gene_names_v2.pl gives every gene a name (geneNames.tsv) and, separately, its
## closest human gene and closest gene in each closest_species of geneset_config.yaml
## (closest_<tag>.tsv for the GFF, closest_<tag>.moop.tsv for the database). It reads the analysis
## results directly (OMA, MMseqs2 RBH, DIAMOND, InterProScan) plus the reference data in
## $REFERENCE_DATA; run scripts/update_reference_data.sh before a full reprocess.
## Fills NAMING_ARGS; run_naming_v2 adds --isoforms and the outputs (the caller, --native).
build_naming_args() {
  ## Per-gene-set naming inputs (human-curated names, closest species, naming species) live
  ## in scripts/geneset_config.yaml, not here. geneset_config.pl checks the whole file: a problem in THIS gene set's entry stops the build, one in any
  ## other entry is a warning; it prints this gene set's options NUL-separated.
  local CONFIG_ARGS=()
  local REQUIRED_FILES=()
  perl "$SCRIPTS/geneset_config.pl" "$SCRIPTS/geneset_config.yaml" "$THIS_ORG" "$ASSEMBLY" "$GENE_SET" \
    > geneset_config.args \
    || { rm -f geneset_config.args; echo "ERROR: geneset_config.yaml is invalid (above)"; exit 1; }
  mapfile -d '' -t CONFIG_ARGS < geneset_config.args
  rm -f geneset_config.args

  NAMING_ARGS=(--protein-fasta "$GENESET_DIR/protein.aa.fa"
               --hgnc-dir "$REFERENCE_DATA/hgnc"
               --compara-dir "$REFERENCE_DATA/ensembl_compara"
               --uniprot-dir "$REFERENCE_DATA/uniprot"
               --taxonomy-dir "$REFERENCE_DATA/ncbi_taxonomy"
               --ref-db "$REF_DB"
               "${CONFIG_ARGS[@]}")
  [ -s "$GENESET_DIR/protein2gene.txt" ] && NAMING_ARGS+=(--protein2gene "$GENESET_DIR/protein2gene.txt")
  [ -n "$OMA_CODE" ]                     && NAMING_ARGS+=(--oma-dir "$OMA_SRC" --oma-code "$OMA_CODE")
  [ -n "$OMA_ID_MAP" ]                   && NAMING_ARGS+=(--oma-id-map "$OMA_ID_MAP")
  [ -d "$ANALYSIS_DIR/rbh_mmseq" ]       && NAMING_ARGS+=(--mmseqs-dir "$ANALYSIS_DIR/rbh_mmseq")
  [ -d "$ANALYSIS_DIR/diamond" ]         && NAMING_ARGS+=(--diamond-dir "$ANALYSIS_DIR/diamond")
  ## the raw InterProScan results name genes twice: by PANTHER family, when the match covers
  ## most of the family's model (the model lengths, from update_reference_data.sh), and by
  ## InterPro domain ("X domain-containing protein"; the entry list tells a domain from a family)
  local IPRSCAN_RESULTS
  for IPRSCAN_RESULTS in "$ANALYSIS_DIR/interproscan/interproscan_results.tsv.gz" "$ANALYSIS_DIR/interproscan/interproscan_results.tsv"; do
    if [ -s "$IPRSCAN_RESULTS" ]; then
      NAMING_ARGS+=(--interproscan "$IPRSCAN_RESULTS" --interpro-entries "$REFERENCE_DATA/interpro/entry.list"
                    --panther-hmm-lengths "$REFERENCE_DATA/panther/hmm_lengths.tsv")
      REQUIRED_FILES+=("$REFERENCE_DATA/interpro/entry.list" "$REFERENCE_DATA/panther/hmm_lengths.tsv")
      break
    fi
  done

  ## the gene set's species, taxon and accessions, for the header of naming_decisions.tsv
  [ -s "$GENESET_DIR/metadata.yaml" ] && NAMING_ARGS+=(--metadata "$GENESET_DIR/metadata.yaml")

  ## PANTHER tree placements: where TreeGrafter puts each protein on its PANTHER family tree
  ## (graft points are only in InterProScan's JSON), traced to human genes with PANTHER's
  ## TreeGrafter data (update_reference_data.sh panther_trees) and the species' lineage (its
  ## ncbi-taxon-id). Without the JSON, the trees or the taxon id, naming runs without the tree.
  local IPRSCAN_JSON PANTHER_RELEASE TREES TAXID
  PANTHER_RELEASE=$(sed -n 's/^PANTHER release \([0-9.]*\):.*/\1/p' "$REFERENCE_DATA/panther/VERSION.txt" 2>/dev/null | head -1)
  TREES="$REFERENCE_DATA/panther/treegrafter/$PANTHER_RELEASE/PANTHER${PANTHER_RELEASE}_data"
  TAXID=$(sed -n 's/^ncbi-taxon-id:[[:space:]]*//p' "$GENESET_DIR/metadata.yaml" 2>/dev/null | head -1 | tr -d "\"' \r")
  for IPRSCAN_JSON in "$ANALYSIS_DIR/interproscan/interproscan_results.json.gz" "$ANALYSIS_DIR/interproscan/interproscan_results.json"; do
    [ -s "$IPRSCAN_JSON" ] || continue
    if [ -z "$PANTHER_RELEASE" ] || [ ! -d "$TREES/Tree_MSF" ]; then
      echo "WARNING: no PANTHER TreeGrafter data at $TREES (scripts/update_reference_data.sh panther_trees); naming without the tree"
    elif [ -z "$TAXID" ]; then
      echo "WARNING: no ncbi-taxon-id in $GENESET_DIR/metadata.yaml; naming without the PANTHER tree"
    elif python3 "$SCRIPTS/panther_placements.py" --json "$IPRSCAN_JSON" --trees "$TREES" \
           --hmm-lengths "$REFERENCE_DATA/panther/hmm_lengths.tsv" --taxonomy-dir "$REFERENCE_DATA/ncbi_taxonomy" \
           --taxid "$TAXID" --out panther_placements.tsv; then
      NAMING_ARGS+=(--panther-placements panther_placements.tsv)
    else
      echo "ERROR: panther_placements.py failed"; exit 1
    fi
    break
  done

  local file
  for file in "$REFERENCE_DATA/hgnc/hgnc_complete_set.txt" "${REQUIRED_FILES[@]}"; do
    if [ ! -s "$file" ]; then
      echo "ERROR: Required file $file is missing! Gene naming will fail."
      case "$file" in "$REFERENCE_DATA"/*) echo "       Reference data: run scripts/update_reference_data.sh" ;; esac
      exit 1
    fi
  done
}

## geneNames.tsv and closest_human.tsv/.moop.tsv present; otherwise naming reruns (gene sets
## named before closest_<tag>.tsv existed have none). A geneset_config.yaml change is NOT
## detected here -- that needs MOOP_RELOAD=1, which deletes the *.moop.tsv files.
naming_outputs_current() {
  has_data geneNames.tsv && has_data closest_human.tsv && has_data closest_human.moop.tsv
}

## run assign_gene_names_v2.pl into geneNames.tsv, closest_<tag>.tsv/.moop.tsv and
## gene_name_source.<kind>.moop.tsv (args: extra options). Built in a scratch directory, then
## swapped in: every old closest_* and gene_name_source file goes, so nothing stale is loaded.
run_naming_v2() {
  build_naming_args
  rm -rf naming.tmp && mkdir naming.tmp
  perl "$REPO/analysis_parsers/assign_gene_names_v2.pl" "${NAMING_ARGS[@]}" --isoforms isoforms.tsv "$@" \
    --out-names naming.tmp/geneNames.tsv --out-dir naming.tmp \
    || { rm -rf naming.tmp; echo "ERROR: gene naming (assign_gene_names_v2.pl) failed"; exit 1; }
  rm -f closest_*.tsv gene_name_source.*.moop.tsv
  mv naming.tmp/* . && rmdir naming.tmp
}

## the closest_<tag>.tsv files, human first, for addClosestToGFF.pl
closest_files() {
  printf '%s\n' closest_human.tsv
  ls closest_*.tsv 2>/dev/null | grep -v -e '^closest_human\.tsv$' -e '\.moop\.tsv$'
}

# ═════════════════════════════════════════════════════════════════════════════
# GFF PATH — genome-backed geneset
# ═════════════════════════════════════════════════════════════════════════════
if $HAS_GFF; then

  echo "Detecting GFF format and setting up symlinks"

  ## Reads a top-level "key: value" line from this gene set's metadata.yaml.
  ## Defined here (rather than beside its other callers further down) because
  ## the lift-prefix-required gate right below needs it immediately after
  ## GFF_SOURCE is classified, before any other work on this gene set.
  read_meta_key() {
    local value
    value=$(sed -n "s/^$1:[[:space:]]*//p" "$GENESET_DIR/metadata.yaml" 2>/dev/null | head -1)
    value=${value%$'\r'}
    value=${value%\"}; value=${value#\"}
    value=${value%\'}; value=${value#\'}
    printf '%s' "$value"
  }

  GFF_SOURCE=$(perl -ne '
    next if /^#/;
    my @f = split /\t/;
    next unless @f >= 9 && $f[2] eq "gene";
    if    ($f[8] =~ /\bID=gene:/)                                 { print "ensembl"; exit }
    elsif ($f[8] =~ /\bID=gene-/ || $f[8] =~ /\bDbxref=GeneID:/) { print "refseq";  exit }
    print "other"; exit
  ' "$GENESET_DIR/genes.gff")
  GFF_SOURCE=${GFF_SOURCE:-other}

  ## LiftOn/Liftoff output carries genuine RefSeq accessions (the liftover
  ## source's), which is exactly what the refseq match above keys on -- but the
  ## file SHAPE is the liftover tool's, not RefSeq's, and its CDS lines never
  ## carry the ID= that RefSeq always gives them (LiftOn omits it precisely
  ## when the liftover produced no usable protein). Neither
  ## rename_RefSeq_cds_fasta.pl nor emit_refseq can do anything with that, so a
  ## file matching either signal below is rerouted to "lift", handled
  ## identically to "other" from here on -- the generic emitter needs no CDS
  ## ID=/protein_id= at all (already proven for this exact shape: Nematostella
  ## NV2's CDS lines also carry no ID=, and it loads fine because it was never
  ## misclassified as refseq in the first place). See
  ## notes/LIFTOVER_GENESET_CRITERIA.md. Kept in sync with the same override in
  ## parse_GFF3_to_MOOP_TSV.pl::detect_format.
  if [ "$GFF_SOURCE" = "refseq" ]; then
    LIFT_TOOL=$(awk -F'\t' '!/^#/ && NF>=9 {print $2; exit}' "$GENESET_DIR/genes.gff")
    CDS_HAS_ID=$(awk -F'\t' '$3=="CDS" && index($9,"ID=")>0 {print 1; exit}' "$GENESET_DIR/genes.gff")
    if echo "$LIFT_TOOL" | grep -qiE '^lift(on|off)$' || [ -z "$CDS_HAS_ID" ]; then
      GFF_SOURCE=lift
    fi
  fi
  echo "GFF format: $GFF_SOURCE"

  ## A liftover gene set MUST have moop-lift-prefix before anything else here
  ## runs. Without it, every downstream step (naming, features.tsv, the load
  ## itself) would still "succeed" using borrowed XM_/XP_ accessions as this
  ## organism's own feature ids -- exactly the kind of looks-fine-but-empty-or-
  ## wrong state notes/LIFTOVER_GENESET_CRITERIA.md was written about. Stopping
  ## here, before any work happens, is cheaper than stopping after and it can
  ## never let a bad organism.sqlite reach the copy-to-moop step.
  if [ "$GFF_SOURCE" = "lift" ] && [ -z "$(read_meta_key moop-lift-prefix)" ]; then
    echo "ERROR: $THIS_ORG/$ASSEMBLY/$GENE_SET -- this looks like liftover output" >&2
    echo "       (LiftOn/Liftoff), not a native RefSeq/Ensembl download:" >&2
    [ -n "$LIFT_TOOL" ]    && echo "         genes.gff column 2 (source) = '$LIFT_TOOL'" >&2
    [ -z "$CDS_HAS_ID" ]   && echo "         no CDS line in genes.gff carries an ID= attribute" >&2
    echo "       Its feature ids are borrowed accessions from whatever genome the" >&2
    echo "       annotation was lifted from, and must be given a prefix before" >&2
    echo "       loading so they are never mistaken for this organism's own --" >&2
    echo "       see notes/LIFTOVER_GENESET_CRITERIA.md." >&2
    echo "" >&2
    echo "       Add a line to:" >&2
    echo "         $GENESET_DIR/metadata.yaml" >&2
    echo "       e.g.:" >&2
    echo "         moop-lift-prefix: <ShortCode>" >&2
    echo "       (a trailing underscore is added automatically if you omit one)" >&2
    echo "       then re-run this gene set." >&2
    exit 1
  fi

  case "$GFF_SOURCE" in
    refseq)  write_geneset_json "RefSeq"   ;;
    ensembl) write_geneset_json "Ensembl"  ;;
    lift)    write_geneset_json "Liftover (LiftOn/Liftoff)" ;;
    *)       write_geneset_json "$(infer_source "$GENE_SET")" ;;
  esac

  case "$GFF_SOURCE" in
    refseq)  ensure_organism_json "gene" "mRNA,transcript,protein" ;;
    *)       ensure_organism_json "gene" "mRNA,transcript" ;;
  esac

  for f in genes.gff protein.aa.fa cds.nt.fa transcript.nt.fa; do
    [ -L "$f" ] && rm -f "$f"
  done

  RENAME=true
  if [[ "$GFF_SOURCE" == "ensembl" || "$GFF_SOURCE" == "refseq" ]]; then
    RENAME=false
    ln -sf "$GENESET_DIR/genes.gff"         genes.gff
    ln -sf "$GENESET_DIR/protein.aa.fa"     protein.aa.fa
    ln -sf "$GENESET_DIR/transcript.nt.fa"  transcript.nt.fa
    if [[ "$GFF_SOURCE" == "refseq" || "$GFF_SOURCE" == "ensembl" ]]; then
      cp "$GENESET_DIR/cds.nt.fa" cds.nt.fa
    else
      ln -sf "$GENESET_DIR/cds.nt.fa" cds.nt.fa
    fi
  fi

  ## genome.fa lives at the assembly level
  mkdir -p "$ASSEMBLY_DATA"
  [ -e "$ASSEMBLY_DATA/genome.fa" ] || ln -sf "$GENOME_DIR/genome.fa" "$ASSEMBLY_DATA/genome.fa"

  check_missing_files_gff

  # A reload (MOOP_RELOAD=1, set by the caller) starts this true, so every
  # 'has_data X || REBUILD=true' gate below is bypassed and X is regenerated from
  # source. That is the whole point of a reload: picking up a fixed parser means
  # rebuilding features.tsv, not reusing the one the OLD parser wrote.
  REBUILD=false
  [ "${MOOP_RELOAD:-0}" = "1" ] && REBUILD=true

  has_data isoforms.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building isoforms.tsv"
    perl "$REPO/analysis_parsers/make_isoforms_from_gff.pl" "$GENESET_DIR/genes.gff" > isoforms.tsv.tmp \
      && mv isoforms.tsv.tmp isoforms.tsv \
      || { rm -f isoforms.tsv.tmp; echo "ERROR: failed to build isoforms.tsv"; exit 1; }
  fi

  naming_outputs_current || REBUILD=true
  if $REBUILD; then
    echo "Building geneNames.tsv"
    if $RENAME; then
      run_naming_v2

      echo "Updating GFF and FASTAs"
      perl "$REPO/analysis_parsers/updateGFF.pl"   "$GENESET_DIR/genes.gff"         geneNames.tsv > genes.gff.tmp \
        && perl "$REPO/analysis_parsers/addClosestToGFF.pl" genes.gff.tmp $(closest_files) > genes.gff.closest.tmp \
        && mv genes.gff.closest.tmp genes.gff && rm -f genes.gff.tmp \
        || { rm -f genes.gff.tmp genes.gff.closest.tmp; echo "ERROR: failed to build genes.gff"; exit 1; }
      perl "$REPO/analysis_parsers/updateFASTA.pl" "$GENESET_DIR/protein.aa.fa"     geneNames.tsv > protein.aa.fa.tmp \
        && mv protein.aa.fa.tmp protein.aa.fa \
        || { rm -f protein.aa.fa.tmp;    echo "ERROR: failed to build protein.aa.fa";   exit 1; }
      perl "$REPO/analysis_parsers/updateFASTA.pl" "$GENESET_DIR/cds.nt.fa"         geneNames.tsv > cds.nt.fa.tmp \
        && mv cds.nt.fa.tmp cds.nt.fa \
        || { rm -f cds.nt.fa.tmp;        echo "ERROR: failed to build cds.nt.fa";       exit 1; }
      perl "$REPO/analysis_parsers/updateFASTA.pl" "$GENESET_DIR/transcript.nt.fa"  geneNames.tsv > transcript.nt.fa.tmp \
        && mv transcript.nt.fa.tmp transcript.nt.fa \
        || { rm -f transcript.nt.fa.tmp; echo "ERROR: failed to build transcript.nt.fa"; exit 1; }
    else
      # RefSeq/Ensembl ship their own gene name + description, and we keep them
      # exactly as provided -- unless the native name is uninformative ("uncharacterized
      # protein", a bare LOC/CG/Gm symbol, ...; GeneNamingV2.pm decides), in which case
      # the naming v2 name replaces it for that gene. Every gene still gets its closest
      # genes (closest_<tag>.tsv/.moop.tsv, and the GFF below).
      #
      # geneNames.native.tsv (the source's own names, from get_names_from_gff.pl) is left
      # on disk so a replaced name can be compared with what the source called it.
      # geneNames.tsv covers every id in it: updateFASTA.pl/updateGFF.pl treat an id
      # ABSENT from a names file as "no name any more".
      perl "$REPO/analysis_parsers/get_names_from_gff.pl" "$GENESET_DIR/genes.gff" > geneNames.native.tsv.tmp \
        && mv geneNames.native.tsv.tmp geneNames.native.tsv \
        || { rm -f geneNames.native.tsv.tmp; echo "ERROR: failed to build geneNames.native.tsv"; exit 1; }
      run_naming_v2 --native geneNames.native.tsv
    fi
  fi

  ## Native RefSeq/Ensembl GFFs keep their names, but get the closest genes on every
  ## gene and mRNA. genes.gff is a symlink to the source at this point (re-made on every
  ## run above), so write a real copy -- never edit through the link into the datastore.
  if ! $RENAME && [ -s closest_human.tsv ]; then
    rm -f genes.gff
    perl "$REPO/analysis_parsers/addClosestToGFF.pl" "$GENESET_DIR/genes.gff" $(closest_files) > genes.gff.tmp \
      && mv genes.gff.tmp genes.gff \
      || { rm -f genes.gff.tmp; ln -sf "$GENESET_DIR/genes.gff" genes.gff; echo "ERROR: failed to add closest genes to genes.gff"; exit 1; }
  fi

  ## MOOP's own ID normalization, opt-in per gene set via metadata.yaml:
  ##
  ##     moop-strip-id-prefix: Bradypodion_ventrale_
  ##     moop-add-id-prefix:   BraVen_
  ##
  ## The add key is optional -- omit it to drop the prefix entirely. Both are
  ## literals; the 3-of-genus + 3-of-species convention is for the curator filling
  ## in the file, not something computed here (see strip_id_prefix.pl for why).
  ##
  ## Runs BEFORE every other rename and before anything is derived from these
  ## files, so genes.gtf, features.tsv, feature_coords.tsv, geneNames.tsv and
  ## isoforms.tsv all inherit the shortened IDs without knowing this happened.
  ##
  ## Absent key = no invocation = IDs untouched. That is what makes a run over
  ## everything safe for the ~90 gene sets that do not opt in.
  ## (read_meta_key is defined earlier in this block, above the GFF_SOURCE
  ## classification -- the lift-prefix-required gate needs it before this point.)
  STRIP_PREFIX=$(read_meta_key moop-strip-id-prefix)
  ADD_PREFIX=$(read_meta_key moop-add-id-prefix)
  if [ -n "$STRIP_PREFIX" ]; then
    perl "$SCRIPTS/strip_id_prefix.pl" --strip "$STRIP_PREFIX" --add "$ADD_PREFIX" \
      genes.gff protein.aa.fa cds.nt.fa transcript.nt.fa \
      || { echo "ERROR: strip_id_prefix.pl failed for $THIS_ORG [$ASSEMBLY/$GENE_SET]"; exit 1; }
  fi

  ## moop-lift-prefix: a liftover gene set's (GFF_SOURCE=lift) feature IDs are
  ## borrowed accessions from whatever genome the annotation was lifted from --
  ## see notes/LIFTOVER_GENESET_CRITERIA.md. Prepending a short organism code
  ## means a borrowed XM_/XP_ accession is never mistaken for this organism's
  ## own. Same script as above, empty --strip means "prepend, don't replace"
  ## (see strip_id_prefix.pl's own header) -- same four id attributes, same
  ## distinct-id-count and 50-char guards, same position (before every other
  ## rename) for the same reason STRIP_PREFIX runs here: everything derived
  ## further down inherits the prefixed ids without knowing this happened.
  LIFT_PREFIX=$(read_meta_key moop-lift-prefix)
  if [ -n "$LIFT_PREFIX" ]; then
    ## Curator writes the short code alone (e.g. "Ppar1"); a separator is added
    ## automatically unless they already included one, so "parpar1_rna-XM_..."
    ## reads cleanly either way without requiring the curator to remember it.
    case "$LIFT_PREFIX" in
      *_) ;;
      *)  LIFT_PREFIX="${LIFT_PREFIX}_" ;;
    esac
    perl "$SCRIPTS/strip_id_prefix.pl" --add "$LIFT_PREFIX" \
      genes.gff protein.aa.fa cds.nt.fa transcript.nt.fa \
      || { echo "ERROR: strip_id_prefix.pl (moop-lift-prefix) failed for $THIS_ORG [$ASSEMBLY/$GENE_SET]"; exit 1; }
  fi

  if [[ "$GFF_SOURCE" == "refseq" ]]; then
    perl "$SCRIPTS/rename_RefSeq_cds_fasta.pl" genes.gff cds.nt.fa
  elif [[ "$GFF_SOURCE" == "ensembl" ]]; then
    perl "$SCRIPTS/rename_Ensembl_cds_fasta.pl" protein.aa.fa cds.nt.fa
  fi

  if [ -L protein.aa.fa ]; then
    cp --dereference protein.aa.fa protein.aa.fa.tmp && mv protein.aa.fa.tmp protein.aa.fa
  fi
  perl "$REPO/analysis_parsers/clean_protein_fasta.pl" protein.aa.fa > protein.aa.fa.tmp \
    && mv protein.aa.fa.tmp protein.aa.fa \
    || { rm -f protein.aa.fa.tmp; echo "ERROR: failed to clean protein.aa.fa"; exit 1; }

  if [[ "$GFF_SOURCE" == "other" || "$GFF_SOURCE" == "lift" ]]; then
    [ -s cds.nt.fa ]     && perl "$SCRIPTS/rename_generic_fasta.pl" cds.nt.fa     :cds genes.gff
    [ -s protein.aa.fa ] && perl "$SCRIPTS/rename_generic_fasta.pl" protein.aa.fa :pep genes.gff cds.nt.fa
  fi

  if [ -s "genes.gff" ]; then
    echo "GFF available. Proceeding to feature table."
  else
    echo "ERROR: genes.gff is empty or missing!"
    exit 1
  fi

  # ── GTF (built from the finalized, renamed genes.gff) ────────────────────
  module load gffread
  if [ ! -s genes.gtf ] || [ genes.gff -nt genes.gtf ]; then
    echo "Building genes.gtf"
    gffread -T -F genes.gff -o genes.gtf.tmp \
      && mv genes.gtf.tmp genes.gtf \
      || { rm -f genes.gtf.tmp; echo "ERROR: failed to build genes.gtf"; exit 1; }
  fi

  has_data features.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building features.tsv"
    # geneNames.tsv as a 5th arg only matters on the refseq/ensembl branch --
    # that's the only case parse_GFF3_to_MOOP_TSV.pl's emit_ensembl/emit_refseq
    # consult it (see its own comments). The "other"-format emitter reads
    # Name=/Note= off genes.gff itself (already rewritten by updateGFF.pl
    # above) and ignores this argument, so passing it here unconditionally is
    # a no-op for that path rather than a second, conflicting naming source.
    perl "$REPO/analysis_parsers/parse_GFF3_to_MOOP_TSV.pl" genes.gff \
      "$GENESET_DIR/metadata.yaml" cds.nt.fa protein.aa.fa geneNames.tsv > features.tsv.tmp \
      && mv features.tsv.tmp features.tsv \
      || { rm -f features.tsv.tmp; echo "ERROR: failed to build features.tsv"; exit 1; }
  fi

  ## Load if anything was rebuilt above, OR if this gene set simply is not in the
  ## database -- see geneset_loaded(). Without the second test a failed load is never
  ## retried, because its features.tsv survives to satisfy the gate above.
  if ! geneset_loaded; then
    $REBUILD || echo "NOTE: features.tsv exists but '$GENE_SET' has no rows in organism.sqlite — loading it"
    REBUILD=true
  fi

  if $REBUILD; then
    refuse_load_on_top
    sh "$SCRIPTS/setup_new_moopdb_and_load_data.sh" "$THIS_ORG" "$GENE_SET" "$DATA/$THIS_ORG" "$GENESET_DATA" || {
      echo "ERROR: loading $THIS_ORG/$ASSEMBLY/$GENE_SET failed — not continuing." >&2
      exit 1
    }

    CHILDREN="mRNA,transcript"
    if [[ "$GFF_SOURCE" == "refseq" ]]; then
      if grep -qP '\tmRNA\t' genes.gff; then
        CHILDREN="mRNA,transcript,protein"
      else
        CHILDREN="protein"
      fi
    fi
    perl "$REPO/analysis_parsers/make_organisms_config.pl" \
      "$GENESET_DIR/metadata.yaml" gene "$CHILDREN"
    mv organism.json "$DATA/$THIS_ORG/organism.json"
  fi

  module load samtools
  module load blast-plus
  needs_rebuild() { [ ! -f "$2" ] || [ "$1" -nt "$2" ]; }
  echo "Building indices"

  if [ -e "$ASSEMBLY_DATA/genome.fa" ]; then
    needs_rebuild "$ASSEMBLY_DATA/genome.fa" "$ASSEMBLY_DATA/genome.fa.fai" \
      && samtools faidx "$ASSEMBLY_DATA/genome.fa"
    needs_rebuild "$ASSEMBLY_DATA/genome.fa" "$ASSEMBLY_DATA/genome.fa.ndb" \
      && makeblastdb -in "$ASSEMBLY_DATA/genome.fa" -dbtype nucl -parse_seqids
  fi
  if [ -e protein.aa.fa ]; then
    needs_rebuild protein.aa.fa protein.aa.fa.pdb \
      && makeblastdb -in protein.aa.fa -dbtype prot -parse_seqids
  fi
  for FA in transcript.nt.fa cds.nt.fa; do
    [ -e "$FA" ] || continue
    needs_rebuild "$FA" "${FA}.ndb" && makeblastdb -in "$FA" -dbtype nucl -parse_seqids
  done

  # samtools .fai alongside the BLAST databases.
  #
  # MOOP's gene page extracts a handful of sequences per request. It did that with
  # blastdbcmd, which costs ~110ms per call and is almost entirely PROCESS STARTUP --
  # `blastdbcmd -version` alone is 100ms. Three calls per page (protein, transcript, cds)
  # made sequence extraction the ENTIRE server-side cost of a gene page: 0.47s -> 0.09s on
  # a 3-isoform gene and 0.77s -> 0.06s on a 17-isoform one when the calls were skipped.
  #
  # With a .fai the same lookup is pure PHP fseek/fread -- no process spawn at all,
  # measured 4ms against blastdbcmd's 110ms, and barely affected by where the id sits in
  # the file. api/get_sequence.php already uses exactly this technique for genome.fa.
  #
  # The BLAST databases stay: they are what BLAST itself searches. This is an ADDITIONAL
  # index for point lookups, ~1.3MB per FASTA and ~0.1s to build.
  for FA in protein.aa.fa transcript.nt.fa cds.nt.fa; do
    [ -e "$FA" ] || continue
    needs_rebuild "$FA" "${FA}.fai" && samtools faidx "$FA"
  done

# ═════════════════════════════════════════════════════════════════════════════
# T2G PATH — transcriptome-only geneset (no genome, no GFF)
# ═════════════════════════════════════════════════════════════════════════════
else

  write_geneset_json "$(infer_source "$GENE_SET")"
  ensure_organism_json "gene" "mRNA,transcript"

  check_missing_files_t2g

  # A reload (MOOP_RELOAD=1, set by the caller) starts this true, so every
  # 'has_data X || REBUILD=true' gate below is bypassed and X is regenerated from
  # source. That is the whole point of a reload: picking up a fixed parser means
  # rebuilding features.tsv, not reusing the one the OLD parser wrote.
  REBUILD=false
  [ "${MOOP_RELOAD:-0}" = "1" ] && REBUILD=true

  has_data isoforms.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building isoforms.tsv from protein2gene.txt"
    perl "$REPO/analysis_parsers/make_isoforms_from_transcript2gene.pl" \
      "$GENESET_DIR/protein2gene.txt" > isoforms.tsv.tmp \
      && mv isoforms.tsv.tmp isoforms.tsv \
      || { rm -f isoforms.tsv.tmp; echo "ERROR: failed to build isoforms.tsv"; exit 1; }
  fi

  naming_outputs_current || REBUILD=true
  if $REBUILD; then
    echo "Building geneNames.tsv"
    run_naming_v2

    echo "Updating FASTAs with gene names"
    perl "$REPO/analysis_parsers/updateFASTA.pl" "$GENESET_DIR/protein.aa.fa" \
      geneNames.tsv > protein.aa.fa.tmp \
      && mv protein.aa.fa.tmp protein.aa.fa \
      || { rm -f protein.aa.fa.tmp; echo "ERROR: failed to build protein.aa.fa"; exit 1; }
    if [ -s "$GENESET_DIR/transcript.nt.fa" ]; then
      perl "$REPO/analysis_parsers/updateFASTA.pl" "$GENESET_DIR/transcript.nt.fa" \
        geneNames.tsv > transcript.nt.fa.tmp \
        && mv transcript.nt.fa.tmp transcript.nt.fa \
        || { rm -f transcript.nt.fa.tmp; echo "ERROR: failed to build transcript.nt.fa"; exit 1; }
    fi
    if [ -s "$GENESET_DIR/cds.nt.fa" ]; then
      perl "$REPO/analysis_parsers/updateFASTA.pl" "$GENESET_DIR/cds.nt.fa" \
        geneNames.tsv > cds.nt.fa.tmp \
        && mv cds.nt.fa.tmp cds.nt.fa \
        || { rm -f cds.nt.fa.tmp; echo "ERROR: failed to build cds.nt.fa"; exit 1; }
    fi
  fi

  perl "$REPO/analysis_parsers/clean_protein_fasta.pl" protein.aa.fa > protein.aa.fa.tmp \
    && mv protein.aa.fa.tmp protein.aa.fa \
    || { rm -f protein.aa.fa.tmp; echo "ERROR: failed to clean protein.aa.fa"; exit 1; }

  # ── Give the three T2G FASTAs distinct ids ──────────────────────────────────
  # On this path transcript.nt.fa, cds.nt.fa and protein.aa.fa all key on the SAME
  # identifier -- the type is decided by which file you read. MOOP needs
  # feature_uniquename to be both unique and the FASTA lookup key, which one
  # shared id cannot satisfy: the rows collapse into a single self-parented
  # feature, and the protein sequences become unreachable.
  #
  # Suffix CDS and protein to match what parse_transcript2gene_to_MOOP_TSV.pl
  # emits. transcript.nt.fa deliberately keeps its bare id, as the mRNA row does
  # on every other path. Idempotent, so re-running over renamed copies is a no-op.
  if [ -s cds.nt.fa ]; then
    perl "$SCRIPTS/rename_t2g_fasta.pl" cds.nt.fa :cds \
      || { echo "ERROR: failed to suffix cds.nt.fa"; exit 1; }
  fi
  if [ -s protein.aa.fa ]; then
    perl "$SCRIPTS/rename_t2g_fasta.pl" protein.aa.fa :pep \
      || { echo "ERROR: failed to suffix protein.aa.fa"; exit 1; }
  fi

  has_data features.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building features.tsv from transcript2gene.txt"
    perl "$REPO/analysis_parsers/parse_transcript2gene_to_MOOP_TSV.pl" \
      "$GENESET_DIR/transcript2gene.txt" geneNames.tsv "$GENESET_DIR/metadata.yaml" \
      "$GENESET_DIR/protein2gene.txt" "$GENESET_DIR/cds2gene.txt" > features.tsv.tmp \
      && mv features.tsv.tmp features.tsv \
      || { rm -f features.tsv.tmp; echo "ERROR: failed to build features.tsv"; exit 1; }
  fi

  ## Load if anything was rebuilt above, OR if this gene set simply is not in the
  ## database -- see geneset_loaded(). Without the second test a failed load is never
  ## retried, because its features.tsv survives to satisfy the gate above.
  if ! geneset_loaded; then
    $REBUILD || echo "NOTE: features.tsv exists but '$GENE_SET' has no rows in organism.sqlite — loading it"
    REBUILD=true
  fi

  if $REBUILD; then
    refuse_load_on_top
    sh "$SCRIPTS/setup_new_moopdb_and_load_data.sh" "$THIS_ORG" "$GENE_SET" "$DATA/$THIS_ORG" "$GENESET_DATA" || {
      echo "ERROR: loading $THIS_ORG/$ASSEMBLY/$GENE_SET failed — not continuing." >&2
      exit 1
    }
    perl "$REPO/analysis_parsers/make_organisms_config.pl" \
      "$GENESET_DIR/metadata.yaml" gene "mRNA,transcript"
    mv organism.json "$DATA/$THIS_ORG/organism.json"
  fi

  module load blast-plus
  needs_rebuild() { [ ! -f "$2" ] || [ "$1" -nt "$2" ]; }
  echo "Building FASTA indices"

  if [ -e protein.aa.fa ]; then
    needs_rebuild protein.aa.fa protein.aa.fa.pdb \
      && makeblastdb -in protein.aa.fa -dbtype prot -parse_seqids
  fi
  for FA in transcript.nt.fa cds.nt.fa; do
    [ -e "$FA" ] || continue
    needs_rebuild "$FA" "${FA}.ndb" && makeblastdb -in "$FA" -dbtype nucl -parse_seqids
  done

  # samtools .fai alongside the BLAST databases.
  #
  # MOOP's gene page extracts a handful of sequences per request. It did that with
  # blastdbcmd, which costs ~110ms per call and is almost entirely PROCESS STARTUP --
  # `blastdbcmd -version` alone is 100ms. Three calls per page (protein, transcript, cds)
  # made sequence extraction the ENTIRE server-side cost of a gene page: 0.47s -> 0.09s on
  # a 3-isoform gene and 0.77s -> 0.06s on a 17-isoform one when the calls were skipped.
  #
  # With a .fai the same lookup is pure PHP fseek/fread -- no process spawn at all,
  # measured 4ms against blastdbcmd's 110ms, and barely affected by where the id sits in
  # the file. api/get_sequence.php already uses exactly this technique for genome.fa.
  #
  # The BLAST databases stay: they are what BLAST itself searches. This is an ADDITIONAL
  # index for point lookups, ~1.3MB per FASTA and ~0.1s to build.
  for FA in protein.aa.fa transcript.nt.fa cds.nt.fa; do
    [ -e "$FA" ] || continue
    needs_rebuild "$FA" "${FA}.fai" && samtools faidx "$FA"
  done

fi
