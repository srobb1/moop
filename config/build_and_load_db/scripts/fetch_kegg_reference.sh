#!/usr/bin/bash
# fetch_kegg_reference.sh -- a dated snapshot of the KEGG reference lists that parse_KEGG_to_MOOP_TSV.py reads.
#
#   bash scripts/fetch_kegg_reference.sh            # -> $REFERENCE_DATA/kegg/<YYYY-MM-DD>/, and kegg/current -> it
#
# KEGG REST (https://rest.kegg.jp) is free for academic use. Only the lists are fetched (IDs, names, which KO is in
# which pathway or module, the pathway classes); no pathway images. Seven requests; rerun now and then to update.
#
#   ko.tsv              K00844 <TAB> HK; hexokinase [EC:2.7.1.1]
#   pathway.tsv         map00010 <TAB> Glycolysis / Gluconeogenesis
#   module.tsv          M00001 <TAB> Glycolysis (Embden-Meyerhof pathway), glucose => pyruvate
#   ko_pathway.tsv      ko:K00844 <TAB> path:map00010   (also path:ko00010 lines; the parser keeps map)
#   ko_module.tsv       ko:K00844 <TAB> md:M00001
#   pathway_classes.json   br:br08901, the pathway hierarchy (class and subclass of each map)
#   info.txt            KEGG's database dates (it gives no release number: the snapshot date is the version)
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/paths.sh"
BASE=$REFERENCE_DATA/kegg
OUT=$BASE/$(date +%F)
REST=https://rest.kegg.jp
mkdir -p "$OUT"
get() {   # get <path> <file>: download, refuse an empty answer, then move into place
    curl -sSf --retry 3 -m 600 "$REST/$1" -o "$OUT/$2.tmp"
    [ -s "$OUT/$2.tmp" ] || { echo "ERROR: empty answer for $REST/$1" >&2; exit 1; }
    mv "$OUT/$2.tmp" "$OUT/$2"
    echo "  $2: $(wc -l < "$OUT/$2") lines"
}
echo "KEGG snapshot -> $OUT"
get info/kegg info.txt
get list/ko ko.tsv
get list/pathway pathway.tsv
get list/module module.tsv
get link/pathway/ko ko_pathway.tsv
get link/module/ko ko_module.tsv
get get/br:br08901/json pathway_classes.json
ln -sfn "$(basename "$OUT")" "$BASE/current"
echo "KEGG dates: $(awk '$1 == "ko" || $1 == "pathway" || $1 == "module" {printf "%s %s  ", $1, $NF}' "$OUT/info.txt")"
echo "current -> $(readlink "$BASE/current")"
