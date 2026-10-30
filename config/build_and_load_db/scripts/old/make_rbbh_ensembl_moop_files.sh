## once rbbh  has been completed
##
## NOTE: process_one_geneset.sh no longer calls this — it has an inline
## make_rbbh_moop() that is the canonical copy (matching make_diamond_moop etc).
## Kept for manual / standalone runs; keep the two in sync.
##
## see /n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/rbbh/run_rbbh.sh
#RBBH=/n/sci/SCI-004223-SBGENOMES/shared_scripts/bin/reciprocal_alignment.py
#FA1=CCA3.fa
#FA2=${ORG}.fa
#ISO1=CCA3.isoforms
#ISO2=${ORG}.isoforms
#$RBBH $FA1 $FA2 --protein2gene1 $ISO1 --protein2gene2 $ISO2 -o ${FA1}.${FA2}.results.tsv

## should rework the running of rbbh scripts to use this:
## we also always make a db_version.txt for every db we use, and we keep it in the results directory. This file is read here to add the db_version info to the metadata section of the moop file that is used to load into the moop sqlite db
## if the db is from a specific organism we can tell based on the name of the results directory ENS_danio_rerio. I use the organisms name in the metadata section of this output file

## DESCRIPTION FILE (desc.txt): hit_id<TAB>description<TAB>gene_symbol, consumed by
## parse_RBBH_to_MOOP_TSV.pl to fill the Accession_Description column.
##
## The old RBBH_v2 runs symlinked a prebuilt desc.txt into each results directory.
## The rbh_eross runs do NOT -- the results directory holds only rbh_eross_results.tsv,
## rbh_eross_job_cmd.txt and rbh_eross_version.txt. Without a desc.txt every homolog
## loaded with a blank description.
##
## Where we look for the target's desc.txt, in order:
##   step 1  $REF_DB/<ORG>/current/desc.txt   -- shared, built once next to the FASTA
##   step 2  ./<ORG>.desc.txt                 -- this gene set's copy from an earlier run
##   step 3  generate ./<ORG>.desc.txt        -- from $REF_DB/<ORG>/current/*.pep.all.fa.gz
## (a desc.txt shipped in the results dir itself, as the old RBBH_v2 tree did, is
## honoured ahead of all three.)

THIS_ORG=$1
RESULTS_DIR=$2
ORG=$3

REPO=$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")
SCRIPTS=$REPO/analysis_parsers

## REF_DB (and a sane default) come from the single path definition.
source "$REPO/scripts/paths.sh"

RESULTS=$RESULTS_DIR/$ORG

## Version: follow the reference DB's 'current' symlink (e.g. release-113); the
## literal below is only the fallback if that lookup comes back with nothing usable.
VERSION=release-113
_ref_current=$(basename "$(readlink -f "$REF_DB/$ORG/current" 2>/dev/null)" 2>/dev/null)
[[ "$_ref_current" == release-* ]] && VERSION=$_ref_current

PRETTY_ORG="${ORG#ENS_}"
PRETTY_ORG="${PRETTY_ORG//_/ }"
PRETTY_ORG="${PRETTY_ORG^}"

# ── desc.txt resolution ──────────────────────────────────────────────────────
REF_DIR="$REF_DB/$ORG/current"
CWD_DESC="$PWD/${ORG}.desc.txt"

## Build $CWD_DESC from the reference Ensembl peptide FASTA.
generate_desc() {
  local fasta
  fasta=$(ls "$REF_DIR"/*.pep.all.fa.gz 2>/dev/null | head -1)
  [ -z "$fasta" ] && fasta=$(ls "$REF_DIR"/*.pep.all.fa 2>/dev/null | head -1)
  if [ -z "$fasta" ]; then
    echo "WARNING: no Ensembl peptide FASTA under $REF_DIR — RBBH descriptions for $ORG will be blank" >&2
    return 1
  fi
  echo "Generating RBBH description file for $ORG from $fasta"
  if [[ "$fasta" == *.gz ]]; then
    zcat "$fasta" | perl "$SCRIPTS/rbbh/getDesc_ENS_FA.pl" /dev/stdin > "$CWD_DESC.tmp"
  else
    perl "$SCRIPTS/rbbh/getDesc_ENS_FA.pl" "$fasta" > "$CWD_DESC.tmp"
  fi
  if [ -s "$CWD_DESC.tmp" ]; then
    mv "$CWD_DESC.tmp" "$CWD_DESC"
  else
    rm -f "$CWD_DESC.tmp"
    echo "WARNING: getDesc_ENS_FA.pl produced nothing for $ORG" >&2
    return 1
  fi
}

if   [ -s "$RESULTS/desc.txt" ]; then DESC_FILE="$RESULTS/desc.txt"   # shipped with results
elif [ -s "$REF_DIR/desc.txt" ]; then DESC_FILE="$REF_DIR/desc.txt"   # step 1: shared db dir
elif [ -s "$CWD_DESC" ];         then DESC_FILE="$CWD_DESC"           # step 2: earlier run in cwd
elif generate_desc;              then DESC_FILE="$CWD_DESC"           # step 3: generate in cwd
else                                  DESC_FILE=""                    # give up -> blank descriptions
fi

echo "$RESULTS"
echo "desc file: ${DESC_FILE:-<none>}"
perl "$SCRIPTS/parse_RBBH_to_MOOP_TSV.pl" "$RESULTS" "Ensembl $PRETTY_ORG" "$VERSION" \
  https://www.ensembl.org/ "https://www.ensembl.org/Multi/Search/Results?q=" "$DESC_FILE"
