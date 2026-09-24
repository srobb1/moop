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

: "${GENOMES:=/n/sci/SCI-004223-SBGENOMES/genomes/v2}"
: "${ANNOTATIONS:=/n/sci/SCI-004223-SBGENOMES/annotations/sbgenomes_2}"
: "${REF_DB:=/n/sci/SCI-004223-SBGENOMES/db}"
: "${REFERENCE_DATA:=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop}"
: "${OMA_BASE:=/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/OMA_v2}"

export GENOMES ANNOTATIONS REF_DB REFERENCE_DATA OMA_BASE
