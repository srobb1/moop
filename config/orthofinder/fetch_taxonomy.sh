#!/usr/bin/bash
# Download the NCBI taxonomy (nodes.dmp, names.dmp, merged.dmp) into $OF_ROOT/taxonomy, which
# make_clade_set.py uses to find a clade's members and build_clade_homologs.py to label tree nodes.
# Rerun now and then to pick up new taxa.
set -euo pipefail
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ORTHOFINDER_CONF=${ORTHOFINDER_CONF:-$HERE/orthofinder.conf}
[ -r "$ORTHOFINDER_CONF" ] || { echo "ERROR: no settings file $ORTHOFINDER_CONF (copy orthofinder.conf.example)" >&2; exit 1; }
source "$ORTHOFINDER_CONF"
URL=https://ftp.ncbi.nlm.nih.gov/pub/taxonomy/taxdump.tar.gz
DEST=$OF_ROOT/taxonomy
mkdir -p "$DEST"
tmp=$(mktemp -d "$DEST/.download.XXXXXX")
trap 'rm -rf "$tmp"' EXIT
curl -sS --fail -o "$tmp/taxdump.tar.gz" "$URL"
tar -xzf "$tmp/taxdump.tar.gz" -C "$tmp" nodes.dmp names.dmp merged.dmp
mv "$tmp"/{nodes,names,merged}.dmp "$DEST/"
echo "downloaded $(date +%F) from $URL (nodes.dmp, names.dmp, merged.dmp)" > "$DEST/SOURCE.txt"
echo "taxonomy updated in $DEST"
