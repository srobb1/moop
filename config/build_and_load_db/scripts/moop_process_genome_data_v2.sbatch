#!/usr/bin/bash
#SBATCH --job-name=MOOP_LOAD
#SBATCH --cpus-per-task=4
#SBATCH --mem=16gb
#SBATCH --time=1-00:00:00
#SBATCH --output=slurm_logs/%x_%A_%a.out

## New GENOMES structure:
##   GENOMES/<organism>/<assembly>/<geneset>/
##     metadata.yaml  protein.aa.fa  cds.nt.fa  transcript.nt.fa
##     genes.gff                          (genome-backed genesets)
##     transcript2gene.txt / protein2gene.txt  (transcriptome-only genesets)
##   GENOMES/<organism>/<assembly>/genome.fa
##
## Annotations structure:
##   ANNOTATIONS/<organism>/<assembly>/<geneset>/<tool>/
##
## Usage:
##   Single job : sbatch moop_process_genome_data_v2.sbatch <organism> <assembly> <gene_set>
##   Array job  : sbatch --array=0-102%5 scripts/moop_process_genome_data_v2.sbatch
##                (reads organism/assembly/geneset from $GENESETS_FILE by task index;
##                 defaults to $REPO/all_genesets.tsv, but run_all_v2.sh passes its own
##                 auto-generated list of active genesets via --export=GENESETS_FILE=...)

REPO=$SLURM_SUBMIT_DIR

if [[ -n "$SLURM_ARRAY_TASK_ID" ]]; then
  GENESETS_FILE="${GENESETS_FILE:-$REPO/all_genesets.tsv}"
  LINE=$(sed -n "$((SLURM_ARRAY_TASK_ID + 1))p" "$GENESETS_FILE")
  THIS_ORG=$(echo "$LINE"  | cut -f1)
  ASSEMBLY=$(echo "$LINE"  | cut -f2)
  GENE_SET=$(echo "$LINE"  | cut -f3)
else
  THIS_ORG=$1
  ASSEMBLY=$2
  GENE_SET=$3
fi

[ -n "$THIS_ORG" ] && [ -n "$ASSEMBLY" ] && [ -n "$GENE_SET" ] \
  || { echo "Usage: $0 <organism> <assembly> <gene_set>  (or run as array job)"; exit 1; }

SCRIPTS=$REPO/scripts
DATA=$REPO/data

GENOMES=/n/sci/SCI-004223-SBGENOMES/genomes_v2
ANNOTATIONS=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21

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
  [ -f "$ANALYSIS_DIR/diamond_blast/UNIPROT_sprot/tophit.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/diamond_blast/UNIPROT_sprot/tophit.tsv" ]     || log_missing "diamond_blast/UNIPROT_sprot/tophit.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/eggnog_mapper/emapper.annotations" ]             || log_missing "eggnog_mapper/emapper.annotations"
  [ -f "$ANALYSIS_DIR/interproscan/iprscan_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/interproscan/iprscan_results.tsv" ]            || log_missing "interproscan/iprscan_results.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/protnlm/protnlm_pred_results.tsv" ]             || log_missing "protnlm/protnlm_pred_results.tsv"
}

check_missing_files_t2g() {
  [ -s "$GENESET_DIR/protein2gene.txt" ]                               || log_missing "protein2gene.txt"
  [ -s "$GENESET_DIR/transcript2gene.txt" ]                            || log_missing "transcript2gene.txt"
  [ -s "$GENESET_DIR/protein.aa.fa" ]                                  || log_missing "protein.aa.fa"
  [ -s "$GENESET_DIR/transcript.nt.fa" ]                               || log_missing "transcript.nt.fa"
  [ -s "$GENESET_DIR/metadata.yaml" ]                                  || log_missing "metadata.yaml"
  [ -f "$ANALYSIS_DIR/diamond_blast/UNIPROT_sprot/tophit.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/diamond_blast/UNIPROT_sprot/tophit.tsv" ]     || log_missing "diamond_blast/UNIPROT_sprot/tophit.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/eggnog_mapper/emapper.annotations" ]             || log_missing "eggnog_mapper/emapper.annotations"
  [ -f "$ANALYSIS_DIR/interproscan/iprscan_results.tsv.gz" ] || \
    [ -f "$ANALYSIS_DIR/interproscan/iprscan_results.tsv" ]            || log_missing "interproscan/iprscan_results.tsv(.gz)"
  [ -f "$ANALYSIS_DIR/protnlm/protnlm_pred_results.tsv" ]             || log_missing "protnlm/protnlm_pred_results.tsv"
}

