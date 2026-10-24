#!/bin/bash
# Where the synteny pipeline reads and writes. Source this; do not re-declare these.
#
#   source "$(dirname "${BASH_SOURCE[0]}")/settings.sh"
#
# ':' with ':=' assigns ONLY when unset, so an environment override still wins:
#
#   SYNTENY_OUT=/scratch/me/synteny bash submit_all.sh
#
# MOOP_DATA is the MOOP copy of the organisms tree on the compute side -- the files that
# were (or will be) copied to the site, laid out <organism>/<assembly>/<gene_set>/.
# Use the MOOP copies, NOT the originals in $GENOMES: the browser shows MOOP's IDs
# (e.g. the ':cds' suffixes and normalised IDs), and a synteny file only lines up with
# the gene track when it uses the same IDs.
#
# Each gene set dir needs genes.gff and cds.nt.fa; the assembly dir above it needs
# genome.fa.fai (and genome.fa for whole-genome alignment). feature_coords.tsv is NOT
# needed -- coordinates come from genes.gff, the same file that drives the gene track.

: "${MOOP_DATA:=/CHANGE/ME/moop/organisms}"
: "${SYNTENY_OUT:=/CHANGE/ME/synteny}"

# The two lists an analyst edits (see README.md). Default: next to these scripts.
SYNTENY_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
: "${GENOMES_TSV:=$SYNTENY_DIR/genomes.tsv}"
: "${PAIRS_TSV:=$SYNTENY_DIR/pairs.tsv}"

# Tool modules (cerebro). The gene route is what notes/SYNTENY_PLAN.md calls "MCScan";
# jcvi brings its own LAST.
: "${JCVI_MODULE:=jcvi/1.3.3}"
: "${MINIMAP2_MODULE:=minimap2}"

# Defaults, matching the settings that worked in the 2024 chameleon run.
: "${CSCORE:=.99}"          # jcvi.compara.catalog ortholog --cscore
: "${MINSPAN:=30}"          # jcvi.compara.synteny screen --minspan
: "${WGA_PRESET:=asm20}"    # minimap2 -x, when pairs.tsv gives none
: "${THREADS:=16}"

export MOOP_DATA SYNTENY_OUT SYNTENY_DIR GENOMES_TSV PAIRS_TSV JCVI_MODULE MINIMAP2_MODULE \
       CSCORE MINSPAN WGA_PRESET THREADS
