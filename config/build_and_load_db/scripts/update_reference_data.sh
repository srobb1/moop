#!/bin/bash
# Check and refresh the shared reference data used by gene naming v2 and the OMA setup.
# Run once before a full reprocess (run_all_v2.sh calls it); safe to run any time.
#
#   bash scripts/update_reference_data.sh
#
# Writes only under $REFERENCE_DATA (paths.sh; dev/smr_dev/moop); $REF_DB is only read.
#
#   ensembl_compara/release-<N>/homo_sapiens.orthologs.tsv.gz
#       Ensembl Compara protein orthologs of human, all species, for every Ensembl release used
#       by $REF_DB/ENS_*/current/db_version.txt. Ensembl stores each homology pair once, so
#       the human file holds human<->every species; downloaded only when missing, checked
#       against Ensembl's CHECKSUMS, and cut to ortholog rows.
#   hgnc/hgnc_complete_set.txt, hgnc/withdrawn.txt
#       downloaded when HGNC has a newer file than ours.
#   ncbi_taxonomy/ (nodes.dmp names.dmp merged.dmp delnodes.dmp)
#       checked at most every TAXONOMY_MAX_AGE_DAYS days; downloaded when NCBI's md5 changed.
#   uniprot/sprot_xrefs.tsv.gz
#       per Swiss-Prot entry: taxon, gene name, HGNC, Ensembl genes/proteins, PANTHER ids and
#       secondary accessions, parsed from uniprot_sprot.dat.gz (parse_uniprot_dat.pl, next to
#       this script; the flat file itself is not kept). Rebuilt when UniProt publishes a new
#       release, so it is never older than the Swiss-Prot the annotation pipeline searched.
#   interpro/entry.list
#       every InterPro entry: accession, type (Domain, Repeat, Family, ...) and curated name.
#       Gene naming's last step names a gene after its InterPro domain or repeat; the type is
#       what tells a domain from a family. Downloaded when InterPro publishes a new release.
#
# A failed download keeps the existing copy and prints a WARNING; the exit code is the number
# of warnings (0 = everything current).

set -uo pipefail

SCRIPTS=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "$SCRIPTS/paths.sh"
MOOP_DIR=$REFERENCE_DATA
: "${TAXONOMY_MAX_AGE_DAYS:=30}"
TODAY=$(date '+%Y-%m-%d')
WARNINGS=0

warn() {
  echo "WARNING: $*" >&2
  WARNINGS=$((WARNINGS + 1))
}

# ---------------------------------------------------------------- Ensembl Compara
update_compara() {
  local releases release out_dir out base expected got tmp checksums file uncompress version_file dir_name version
  # main Ensembl only: plant dirs (ENS_PL_*) and Ensembl Genomes releases such as
  # "release-61_bacteria_128_collection" have no human Compara file
  releases=""
  for version_file in "$REF_DB"/ENS_*/current/db_version.txt; do
    [ -e "$version_file" ] || continue
    dir_name=$(basename "$(dirname "$(dirname "$version_file")")")
    version=$(head -1 "$version_file")
    if [[ "$dir_name" == ENS_PL_* ]] || ! [[ "$version" =~ ^release-([0-9]+)[[:space:]]*$ ]]; then
      echo "compara: $dir_name ($version) is not a main Ensembl release; skipped"
      continue
    fi
    releases="$releases ${BASH_REMATCH[1]}"
  done
  releases=$(echo $releases | tr ' ' '\n' | sort -un)
  if [ -z "$releases" ]; then
    warn "no Ensembl releases found in $REF_DB/ENS_*/current/db_version.txt"
    return
  fi
  for release in $releases; do
    out_dir="$MOOP_DIR/ensembl_compara/release-$release"
    out="$out_dir/homo_sapiens.orthologs.tsv.gz"
    if [ -s "$out" ]; then
      echo "compara: release $release present"
      continue
    fi
    mkdir -p "$out_dir"
    base="https://ftp.ensembl.org/pub/release-$release/tsv/ensembl-compara/homologies/homo_sapiens"
    # plain .tsv in older releases, .tsv.gz from release 116
    checksums=$(curl -sS -m 120 "$base/CHECKSUMS")
    file=""
    expected=""
    for candidate in "Compara.$release.protein_default.homologies.tsv" "Compara.$release.protein_default.homologies.tsv.gz"; do
      expected=$(echo "$checksums" | awk -v f="$candidate" '$3 == f {print $1, $2}')
      if [ -n "$expected" ]; then
        file=$candidate
        break
      fi
    done
    if [ -z "$file" ]; then
      warn "compara: no protein_default homologies file listed in $base/CHECKSUMS"
      continue
    fi
    [[ "$file" == *.gz ]] && uncompress="gzip -dc" || uncompress="cat"
    echo "compara: downloading release $release human homologies ($file)"
    tmp="$out.tmp"
    curl -sS -m 7200 "$base/$file" \
      | tee >(sum > "$tmp.sum") \
      | $uncompress \
      | awk -F'\t' 'NR == 1 || $5 ~ /^ortholog/' \
      | gzip > "$tmp"
    local waited=0
    while [ ! -s "$tmp.sum" ] && [ $waited -lt 30 ]; do sleep 1; waited=$((waited + 1)); done
    got=$(awk '{print $1, $2}' "$tmp.sum" 2>/dev/null)
    rm -f "$tmp.sum"
    if [ "$got" != "$expected" ]; then
      rm -f "$tmp"
      warn "compara: release $release checksum mismatch (got '$got', expected '$expected'); nothing saved"
      continue
    fi
    mv "$tmp" "$out"
    echo "Ensembl Compara release $release: homo_sapiens protein_default homologies, ortholog rows only; downloaded $TODAY from $base/$file" > "$out_dir/VERSION.txt"
    echo "compara: release $release saved ($(gzip -dc "$out" | tail -n +2 | wc -l) ortholog rows)"
  done
}