mkdir -p "$GENESET_DATA"
write_genome_json
cd "$GENESET_DATA"
[ -e tophit.tsv ] && rm tophit.tsv

# ── Diamond / BLAST homologs ──────────────────────────────────────────────────
make_diamond_moop() {
  local DBLAST_DIR="$ANALYSIS_DIR/diamond_blast"
  local SPROT_DIR="$DBLAST_DIR/UNIPROT_sprot"
  local VERSION
  VERSION=$(head -1 "$SPROT_DIR/db_version.txt" 2>/dev/null)

  [ -e tophit.tsv ] && rm tophit.tsv
  if [ -e "$SPROT_DIR/tophit.tsv.gz" ]; then
    zcat "$SPROT_DIR/tophit.tsv.gz" > tophit.tsv
  else
    ln -s "$SPROT_DIR/tophit.tsv" tophit.tsv
  fi
  ls -l tophit.tsv
  perl "$REPO/analysis_parsers/parse_DIAMOND_to_MOOP_TSV.pl" tophit.tsv \
    'UniProtKB/Swiss-Prot' "$VERSION" https://www.uniprot.org https://www.uniprot.org/uniprotkb/

  shopt -s nullglob
  for RESULTS in "$DBLAST_DIR"/ENS_*/; do
    shopt -u nullglob
    RBBH_ORG=$(basename "$RESULTS")
    ORGSTRING="${RBBH_ORG#ENS_}"
    ORG=$(echo "$ORGSTRING" | perl -pe 's/^(\w)/\u$1/; s/_(\w)/ \L$1/g')
    VERSION=$(head -1 "$RESULTS/db_version.txt" 2>/dev/null)
    [ -e tophit.tsv ] && rm tophit.tsv
    if [ -e "$RESULTS/tophit.tsv.gz" ]; then
      zcat "$RESULTS/tophit.tsv.gz" > tophit.tsv
    elif [ -e "$RESULTS/tophit.tsv" ]; then
      ln -s "$RESULTS/tophit.tsv" tophit.tsv
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
  MAPPERVERSION=$(grep emapper "$EDIR/version.txt" 2>/dev/null \
                  | perl -pe 's/.*(emapper-\S+).*/$1/')
  DBVERSION=$(grep 'eggNOG DB version:' "$EDIR/version.txt" 2>/dev/null \
              | perl -pe 's/.*?eggNOG DB version: (\S+).*/$1/')
  VERSION="$MAPPERVERSION DB_$DBVERSION"
  echo "perl $REPO/analysis_parsers/parse_EggNOG_to_MOOP_TSV.pl $EDIR/emapper.annotations $VERSION"
  perl "$REPO/analysis_parsers/parse_EggNOG_to_MOOP_TSV.pl" "$EDIR/emapper.annotations" "$VERSION"
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
  VERSION=$(cat "$IDIR/version.txt" 2>/dev/null)
  rm -f iprscan.tsv
  if [ -e "$IDIR/iprscan_results.tsv.gz" ]; then
    zcat "$IDIR/iprscan_results.tsv.gz" > iprscan.tsv
  else
    ln -s "$IDIR/iprscan_results.tsv" iprscan.tsv
  fi
  perl "$REPO/analysis_parsers/parse_InterProScan_to_MOOP_TSV.pl" iprscan.tsv "$VERSION"
}
has_data PANTHER.iprscan.moop.tsv \
  || { echo "Building InterProScan moop files"; make_interproscan_moop; }

# ── ProtNLM ───────────────────────────────────────────────────────────────────
make_protnlm_moop() {
  perl "$REPO/analysis_parsers/parse_ProtNLM_to_MOOP_TSV.pl" \
    "$ANALYSIS_DIR/protnlm/protnlm_pred_results.tsv"
}
has_data protnlm.moop.tsv \
  || { echo "Building ProtNLM moop files"; make_protnlm_moop; }

