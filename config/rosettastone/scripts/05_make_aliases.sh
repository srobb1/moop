#!/bin/bash
# Step 05: build the alias table: other names people use for the mapped sequences.
#
#   05_make_aliases.sh -c rosettastone.conf
#
# Writes $OUT_DIR/aliases/aliases.txt
#          alias  accession  source  type
#        "accession" is an ID as it appears in a query or probe set, and "source" is that set's
#        name. An alias maps wherever its accession maps.
#
# Aliases come from:
#   ALIAS_RULES   optional bash file of organism-specific rules that derive aliases from the
#                 FASTA headers (see examples/). It is sourced with these helpers available:
#                   rule NAME INPUT...  start $AL/rules/NAME.txt; false (with a warning) if no
#                                       INPUT is readable. The readable INPUTs are in $IN.
#                   heads               the ">" header lines of $IN
#                 and appends alias<TAB>accession<TAB>source<TAB>type lines to $AL/rules/NAME.txt.
#                 Every config value (QUERY_DIR, PROBE_DIR, ...) is visible to it.
#   ALIAS_TABLES  space-separated list of ready-made alias tables in the same 4 columns. Lines
#                 separated by spaces rather than tabs are accepted too.
# ALIAS_SOURCE_RENAME ("old=new ...") then renames sources, so a table that says "ncbi" can
# point at the set actually called e.g. ncbi_20240424.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
parse_common_args "$@"
load_config "$CONF"

AL=$OUT_DIR/aliases
rm -rf "$AL/rules"
mkdir -p "$AL/rules"
HDR=$'alias\taccession\tsource\ttype'
nrules=0

rule() {
    local name=$1 f; shift
    [[ "$name" =~ $NAME_RE ]] || die "alias rule name '$name' is not a plain name"
    IN=()
    for f in "$@"; do [ -n "$f" ] && [ -r "$f" ] && IN+=("$f"); done
    if [ ${#IN[@]} -eq 0 ]; then log "alias rule $name: no input (${*:-none}), skipped"; return 1; fi
    printf '%s\n' "$HDR" > "$AL/rules/$name.txt"
    nrules=$((nrules + 1))
}
heads() { grep -h '>' "${IN[@]}"; }

if [ -n "${ALIAS_RULES:-}" ]; then
    [ -r "$ALIAS_RULES" ] || die "cannot read ALIAS_RULES $ALIAS_RULES"
    log "alias rules: $ALIAS_RULES"
    # shellcheck disable=SC1090
    source "$ALIAS_RULES"
fi
tables=()
for t in ${ALIAS_TABLES:-}; do
    [ -r "$t" ] || die "cannot read alias table $t"
    tables+=("$t")
done

# Combine: header lines dropped, whitespace-separated lines normalised to tabs, sources renamed.
shopt -s nullglob
{ printf '%s\n' "$HDR"
  cat /dev/null "$AL"/rules/*.txt "${tables[@]}" \
  | awk -v OFS='\t' -v renames="${ALIAS_SOURCE_RENAME:-}" '
      BEGIN { n = split(renames, r, /[ \t]+/)
              for (i = 1; i <= n; i++) if (split(r[i], kv, "=") == 2) map[kv[1]] = kv[2] }
      { nt = split($0, f, "\t")
        if (nt < 3) nt = split($0, f, /[ \t]+/) }   # space-separated line
      nt < 3 || f[1] == "" || f[2] == "" { next }
      f[1] == "alias" && f[2] == "accession" { next }
      { if (f[3] in map) f[3] = map[f[3]]; print f[1], f[2], f[3], (nt >= 4 ? f[4] : "") }'
} > "$AL/aliases.txt.tmp"
finish "$AL/aliases.txt.tmp" "$AL/aliases.txt"
log "step 05 done: $(($(wc -l < "$AL/aliases.txt") - 1)) aliases from $nrules rule(s) and ${#tables[@]} table(s)"
