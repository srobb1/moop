#!/bin/bash
# The one place the data trees are named. Source this; do not re-declare them.
#
#   source "$(dirname "${BASH_SOURCE[0]}")/paths.sh"
#
# WHY THIS FILE EXISTS. These two paths were literals repeated across seven files,
# and both have now moved:
#
#   genomes_v2                -> genomes/v2          (2026-07-28)
#   annotations/SBGENOMES_... -> a new dated tree each time the analysis is rerun
#
# Every copy has to be found and edited together, and a missed one does not fail
# loudly -- it reports the inputs as absent. That is exactly how a reload came to
# drop an organism.sqlite and then skip the gene set with "no genes.gff or
# transcript2gene.txt, nothing to process": the tree had moved, list_active_genesets.sh
# honoured a GENOMES override, and process_one_geneset.sh did not.
#
# ':' with ':=' assigns ONLY when unset, so an environment override still wins:
#
#   GENOMES=/some/other/tree bash run_all_v2.sh --reload
#
# ANNOTATIONS carries a DATE. When the analysis is regenerated under a new name,
# change it HERE, once.
#
# REF_DB is the reference-database tree ($REF_DB/ENS_<species>/current/*.pep.all.fa.gz,
# $REF_DB/UNIPROT_sprot/..., etc).
#
# REFERENCE_DATA holds what gene naming v2 downloads itself (hgnc/, ensembl_compara/,
# uniprot/, ncbi_taxonomy/), kept current by scripts/update_reference_data.sh.
# OMA_BASE is where the OMA runs live, one <organism>/<assembly>/<geneset> dir each.
# OMA_REFERENCE_RUN is the default template's reference run (its reference genomes only):
# gene sets that ARE a reference genome take their orthologs from it (process_one_geneset.sh).
# INTERPROSCAN_DIR is the InterProScan install the annotation pipeline runs; gene naming reads
# PANTHER's HMM lengths from its data/panther/ (update_reference_data.sh), which must be the
# PANTHER release the gene sets' InterProScan results came from.

: "${GENOMES:=/n/sci/SCI-004223-SBGENOMES/genomes/v2}"
: "${ANNOTATIONS:=/n/sci/SCI-004223-SBGENOMES/annotations/sbgenomes_2}"
: "${REF_DB:=/n/sci/SCI-004223-SBGENOMES/db}"
: "${REFERENCE_DATA:=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop}"
: "${OMA_BASE:=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/OMA_v2}"
: "${OMA_REFERENCE_RUN:=$OMA_BASE/OMA_TEMPLATE_RUNS/BRAFL_CALMI_CAPTE_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE_NEMVE/reference_run}"
: "${INTERPROSCAN_DIR:=/n/projects/sm2699/SBG_v4/src/interproscan/interproscan-5.78-109.0}"

export GENOMES ANNOTATIONS REF_DB REFERENCE_DATA OMA_BASE OMA_REFERENCE_RUN INTERPROSCAN_DIR

## data_assembly ORGANISM ASSEMBLY -- the name of the assembly's directory in data/ and on moop.
##
## $GENOMES names assembly directories however they were delivered (GRCz11, schMedA2h1), but the
## database records the genome-accession from metadata.yaml (GCA_000002035.4), and the site finds
## an assembly's files by that accession. So the built tree is named by the accession, read from
## the assembly's gene sets' metadata.yaml; an assembly with none (empty or NA) keeps its own name.
## Gene sets of one assembly that disagree on the accession are an error: there is no right answer.
data_assembly() {
  local org=$1 asm=$2 metas=() meta acc
  for meta in "$GENOMES/$org/$asm"/*/metadata.yaml; do
    [ -f "$meta" ] && metas+=("$meta")
  done
  [ ${#metas[@]} -gt 0 ] || { echo "$asm"; return 0; }
  acc=$(awk '
    FNR == 1 { seen = 0 }
    !seen && /^genome-accession:/ {
      seen = 1
      sub(/^genome-accession:[ \t]*/, "")
      if ($1 == "" || $1 == "NA") next
      if (name == "") { name = $1; first = FILENAME }
      else if ($1 != name) conflict = conflict "\n  " $1 "  " FILENAME
    }
    END {
      if (conflict != "") {
        print "ERROR: gene sets of one assembly disagree on genome-accession:\n  " name "  " first conflict > "/dev/stderr"
        exit 1
      }
      print name
    }' "${metas[@]}") || return 1
  echo "${acc:-$asm}"
}