# ── RBBH ─────────────────────────────────────────────────────────────────────
RBBH_BASE="/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/RBBH_v2/RESULTS/$THIS_ORG/$ASSEMBLY/$GENE_SET"
for RBBH_RESULTS in "$RBBH_BASE"/*/*results.tsv; do
  [[ -f "$RBBH_RESULTS" ]] || continue
  PARENT_DIR="${RBBH_RESULTS%/*}"
  RBBH_ORG="${PARENT_DIR##*/}"
  RBBH_PRETTY="${RBBH_ORG#ENS_}"; RBBH_PRETTY="${RBBH_PRETTY//_/ }"; RBBH_PRETTY="${RBBH_PRETTY^}"
  has_data "Ensembl_${RBBH_PRETTY// /_}.RBBH.moop.tsv" \
    || { echo "Processing RBBH for $THIS_ORG and $RBBH_ORG"
         sh "$SCRIPTS/make_rbbh_ensembl_moop_files.sh" "$THIS_ORG" "$RBBH_BASE" "$RBBH_ORG"; }
done

# ── OMA (standalone, optional) ────────────────────────────────────────────────
## Not part of the big per-org ANALYSIS_DIR pipeline — OMA is run by hand and
## symlinked in under OMA_BASE/<organism>/<assembly>/<geneset>/{Output,mapGO},
## pointing back at wherever the actual OMA run lives. Silently skipped for any
## organism/assembly/geneset that has no such symlinks.
OMA_BASE="/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/OMA_v2"
OMA_DIR="$OMA_BASE/$THIS_ORG/$ASSEMBLY/$GENE_SET"
if [ -d "$OMA_DIR/Output" ]; then
  FIRST_PROT_ID=$(grep -m1 ">" "$GENESET_DIR/protein.aa.fa" 2>/dev/null | sed 's/^>//' | awk '{print $1}')
  OMA_CODE=$(grep -F -m1 "$FIRST_PROT_ID" "$OMA_DIR/Output/Map-SeqNum-ID.txt" 2>/dev/null | cut -f1)

  if [ -z "$OMA_CODE" ]; then
    echo "WARNING: OMA dir found ($OMA_DIR) but couldn't determine this organism's OMA species code from Map-SeqNum-ID.txt"
  else
    OMA_VERSION=$(basename "$(dirname "$(realpath "$OMA_DIR/Output")")")
    echo "OMA species code: $OMA_CODE (version: $OMA_VERSION)"

    ## parse_OMA_orthologs_to_MOOP_TSV.pl writes one file per *partner* org found in the
    ## groups file (named "$PARTNER.$db.oma_orthologs.moop.tsv") — never one
    ## named after $OMA_CODE itself — so the skip-check has to look for any
    ## such file, not "${OMA_CODE}.*.oma_orthologs.moop.tsv" (which can never exist).
    shopt -s nullglob
    existing_orthologs=(*.oma_orthologs.moop.tsv)
    shopt -u nullglob
    if [ ${#existing_orthologs[@]} -eq 0 ]; then
      echo "Building OMA orthologs for $OMA_CODE"
      perl "$REPO/analysis_parsers/parse_OMA_orthologs_to_MOOP_TSV.pl" \
        "$OMA_DIR/Output/OrthologousGroups.txt" "$OMA_CODE" "$OMA_VERSION"
    fi

    shopt -s nullglob
    ## Filename just needs to contain our species code to be relevant (filters out
    ## the many pairwise files between two *other* reference species, e.g.
    ## ANOCA-CHICK.txt, which have nothing to do with us). Orientation within the
    ## relevant files is then derived from content, not the filename, below.
    for PAIR_FILE in "$OMA_DIR/Output/PairwiseOrthologs/"*"$OMA_CODE"*.txt; do
      ## Every organism other than ours is written as "CODE12345 | xref1; xref2 | ..."
      ## (pipe-delimited), while our own ids are bare gene IDs with no pipes.
      ## Whichever of ids1/ids2 has no "|" is us; the other one's leading letters
      ## are the OTHERORG code.
      FIRST_DATA_LINE=$(grep -v '^#' "$PAIR_FILE" | head -1)
      [ -z "$FIRST_DATA_LINE" ] && continue
      IDS1=$(echo "$FIRST_DATA_LINE" | cut -f3)
      IDS2=$(echo "$FIRST_DATA_LINE" | cut -f4)
      if [[ "$IDS1" != *"|"* ]]; then
        THISORG_FIRST=1
        OTHERORG=$(echo "$IDS2" | grep -oE '^[A-Za-z]+')
      elif [[ "$IDS2" != *"|"* ]]; then
        THISORG_FIRST=0
        OTHERORG=$(echo "$IDS1" | grep -oE '^[A-Za-z]+')
      else
        echo "WARNING: couldn't determine orientation for $PAIR_FILE, skipping"
        continue
      fi
      has_data "${OTHERORG}."*".oma_pairs.moop.tsv" \
        || { echo "Building OMA pairs for $OMA_CODE vs $OTHERORG"
             perl "$REPO/analysis_parsers/parse_OMA_pairs_to_MOOP_TSV.pl" \
               "$PAIR_FILE" "$OMA_CODE" "$OTHERORG" "$THISORG_FIRST" "$OMA_VERSION"; }
    done
    shopt -u nullglob

    if [ -s "$OMA_DIR/mapGO/go.tsv" ] && [ -s "$OMA_DIR/Output/Map-SeqNum-ID.txt" ] && [ -s "$OMA_DIR/Output/gene_function.gaf" ]; then
      has_data "${OMA_CODE}.OMA2GO.moop.tsv" \
        || { echo "Building OMA2GO for $OMA_CODE"
             perl "$REPO/analysis_parsers/parse_OMA2GO_to_MOOP_TSV.pl" "$OMA_CODE" "$OMA_VERSION" \
               "$OMA_DIR/mapGO/go.tsv" "$OMA_DIR/Output/Map-SeqNum-ID.txt" "$OMA_DIR/Output/gene_function.gaf" \
               > "${OMA_CODE}.OMA2GO.moop.tsv.tmp" \
               && mv "${OMA_CODE}.OMA2GO.moop.tsv.tmp" "${OMA_CODE}.OMA2GO.moop.tsv" \
               || { rm -f "${OMA_CODE}.OMA2GO.moop.tsv.tmp"; echo "ERROR: failed to build OMA2GO"; exit 1; }
           }
    fi
  fi
fi

## remove previous entries for this org and check for missing files
sed -i "/^${THIS_ORG}\t/d" "$MISSING_LOG" 2>/dev/null

# ── Build gene name params (shared by both RENAME and T2G paths) ──────────────
## Assembles the ordered source list for assign_gene_names.pl.
## RBBH/OMA-to-Homo-sapiens mappings are optional — only included if the file exists.
build_gene_name_params() {
  ## Override maps are keyed by "<org>/<assembly>/<geneset>" so a mapping is
  ## explicitly opted into per gene-set, instead of silently inherited by any
  ## future geneset that happens to share an organism name (bit us once
  ## already — apollo_moop.tsv only matched 43/45 IDs when MENDER_20260701
  ## replaced MENDER_20260623 for Chamaeleo_calyptratus). Bare-organism keys
  ## are not accepted at all — every entry must be geneset-specific.
  declare -A BEST_MAPPING BEST_MAPPING_2
  BEST_MAPPING["Nematostella_vectensis/GCA_033964005.1/NV2"]="/n/sci/SCI-003939-SBNVEC/genomes/Nvec200/aligned/tcs_v2/analysis/rbbh_2026_02_09/jaNemVect1/RefSeq_jaNemVect1.RBBH.moop.tsv"
  BEST_MAPPING["Chamaeleo_calyptratus/CCA3/MENDER_20260701"]="/n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/apollo_moop.tsv"

  ## TODO: revisit per-organism overrides to force a specific ortholog file into the
  ## naming workflow (e.g. a non-Human OMA mapping). Path below is stale — it's the
  ## pre-OMA_v2 manual run (org code HOMSAP, no $db segment) — left here as a reminder.
  # BEST_MAPPING_2["Chamaeleo_calyptratus/CCA3/MENDER_20260701"]="/n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/combinedModels/orthologs/HOMSAP.oma_orthologs.moop.tsv"

  declare -A NEXT_BEST_MAPPING
  NEXT_BEST_MAPPING["Montipora_capitata/HIv3/HIv3_geneset"]="/n/sci/SCI-004111-SBCORAL/Montipora_capitata/genomes/Montipora_capitata_HIv3/analysis/RBBH/RefSeq_jaNemVect1.RBBH.moop.tsv"

  local GENESET_KEY="$THIS_ORG/$ASSEMBLY/$GENE_SET"

  ## Looks up $1 (an associative array name) at the geneset-specific key
  ## only. Bare-organism keys are never honored, even as a fallback.
  override_lookup() {
    local -n _map=$1
    [[ -n "${_map[$GENESET_KEY]:-}" ]] && echo "${_map[$GENESET_KEY]}"
  }

  PARAMS=("isoforms.tsv")
  local best_mapping best_mapping_2 next_best_mapping
  best_mapping=$(override_lookup BEST_MAPPING)
  [[ -n "$best_mapping" ]] && PARAMS+=("$best_mapping")
  best_mapping_2=$(override_lookup BEST_MAPPING_2)
  [[ -n "$best_mapping_2" ]] && PARAMS+=("$best_mapping_2")
  ## NOTE: only the first match is used (sorts to Ensembl before RefSeq, which is fine
  ## for now). If both HUMAN.Ensembl.* and HUMAN.RefSeq.* exist, the RefSeq one is
  ## silently dropped — revisit if we ever want both sources fed into naming.
  shopt -s nullglob
  local human_oma=(HUMAN.*.oma_orthologs.moop.tsv)
  shopt -u nullglob
  [[ -n "${human_oma[0]:-}" ]] && PARAMS+=("${human_oma[0]}")
  [ -f "Ensembl_Homo_sapiens.RBBH.moop.tsv" ] && PARAMS+=("Ensembl_Homo_sapiens.RBBH.moop.tsv")
  next_best_mapping=$(override_lookup NEXT_BEST_MAPPING)
  [[ -n "$next_best_mapping" ]] && PARAMS+=("$next_best_mapping")
  PARAMS+=("UniProtKB_Swiss-Prot.homologs.moop.tsv")
  PARAMS+=("PANTHER.iprscan.moop.tsv")

  for file in "${PARAMS[@]}"; do
    if [ ! -f "$file" ]; then
      echo "ERROR: Required file $file is missing! Gene naming will fail."
      exit 1
    fi
  done
}

# ═════════════════════════════════════════════════════════════════════════════
# GFF PATH — genome-backed geneset
# ═════════════════════════════════════════════════════════════════════════════
if $HAS_GFF; then

  echo "Detecting GFF format and setting up symlinks"

  GFF_SOURCE=$(perl -ne '
    next if /^#/;
    my @f = split /\t/;
    next unless @f >= 9 && $f[2] eq "gene";
    if    ($f[8] =~ /\bID=gene:/)                                 { print "ensembl"; exit }
    elsif ($f[8] =~ /\bID=gene-/ || $f[8] =~ /\bDbxref=GeneID:/) { print "refseq";  exit }
    print "other"; exit
  ' "$GENESET_DIR/genes.gff")
  GFF_SOURCE=${GFF_SOURCE:-other}
  echo "GFF format: $GFF_SOURCE"

  case "$GFF_SOURCE" in
    refseq)  write_geneset_json "RefSeq"   ;;
    ensembl) write_geneset_json "Ensembl"  ;;
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

  REBUILD=false

  has_data isoforms.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building isoforms.tsv"
    perl "$REPO/analysis_parsers/make_isoforms_from_gff.pl" "$GENESET_DIR/genes.gff" > isoforms.tsv.tmp \
      && mv isoforms.tsv.tmp isoforms.tsv \
      || { rm -f isoforms.tsv.tmp; echo "ERROR: failed to build isoforms.tsv"; exit 1; }
  fi

  has_data geneNames.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building geneNames.tsv"
    if $RENAME; then
      build_gene_name_params
      perl "$REPO/analysis_parsers/assign_gene_names.pl" "${PARAMS[@]}" > geneNames.tsv.tmp \
        && mv geneNames.tsv.tmp geneNames.tsv \
        || { rm -f geneNames.tsv.tmp; echo "ERROR: failed to build geneNames.tsv"; exit 1; }

      echo "Updating GFF and FASTAs"
      perl "$REPO/analysis_parsers/updateGFF.pl"   "$GENESET_DIR/genes.gff"         geneNames.tsv > genes.gff.tmp \
        && mv genes.gff.tmp genes.gff \
        || { rm -f genes.gff.tmp;      echo "ERROR: failed to build genes.gff";     exit 1; }
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
      perl "$REPO/analysis_parsers/get_names_from_gff.pl" "$GENESET_DIR/genes.gff" > geneNames.tsv.tmp \
        && mv geneNames.tsv.tmp geneNames.tsv \
        || { rm -f geneNames.tsv.tmp; echo "ERROR: failed to build geneNames.tsv"; exit 1; }
    fi
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

  if [[ "$GFF_SOURCE" == "other" ]]; then
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
    perl "$REPO/analysis_parsers/parse_GFF3_to_MOOP_TSV.pl" genes.gff \
      "$GENESET_DIR/metadata.yaml" cds.nt.fa protein.aa.fa > features.tsv.tmp \
      && mv features.tsv.tmp features.tsv \
      || { rm -f features.tsv.tmp; echo "ERROR: failed to build features.tsv"; exit 1; }
  fi

  if $REBUILD; then
    sh "$SCRIPTS/setup_new_moopdb_and_load_data.sh" "$THIS_ORG" "$GENE_SET" "$DATA/$THIS_ORG"

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

# ═════════════════════════════════════════════════════════════════════════════
# T2G PATH — transcriptome-only geneset (no genome, no GFF)
# ═════════════════════════════════════════════════════════════════════════════
else

  write_geneset_json "$(infer_source "$GENE_SET")"
  ensure_organism_json "gene" "mRNA,transcript"

  check_missing_files_t2g

  REBUILD=false

  has_data isoforms.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building isoforms.tsv from protein2gene.txt"
    perl "$REPO/analysis_parsers/make_isoforms_from_transcript2gene.pl" \
      "$GENESET_DIR/protein2gene.txt" > isoforms.tsv.tmp \
      && mv isoforms.tsv.tmp isoforms.tsv \
      || { rm -f isoforms.tsv.tmp; echo "ERROR: failed to build isoforms.tsv"; exit 1; }
  fi

  has_data geneNames.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building geneNames.tsv"
    build_gene_name_params
    perl "$REPO/analysis_parsers/assign_gene_names.pl" "${PARAMS[@]}" > geneNames.tsv.tmp \
      && mv geneNames.tsv.tmp geneNames.tsv \
      || { rm -f geneNames.tsv.tmp; echo "ERROR: failed to build geneNames.tsv"; exit 1; }

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

  has_data features.tsv || REBUILD=true
  if $REBUILD; then
    echo "Building features.tsv from transcript2gene.txt"
    perl "$REPO/analysis_parsers/parse_transcript2gene_to_MOOP_TSV.pl" \
      "$GENESET_DIR/transcript2gene.txt" geneNames.tsv "$GENESET_DIR/metadata.yaml" \
      "$GENESET_DIR/protein2gene.txt" "$GENESET_DIR/cds2gene.txt" > features.tsv.tmp \
      && mv features.tsv.tmp features.tsv \
      || { rm -f features.tsv.tmp; echo "ERROR: failed to build features.tsv"; exit 1; }
  fi

  if $REBUILD; then
    sh "$SCRIPTS/setup_new_moopdb_and_load_data.sh" "$THIS_ORG" "$GENE_SET" "$DATA/$THIS_ORG"
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

fi

if [ "${SKIP_COPY:-0}" -eq 1 ]; then
  echo "SKIP_COPY=1: not copying $THIS_ORG/$ASSEMBLY/$GENE_SET to moop."
else
  sh "$SCRIPTS/copy2moop_v2.sh" "$THIS_ORG" "$ASSEMBLY" "$GENE_SET"
fi
