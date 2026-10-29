#!/usr/bin/bash
## Canonical-only protein databases for the Ensembl references (user, 2026-10-29).
##
## The reference databases in $REF_DB (<db>/current/peptide.fa.gz, peptide.dmnd) hold every isoform
## (human: 123,845 proteins for ~20,000 coding genes), so a gene's search hits are crowded by the
## isoforms of one partner gene. Until the annotation pipeline builds them from one protein per gene,
## this makes that set here, beside our own analyses ($REF_DB is not ours to write):
##
##   $OUT/<db>/<release>/peptide.fa.gz     the proteins of Ensembl_canonical transcripts (headers as delivered)
##   $OUT/<db>/<release>/peptide.dmnd      DIAMOND database
##   $OUT/<db>/<release>/db_version.txt    the reference's own, plus the canonical source GTF
##   $OUT/<db>/current -> <release>
##
## The canonical transcripts come from the same release's GTF (tag "Ensembl_canonical"); a protein is
## kept when its header's transcript: is one. A gene with no canonical protein:
##   - in the GTF: its longest protein (Xenopus and chicken GRCg7b tag few transcripts; WormBase/FlyBase/
##     SGD models none);
##   - not in the GTF: dropped. The plain GTF leaves out alternate haplotypes and patches (human
##     HSCHR6_MHC_*: thousands of copies of reference genes), which pep.all has.
## A database with no isoforms to remove (Ensembl Bacteria, ENS_escherichia_coli) is copied whole.
##
## Usage: bash make_canonical_peptide_dbs.sh [db ...]      (default: every ENS_* database in $REF_DB)
##   OUT=... to write elsewhere (default dev/smr_dev/moop/db_canonical)
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/paths.sh"
OUT=${OUT:-/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/db_canonical}
DOWNLOADS=$OUT/_downloads
mkdir -p "$DOWNLOADS"
module purge >/dev/null 2>&1 || true
module load diamond/2.1.6

## pep.all on STDIN -> canonical proteins on STDOUT; args: canonical transcript ids, GTF gene ids
FILTER='
  my ($list, $genes) = @ARGV;
  my (%canonical, %in_gtf);
  open my $list_fh, "<", $list or die "cant open $list\n";
  while (my $id = <$list_fh>) { chomp $id; $canonical{$id} = 1; }
  close $list_fh;
  open my $genes_fh, "<", $genes or die "cant open $genes\n";
  while (my $id = <$genes_fh>) { chomp $id; $in_gtf{$id} = 1; }
  close $genes_fh;
  # per gene: its canonical protein; none tagged (some species tag few or no transcripts), its
  # longest; a gene not in the GTF (alternate haplotype, patch) is dropped
  my (%canonical_of, %longest_of, %length, %order, @genes);
  local $/ = "\n>";
  my $n = 0;
  while (my $record = <STDIN>) {
    $record =~ s/^>//;
    $record =~ s/>$//;
    $record =~ s/\n?$/\n/;
    my ($header) = $record =~ /^(.*)/;
    my ($gene) = $header =~ /\bgene:(\S+)/;
    my ($transcript) = $header =~ /\btranscript:(\S+)/;
    $gene //= $header;
    $transcript //= "";
    (my $bare_transcript = $transcript) =~ s/\.\d+$//;   # a version (ENST...5); Arabidopsis AT5G16970.1 is the id itself
    (my $sequence = $record) =~ s/^.*\n//;
    $sequence =~ s/\s+//g;
    push @genes, $gene unless exists $order{$gene};
    $order{$gene} //= $n++;
    $canonical_of{$gene} //= $record if $canonical{$transcript} or $canonical{$bare_transcript};
    if (!defined $longest_of{$gene} or length($sequence) > $length{$gene}) {
      $longest_of{$gene} = $record;
      $length{$gene} = length $sequence;
    }
  }
  my ($by_tag, $by_length, $dropped) = (0, 0, 0);
  foreach my $gene (@genes) {
    (my $bare_gene = $gene) =~ s/\.\d+$//;
    if (defined $canonical_of{$gene}) { print ">$canonical_of{$gene}"; $by_tag++; }
    elsif ($in_gtf{$bare_gene} or $in_gtf{$gene}) { print ">$longest_of{$gene}"; $by_length++; }
    else { $dropped++; }
  }
  printf STDERR "   %d genes: %d by Ensembl_canonical, %d by longest protein (no tag), %d not in the GTF (alt haplotype/patch) dropped\n",
    scalar(@genes), $by_tag, $by_length, $dropped;
'

if [ $# -gt 0 ]; then DBS=("$@"); else
  mapfile -t DBS < <(cd "$REF_DB" && ls -d ENS_* | sort)
fi

for db in "${DBS[@]}"; do
  src=$REF_DB/$db/current
  release=$(basename "$(readlink -f "$src")")               # release-113, release-61, release-61_bacteria_128_collection
  pep=$(cd "$src" && ls *.pep.all.fa.gz | head -1)
  out=$OUT/$db/$release
  mkdir -p "$out"
  echo "== $db ($release, $pep)"

  if [[ $release == *bacteria* ]]; then
    ## one protein per gene already: no isoforms to remove
    cp "$src/peptide.fa.gz" "$out/peptide.fa.gz"
    note="copied whole (Ensembl Bacteria: one protein per gene)"
  else
    number=${release#release-}; number=${number%%_*}
    species=${db#ENS_}; species=${species#PL_}
    base=${pep%.pep.all.fa.gz}                               # Homo_sapiens.GRCh38
    gtf=$base.$number.gtf.gz
    if [[ $db == ENS_PL_* ]]; then
      url=https://ftp.ensemblgenomes.ebi.ac.uk/pub/plants/$release/gtf/$species/$gtf
    else
      url=https://ftp.ensembl.org/pub/$release/gtf/$species/$gtf
    fi
    [ -s "$DOWNLOADS/$gtf" ] || curl -s -f --max-time 900 -o "$DOWNLOADS/$gtf" "$url" \
      || { echo "   ERROR: could not download $url" >&2; rm -f "$DOWNLOADS/$gtf"; continue; }
    zcat "$DOWNLOADS/$gtf" | awk -F'\t' '$3=="transcript" && $9 ~ /tag "Ensembl_canonical"/' \
      | { grep -o 'transcript_id "[^"]*"' || true; } | cut -d'"' -f2 | sort -u > "$out/canonical_transcripts.txt"
    zcat "$DOWNLOADS/$gtf" | awk -F'\t' '$3=="gene"' \
      | grep -o 'gene_id "[^"]*"' | cut -d'"' -f2 | sort -u > "$out/gtf_genes.txt"
    zcat "$src/peptide.fa.gz" | perl -e "$FILTER" "$out/canonical_transcripts.txt" "$out/gtf_genes.txt" \
      | gzip > "$out/peptide.fa.gz"
    note="one protein per gene: the Ensembl_canonical one in $gtf, else the longest"

  fi

  diamond makedb --in "$out/peptide.fa.gz" --db "$out/peptide.dmnd" --quiet
  { cat "$src/db_version.txt" 2>/dev/null || echo "$release"; } > "$out/db_version.txt"
  printf 'canonical: %s\n' "$note" >> "$out/db_version.txt"
  for extra in "$src"/*.desc.tsv; do [ -e "$extra" ] && cp -p "$extra" "$out/"; done
  ln -sfn "$release" "$OUT/$db/current"
  echo "   $(zcat "$out/peptide.fa.gz" | grep -c '>') proteins -> $out"
done