# ---------------------------------------------------------------- HGNC
update_hgnc() {
  local dir="$MOOP_DIR/hgnc" base=https://storage.googleapis.com/public-download-files/hgnc/tsv/tsv
  local file changed=0
  mkdir -p "$dir"
  for file in hgnc_complete_set.txt withdrawn.txt; do
    local target="$dir/$file" tmp="$dir/$file.tmp" code
    if [ -s "$target" ]; then
      code=$(curl -sS -m 600 -z "$target" -o "$tmp" -w '%{http_code}' "$base/$file")
    else
      code=$(curl -sS -m 600 -o "$tmp" -w '%{http_code}' "$base/$file")
    fi
    if [ "$code" = "304" ]; then
      rm -f "$tmp"
      echo "hgnc: $file current"
    elif [ "$code" = "200" ] && [ -s "$tmp" ] && head -1 "$tmp" | grep -q -i 'hgnc_id'; then
      mv "$tmp" "$target"
      changed=1
      echo "hgnc: $file updated"
    else
      rm -f "$tmp"
      warn "hgnc: could not download $file (HTTP $code); keeping the existing copy"
    fi
  done
  [ $changed -eq 1 ] && echo "downloaded $TODAY from $base/ (hgnc_complete_set.txt, withdrawn.txt)" > "$dir/VERSION.txt"
}

# ---------------------------------------------------------------- NCBI taxonomy
update_taxonomy() {
  local dir="$MOOP_DIR/ncbi_taxonomy" base=https://ftp.ncbi.nlm.nih.gov/pub/taxonomy
  local remote_md5 local_md5 age_days
  mkdir -p "$dir"
  if [ -s "$dir/nodes.dmp" ] && [ -e "$dir/VERSION.txt" ]; then
    age_days=$(( ( $(date +%s) - $(stat -c %Y "$dir/VERSION.txt") ) / 86400 ))
    if [ "$age_days" -lt "$TAXONOMY_MAX_AGE_DAYS" ]; then
      echo "taxonomy: checked $age_days days ago (limit $TAXONOMY_MAX_AGE_DAYS)"
      return
    fi
  fi
  remote_md5=$(curl -sS -m 120 "$base/taxdump.tar.gz.md5" | awk '{print $1}')
  local_md5=$(awk '{print $1}' "$dir/taxdump.tar.gz.md5" 2>/dev/null)
  if [ -z "$remote_md5" ]; then
    warn "taxonomy: could not read $base/taxdump.tar.gz.md5; keeping the existing copy"
    return
  fi
  if [ "$remote_md5" = "$local_md5" ] && [ -s "$dir/nodes.dmp" ]; then
    touch "$dir/VERSION.txt"
    echo "taxonomy: unchanged at NCBI"
    return
  fi
  echo "taxonomy: downloading taxdump.tar.gz"
  if curl -sS -m 1800 -o "$dir/taxdump.tar.gz.tmp" "$base/taxdump.tar.gz" \
     && [ "$(md5sum < "$dir/taxdump.tar.gz.tmp" | awk '{print $1}')" = "$remote_md5" ] \
     && tar -xzf "$dir/taxdump.tar.gz.tmp" -C "$dir" nodes.dmp names.dmp merged.dmp delnodes.dmp; then
    mv "$dir/taxdump.tar.gz.tmp" "$dir/taxdump.tar.gz"
    echo "$remote_md5  taxdump.tar.gz" > "$dir/taxdump.tar.gz.md5"
    echo "downloaded $TODAY from $base/taxdump.tar.gz" > "$dir/VERSION.txt"
    echo "taxonomy: updated"
  else
    rm -f "$dir/taxdump.tar.gz.tmp"
    warn "taxonomy: download or md5 check failed; keeping the existing copy"
  fi
}

