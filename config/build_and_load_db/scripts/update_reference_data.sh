#!/bin/bash
# Check and refresh the shared reference data used by gene naming v2 and the OMA setup.
# Run once before a full reprocess (run_all_v2.sh calls it); safe to run any time.
#
#   bash scripts/update_reference_data.sh              every step
#   bash scripts/update_reference_data.sh hgnc panther_trees   only these steps
#       (compara hgnc taxonomy uniprot human_domains interpro panther pfam panther_trees)
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
#   uniprot/human_pfam.tsv.gz
#       per reviewed human UniProt entry: HGNC ids and Pfam domains (fetch_human_domains.py, paged
#       from rest.uniprot.org and checked against UniProt's total). Gene naming says whether a gene
#       has the Pfam domains of the human gene it is named after. Rebuilt when sprot_xrefs.tsv.gz
#       moves to a new UniProt release, so the two always come from the same release.
#   interpro/entry.list
#       every InterPro entry: accession, type (Domain, Repeat, Family, ...) and curated name.
#       Gene naming's last step names a gene after its InterPro domain or repeat; the type is
#       what tells a domain from a family. Downloaded when InterPro publishes a new release.
#   panther/hmm_lengths.tsv
#       PANTHER family HMM lengths (family<TAB>length), read from the NAME/LENG lines of the
#       famhmm/binHmm in $INTERPROSCAN_DIR (paths.sh). Gene naming names a gene after its
#       PANTHER family only when the match covers most of the family's model; InterProScan's
#       TSV has no model coordinates, so the model length comes from here. Rebuilt when the
#       binHmm changes (md5), so it always matches the PANTHER release InterProScan ran.
#   pfam/pfam_names.tsv
#       Pfam accession, short name, clan and description, from pfam_a.dat in $INTERPROSCAN_DIR (the
#       Pfam release InterProScan ran): the names of the human gene's domains, and their clans -- a
#       domain matched by a sister family of the same clan (TIR / TIR_2, clan CL0173) is the same
#       domain. Rebuilt when pfam_a.dat changes (md5).
#   panther/treegrafter/<release>/
#       PANTHER's TreeGrafter data for the release InterProScan uses (PANTHER<release>_data.tar.gz
#       from data.pantherdb.org/ftp/downloads/TreeGrafter/, ~3 GB): the family trees with every
#       node's type (speciation / duplication) and every leaf's gene (e.g. HUMAN|HGNC=..|UniProtKB=..).
#       InterProScan's own copy has the trees without leaf genes; with these, the graft point
#       InterProScan's JSON reports for a protein can be traced to the human genes it is
#       orthologous to. Downloaded once per PANTHER release.
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

# ---------------------------------------------------------------- PANTHER HMM lengths
update_panther() {
  local dir="$MOOP_DIR/panther" hmm hmms release md5 tmp
  mkdir -p "$dir"
  hmms=("$INTERPROSCAN_DIR"/data/panther/*/famhmm/binHmm)
  if [ ! -s "${hmms[0]}" ]; then
    warn "panther: no data/panther/*/famhmm/binHmm under INTERPROSCAN_DIR=$INTERPROSCAN_DIR; keeping the existing copy"
    return
  fi
  if [ ${#hmms[@]} -gt 1 ]; then
    warn "panther: several PANTHER releases under $INTERPROSCAN_DIR/data/panther (${hmms[*]}); keeping the existing copy"
    return
  fi
  hmm=${hmms[0]}
  release=$(basename "$(dirname "$(dirname "$hmm")")")
  md5=$(md5sum "$hmm" | cut -d' ' -f1)
  if [ -s "$dir/hmm_lengths.tsv" ] && grep -q "md5 $md5" "$dir/VERSION.txt" 2>/dev/null; then
    echo "panther: release $release current"
    return
  fi
  echo "panther: reading HMM lengths from $hmm"
  tmp="$dir/hmm_lengths.tsv.tmp"
  ## binHmm holds one "NAME PTHR12345.orig.30.pir" and one "LENG 290" line per family model
  grep -a -E '^(NAME|LENG) ' "$hmm" \
    | awk '$1 == "NAME" { family = $2; sub(/\.orig\.30\.pir$/, "", family) }
           $1 == "LENG" && family != "" { print family "\t" $2; family = "" }' > "$tmp"
  if [ "$(wc -l < "$tmp")" -lt 10000 ] || grep -qv -P '^PTHR\d+\t\d+$' "$tmp"; then
    rm -f "$tmp"
    warn "panther: $hmm gave an unexpected lengths table; keeping the existing copy"
    return
  fi
  mv "$tmp" "$dir/hmm_lengths.tsv"
  echo "PANTHER release $release: family HMM lengths read $TODAY from $hmm (md5 $md5)" > "$dir/VERSION.txt"
  echo "panther: release $release saved ($(wc -l < "$dir/hmm_lengths.tsv") families)"
}

# ---------------------------------------------------------------- human Pfam domains (UniProt)
update_human_domains() {
  local dir="$MOOP_DIR/uniprot" release have
  release=$(sed -n 's/^UniProt release \([0-9_]*\).*/\1/p' "$dir/VERSION.txt" 2>/dev/null)
  have=$(gzip -dc "$dir/human_pfam.tsv.gz" 2>/dev/null | head -1 | sed -n 's/^# UniProt release \([0-9_]*\).*/\1/p')
  if [ -n "$have" ] && [ "$have" = "$release" ]; then
    echo "human_domains: UniProt release $have current"
    return
  fi
  echo "human_domains: fetching reviewed human Pfam domains (UniProt ${release:-current})"
  if ! python3 "$SCRIPTS/fetch_human_domains.py" --out "$dir/human_pfam.tsv.gz"; then
    warn "human_domains: download failed; keeping the existing copy"
    return
  fi
  have=$(gzip -dc "$dir/human_pfam.tsv.gz" | head -1 | sed -n 's/^# UniProt release \([0-9_]*\).*/\1/p')
  [ -n "$release" ] && [ "$have" != "$release" ] \
    && warn "human_domains: UniProt served release $have, sprot_xrefs.tsv.gz is $release; run the uniprot step too"
}

# ---------------------------------------------------------------- Pfam names (InterProScan's Pfam)
update_pfam() {
  local dir="$MOOP_DIR/pfam" dats dat release md5 tmp
  mkdir -p "$dir"
  dats=("$INTERPROSCAN_DIR"/data/pfam/*/pfam_a.dat)
  if [ ! -s "${dats[0]}" ]; then
    warn "pfam: no data/pfam/*/pfam_a.dat under INTERPROSCAN_DIR=$INTERPROSCAN_DIR; keeping the existing copy"
    return
  fi
  if [ ${#dats[@]} -gt 1 ]; then
    warn "pfam: several Pfam releases under $INTERPROSCAN_DIR/data/pfam (${dats[*]}); keeping the existing copy"
    return
  fi
  dat=${dats[0]}
  release=$(basename "$(dirname "$dat")")
  md5=$(md5sum "$dat" | cut -d' ' -f1)
  if [ -s "$dir/pfam_names.tsv" ] && grep -q "md5 $md5" "$dir/VERSION.txt" 2>/dev/null; then
    echo "pfam: release $release current"
    return
  fi
  tmp="$dir/pfam_names.tsv.tmp"
  ## one record per family ending in "//": "#=GF ID", "#=GF AC", "#=GF DE" and, for a family in a clan, "#=GF CL"
  awk '/^#=GF ID/ { id = $3 }
       /^#=GF AC/ { accession = $3; sub(/\.[0-9]+$/, "", accession) }
       /^#=GF DE/ { description = $0; sub(/^#=GF DE +/, "", description) }
       /^#=GF CL/ { clan = $3 }
       /^\/\// { if (accession != "") print accession "\t" id "\t" clan "\t" description; id = accession = clan = description = "" }' "$dat" > "$tmp"
  if [ "$(wc -l < "$tmp")" -lt 10000 ] || grep -qv -P '^PF\d+\t\S+\t(CL\d+)?\t' "$tmp"; then
    rm -f "$tmp"
    warn "pfam: $dat gave an unexpected names table; keeping the existing copy"
    return
  fi
  mv "$tmp" "$dir/pfam_names.tsv"
  echo "Pfam release $release: accession, name, clan, description read $TODAY from $dat (md5 $md5)" > "$dir/VERSION.txt"
  echo "pfam: release $release saved ($(wc -l < "$dir/pfam_names.tsv") families)"
}

# ---------------------------------------------------------------- PANTHER trees (TreeGrafter data)
update_panther_trees() {
  local base="$MOOP_DIR/panther/treegrafter" hmms release dir tarball url
  hmms=("$INTERPROSCAN_DIR"/data/panther/*/famhmm/binHmm)
  if [ ! -s "${hmms[0]}" ] || [ ${#hmms[@]} -gt 1 ]; then
    warn "panther_trees: cannot tell the PANTHER release from INTERPROSCAN_DIR=$INTERPROSCAN_DIR; skipped"
    return
  fi
  release=$(basename "$(dirname "$(dirname "${hmms[0]}")")")
  dir="$base/$release"
  if [ -s "$dir/VERSION.txt" ]; then
    echo "panther_trees: release $release present"
    return
  fi
  mkdir -p "$dir"
  url="https://data.pantherdb.org/ftp/downloads/TreeGrafter/PANTHER${release}_data.tar.gz"
  tarball="$dir/PANTHER${release}_data.tar.gz"
  echo "panther_trees: downloading $url (~3 GB)"
  if ! curl -fsSL --retry 3 -o "$tarball.part" "$url"; then
    rm -f "$tarball.part"
    warn "panther_trees: download of $url failed"
    return
  fi
  mv "$tarball.part" "$tarball"
  if ! tar -xzf "$tarball" -C "$dir"; then
    warn "panther_trees: $tarball did not unpack; kept for a look"
    return
  fi
  rm -f "$tarball"
  echo "PANTHER release $release: TreeGrafter data downloaded $TODAY from $url" > "$dir/VERSION.txt"
  echo "panther_trees: release $release saved in $dir"
}

STEPS=("$@")
[ ${#STEPS[@]} -eq 0 ] && STEPS=(compara hgnc taxonomy uniprot human_domains interpro panther pfam panther_trees)
for step in "${STEPS[@]}"; do
  case "$step" in
    compara|hgnc|taxonomy|uniprot|human_domains|interpro|panther|pfam|panther_trees) "update_$step" ;;
    *) warn "unknown step '$step' (compara hgnc taxonomy uniprot human_domains interpro panther pfam panther_trees)" ;;
  esac
done

[ $WARNINGS -gt 0 ] && echo "$WARNINGS warning(s); see above" >&2
[ $WARNINGS -gt 255 ] && WARNINGS=255
exit $WARNINGS