# ---------------------------------------------------------------- UniProt Swiss-Prot cross-references
update_uniprot() {
  local dir="$MOOP_DIR/uniprot" base=https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete
  local remote_release local_release tmp status
  mkdir -p "$dir"
  remote_release=$(curl -sS -m 120 "$base/reldate.txt" | sed -n 's/^UniProt Knowledgebase Release \([0-9_]*\).*/\1/p' | head -1)
  local_release=$(sed -n 's/^UniProt release \([0-9_]*\).*/\1/p' "$dir/VERSION.txt" 2>/dev/null)
  if [ -z "$remote_release" ]; then
    warn "uniprot: could not read the current release from $base/reldate.txt; keeping the existing copy"
    return
  fi
  # also rebuild a table made by an older parse_uniprot_dat.pl (no secondary_accessions column)
  if [ "$remote_release" = "$local_release" ] && [ -s "$dir/sprot_xrefs.tsv.gz" ] \
     && gzip -dc "$dir/sprot_xrefs.tsv.gz" | head -1 | grep -q 'secondary_accessions'; then
    echo "uniprot: release $local_release current"
    return
  fi
  echo "uniprot: building Swiss-Prot cross-references for release $remote_release"
  tmp="$dir/sprot_xrefs.tsv.gz.tmp"
  curl -sS -m 7200 "$base/uniprot_sprot.dat.gz" | gzip -dc | perl "$SCRIPTS/parse_uniprot_dat.pl" | gzip > "$tmp"
  status=("${PIPESTATUS[@]}")
  if [ "${status[0]}" -ne 0 ] || [ "${status[1]}" -ne 0 ] || [ "${status[2]}" -ne 0 ] || [ "$(gzip -dc "$tmp" | wc -l)" -lt 100000 ]; then
    rm -f "$tmp"
    warn "uniprot: download or parse failed (curl/gzip/parse exit ${status[*]}); keeping the existing copy"
    return
  fi
  mv "$tmp" "$dir/sprot_xrefs.tsv.gz"
  echo "UniProt release $remote_release: Swiss-Prot cross-references parsed $TODAY from $base/uniprot_sprot.dat.gz" > "$dir/VERSION.txt"
  echo "uniprot: release $remote_release saved ($(( $(gzip -dc "$dir/sprot_xrefs.tsv.gz" | wc -l) - 1 )) entries)"
}

# ---------------------------------------------------------------- InterPro entry list
update_interpro() {
  local dir="$MOOP_DIR/interpro" base=https://ftp.ebi.ac.uk/pub/databases/interpro/current_release
  local remote_release local_release tmp
  mkdir -p "$dir"
  remote_release=$(curl -sS -m 120 "$base/release_notes.txt" | sed -n 's/^.*Release \([0-9][0-9.]*\),.*/\1/p' | head -1)
  local_release=$(sed -n 's/^InterPro release \([0-9.]*\).*/\1/p' "$dir/VERSION.txt" 2>/dev/null)
  if [ -z "$remote_release" ]; then
    warn "interpro: could not read the current release from $base/release_notes.txt; keeping the existing copy"
    return
  fi
  if [ "$remote_release" = "$local_release" ] && [ -s "$dir/entry.list" ]; then
    echo "interpro: release $local_release current"
    return
  fi
  echo "interpro: downloading entry.list for release $remote_release"
  tmp="$dir/entry.list.tmp"
  if ! curl -sS -m 600 -o "$tmp" "$base/entry.list" \
     || [ "$(head -1 "$tmp" 2>/dev/null)" != "$(printf 'ENTRY_AC\tENTRY_TYPE\tENTRY_NAME')" ] \
     || [ "$(wc -l < "$tmp")" -lt 30000 ]; then
    rm -f "$tmp"
    warn "interpro: download failed or entry.list looks wrong; keeping the existing copy"
    return
  fi
  mv "$tmp" "$dir/entry.list"
  echo "InterPro release $remote_release: entry.list downloaded $TODAY from $base/entry.list" > "$dir/VERSION.txt"
  echo "interpro: release $remote_release saved ($(( $(wc -l < "$dir/entry.list") - 1 )) entries)"
}

update_compara
update_hgnc
update_taxonomy
update_uniprot
update_interpro

[ $WARNINGS -gt 0 ] && echo "$WARNINGS warning(s); see above" >&2
[ $WARNINGS -gt 255 ] && WARNINGS=255
exit $WARNINGS
