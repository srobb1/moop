#!/usr/bin/bash
## make_one_per_gene_peptides.sh -- one protein per gene for the Ensembl reference databases in db/
##
## The Ensembl reference databases (db/ENS_<species>/<release>/) hold every isoform from
## <Species>.<assembly>.pep.all.fa.gz: human has ~120,000 proteins for ~20,000 coding genes, so a
## query's hits are crowded by the isoforms of one partner gene. This writes one protein per gene beside
## the download, in place:
##
##   <Species>.<assembly>.pep.one_per_gene.fa.gz   one protein per gene (headers exactly as delivered)
##   one_per_gene.tsv                              gene, protein, transcript, length, how it was chosen
##   one_per_gene.provenance.txt                   source files, rules, counts, tool versions, date
##   <Species>.<assembly>.<release>.gtf.gz         the release's GTF, the canonical tags were read from
##
## THE RULE, per gene (genes and transcripts from the pep.all header's gene: / transcript:):
##   1. its Ensembl_canonical protein (transcript tagged "Ensembl_canonical" in the same release's GTF);
##   2. no tagged transcript: its LONGEST protein (Xenopus and chicken GRCg7b tag few transcripts;
##      WormBase, FlyBase and SGD models none);
##   3. a gene that is not in the GTF: DROPPED. The plain GTF leaves out alternate haplotypes and patches
##      (human HSCHR6_MHC_* and the like: thousands of copies of reference genes), which pep.all has.
##   Ensembl Bacteria (a release named *bacteria*) has one protein per gene already: copied whole.
##   Hence "one_per_gene", not "canonical": rule 2 picks by length.
##
## THEN, unless turned off (the last two steps are separate so they can be dropped, or done elsewhere):
##   indices (--no-diamond, --no-blast skip)
##                               DIAMOND (makedb) and BLAST (makeblastdb -parse_seqids), for every
##                               database: the one_per_gene set as peptide.dmnd / peptide.p* (with the
##                               switch) or peptide.one_per_gene.dmnd / .p* (without it); with the
##                               switch also the all-isoform set, fresh, as peptide.all.dmnd / .p*
##   switch (--no-switch skips)  the database's own names now mean one protein per gene:
##                               peptide.fa.gz -> the one_per_gene file, peptide.all.fa.gz -> pep.all
##
## Every new file is built in a work folder and moved in at the end, and peptide.fa.gz is switched
## LAST. (BLAST v5 files record their own name, so an index is built under its final name and moved,
## never renamed.) A search started during the few seconds of the move can still see a mix: run it when
## nothing is searching these databases.
##
## RERUNNING is safe: a step whose output exists is skipped. --force redoes the one_per_gene FASTA;
## --overwrite-indices rebuilds every index that exists.
##
## Usage: bash make_one_per_gene_peptides.sh [options] [ENS_<species> ...]   (default: every ENS_*)
##   --db-root DIR          the databases (default /n/sci/SCI-004223-SBGENOMES/db)
##   --release NAME         which release folder (default: whatever current points to)
##   --no-switch            leave peptide.fa.gz and the existing indices as they are
##   --no-diamond           build no DIAMOND index
##   --no-blast             build no BLAST index
##   --overwrite-indices    rebuild indices even if they exist
##   --force                redo the one_per_gene FASTA (and table) even if it exists
##   --gtf-dir DIR          read/keep the GTFs there instead of beside the FASTA
##   --threads N            for DIAMOND/BLAST (default 8)
##   --dry-run              counts only: reads, downloads the GTF to a temp folder, writes nothing in db/
##
## Needs: curl, perl, gzip, DIAMOND and makeblastdb (module load diamond/2.1.13 blast/2.17.0, the
## versions the current indices were built with). Written 2026-10-30 (moop-pipeline); the same rule as
## make_canonical_peptide_dbs.sh there.
set -euo pipefail

DB_ROOT=/n/sci/SCI-004223-SBGENOMES/db
RELEASE=current
SWITCH=1
DIAMOND_INDEX=1
BLAST_INDEX=1
OVERWRITE_INDICES=0
FORCE=0
DRY_RUN=0
GTF_DIR=
THREADS=8

usage() { sed -n '/^## Usage:/,/^## Needs:/p' "$0" | sed 's/^## \{0,1\}//' | sed '$d'; exit "${1:-0}"; }

DBS=()
while [ $# -gt 0 ]; do
  case "$1" in
    --db-root)           DB_ROOT=$2; shift 2 ;;
    --release)           RELEASE=$2; shift 2 ;;
    --no-switch)         SWITCH=0; shift ;;
    --no-diamond)        DIAMOND_INDEX=0; shift ;;
    --no-blast)          BLAST_INDEX=0; shift ;;
    --overwrite-indices) OVERWRITE_INDICES=1; shift ;;
    --force)             FORCE=1; shift ;;
    --gtf-dir)           GTF_DIR=$2; shift 2 ;;
    --threads)           THREADS=$2; shift 2 ;;
    --dry-run)           DRY_RUN=1; shift ;;
    -h|--help)           usage 0 ;;
    -*)                  echo "unknown option: $1" >&2; usage 1 ;;
    *)                   DBS+=("$1"); shift ;;
  esac
done
[ ${#DBS[@]} -gt 0 ] || mapfile -t DBS < <(cd "$DB_ROOT" && ls -d ENS_* | sort)

if [ "$DRY_RUN" = 0 ]; then
  if [ "$DIAMOND_INDEX" = 1 ] && ! command -v diamond >/dev/null; then
    module load diamond/2.1.13 2>/dev/null || { echo "ERROR: no diamond (module load diamond/2.1.13)" >&2; exit 1; }
  fi
  if [ "$BLAST_INDEX" = 1 ] && ! command -v makeblastdb >/dev/null; then
    module load blast/2.17.0 2>/dev/null || { echo "ERROR: no makeblastdb (module load blast/2.17.0)" >&2; exit 1; }
  fi
fi

## pep.all on STDIN -> one protein per gene on STDOUT, its table to $3, counts on STDERR
## args: canonical transcript ids, GTF gene ids, table path
SELECT='
  my ($list, $genes, $table) = @ARGV;
  my (%canonical, %in_gtf);
  open my $list_fh, "<", $list or die "cant open $list\n";
  while (my $id = <$list_fh>) { chomp $id; $canonical{$id} = 1; }
  close $list_fh;
  open my $genes_fh, "<", $genes or die "cant open $genes\n";
  while (my $id = <$genes_fh>) { chomp $id; $in_gtf{$id} = 1; }
  close $genes_fh;
  my (%canonical_of, %longest_of, %order, @genes);
  local $/ = "\n>";
  while (my $record = <STDIN>) {
    $record =~ s/^>//;
    $record =~ s/>$//;
    $record =~ s/\n?$/\n/;
    my ($header) = $record =~ /^(.*)/;
    my ($protein) = $header =~ /^(\S+)/;
    my ($gene) = $header =~ /\bgene:(\S+)/;
    my ($transcript) = $header =~ /\btranscript:(\S+)/;
    $gene //= $protein;
    $transcript //= "";
    (my $bare_transcript = $transcript) =~ s/\.\d+$//;   # a version (ENST...5); Arabidopsis AT5G16970.1 is the id itself
    (my $sequence = $record) =~ s/^.*\n//;
    $sequence =~ s/\s+//g;
    my $entry = { record => $record, protein => $protein, transcript => $transcript, length => length $sequence };
    push @genes, $gene unless exists $order{$gene};
    $order{$gene} = 1;
    $canonical_of{$gene} //= $entry if $canonical{$transcript} or $canonical{$bare_transcript};
    $longest_of{$gene} = $entry if !defined $longest_of{$gene} or $entry->{length} > $longest_of{$gene}{length};
  }
  open my $table_fh, ">", $table or die "cant write $table\n";
  print $table_fh join("\t", qw(gene protein transcript length chosen_by)), "\n";
  my ($by_tag, $by_length, $dropped) = (0, 0, 0);
  foreach my $gene (@genes) {
    (my $bare_gene = $gene) =~ s/\.\d+$//;
    my ($entry, $how);
    if (defined $canonical_of{$gene})                 { ($entry, $how) = ($canonical_of{$gene}, "Ensembl_canonical"); $by_tag++; }
    elsif ($in_gtf{$bare_gene} or $in_gtf{$gene})     { ($entry, $how) = ($longest_of{$gene}, "longest (no canonical tag)"); $by_length++; }
    else                                              { ($entry, $how) = ($longest_of{$gene}, "dropped (not in the GTF: alternate haplotype or patch)"); $dropped++; }
    print ">$entry->{record}" unless $how =~ /^dropped/;
    print $table_fh join("\t", $gene, $entry->{protein}, $entry->{transcript}, $entry->{length}, $how), "\n";
  }
  close $table_fh;
  printf STDERR "%d genes: %d Ensembl_canonical, %d longest (no canonical tag), %d dropped (not in the GTF)\n",
    scalar(@genes), $by_tag, $by_length, $dropped;
'

index_exists() {   # $1 = index name in the release folder: peptide or peptide.one_per_gene
  local kind=$2
  if [ "$kind" = diamond ]; then [ -e "$dir/$1.dmnd" ]; else compgen -G "$dir/$1.p??" >/dev/null; fi
}
build_diamond() {  # FASTA, final index name, work folder
  diamond makedb --in "$1" --db "$3/$2.dmnd" --threads "$THREADS" --quiet
}
build_blast() {    # FASTA, final index name, work folder
  zcat -f "$1" | makeblastdb -in - -dbtype prot -parse_seqids -title "$(basename "$1")" -out "$3/$2" >/dev/null
}

failed=()
for db in "${DBS[@]}"; do
  echo "== $db"
  if [ ! -e "$DB_ROOT/$db/$RELEASE" ]; then echo "   ERROR: no $DB_ROOT/$db/$RELEASE" >&2; failed+=("$db"); continue; fi
  dir=$(readlink -f "$DB_ROOT/$db/$RELEASE")
  release=$(basename "$dir")                        # release-113, release-61, release-61_bacteria_128_collection
  pep=$(cd "$dir" && ls *.pep.all.fa.gz 2>/dev/null | head -1 || true)
  if [ -z "$pep" ]; then echo "   ERROR: no *.pep.all.fa.gz in $dir" >&2; failed+=("$db"); continue; fi
  base=${pep%.pep.all.fa.gz}                         # Homo_sapiens.GRCh38
  one=$base.pep.one_per_gene.fa.gz
  echo "   $dir ($pep)"

  work=$(mktemp -d "${TMPDIR:-/tmp}/one_per_gene.XXXXXX")
  out_dir=$dir
  [ "$DRY_RUN" = 1 ] && out_dir=$work

  ## 1. the one_per_gene FASTA and its table
  if [ -s "$dir/$one" ] && [ "$FORCE" = 0 ] && [ "$DRY_RUN" = 0 ]; then
    echo "   $one exists (--force to redo)"
  elif [[ $release == *bacteria* ]]; then
    cp "$dir/$pep" "$work/$one"
    printf 'gene\tprotein\ttranscript\tlength\tchosen_by\n' > "$work/one_per_gene.tsv"
    note="copied whole: Ensembl Bacteria has one protein per gene"
    echo "   $note"
  else
    number=${release#release-}; number=${number%%_*}
    species=${db#ENS_}; species=${species#PL_}
    gtf=$base.$number.gtf.gz
    if [[ $db == ENS_PL_* ]]; then url=https://ftp.ensemblgenomes.ebi.ac.uk/pub/plants/$release/gtf/$species/$gtf
    else                           url=https://ftp.ensembl.org/pub/$release/gtf/$species/$gtf; fi
    gtf_dir=${GTF_DIR:-$out_dir}
    if [ ! -s "$gtf_dir/$gtf" ]; then
      curl -s -f --max-time 1800 -o "$gtf_dir/$gtf.part" "$url" && mv "$gtf_dir/$gtf.part" "$gtf_dir/$gtf" \
        || { echo "   ERROR: could not download $url" >&2; rm -f "$gtf_dir/$gtf.part"; rm -rf "$work"; failed+=("$db"); continue; }
    fi
    zcat "$gtf_dir/$gtf" | awk -F'\t' '$3=="transcript" && $9 ~ /tag "Ensembl_canonical"/' \
      | { grep -o 'transcript_id "[^"]*"' || true; } | cut -d'"' -f2 | sort -u > "$work/canonical_transcripts.txt"
    zcat "$gtf_dir/$gtf" | awk -F'\t' '$3=="gene"' | { grep -o 'gene_id "[^"]*"' || true; } | cut -d'"' -f2 | sort -u > "$work/gtf_genes.txt"
    counts=$(zcat "$dir/$pep" | perl -e "$SELECT" "$work/canonical_transcripts.txt" "$work/gtf_genes.txt" "$work/one_per_gene.tsv" 2>&1 >"$work/one.fa" | tail -1)
    gzip -c "$work/one.fa" > "$work/$one" && rm -f "$work/one.fa"
    note="$counts; canonical tags from $gtf ($url)"
    echo "   $counts"
  fi
  if [ -s "$work/$one" ]; then
    echo "   $(zcat "$dir/$pep" | grep -c '^>') proteins in $pep -> $(zcat "$work/$one" | grep -c '^>') in $one"
    if [ "$DRY_RUN" = 1 ]; then rm -rf "$work"; continue; fi
    {
      echo "file=$one"
      echo "source=$pep"
      echo "rule=per gene: the Ensembl_canonical protein; no tagged transcript, the longest; a gene not in the GTF (alternate haplotype, patch) dropped"
      echo "result=$note"
      echo "made=$(date -u +%Y-%m-%dT%H:%M:%SZ) by make_one_per_gene_peptides.sh ($USER)"
    } > "$work/one_per_gene.provenance.txt"
    mv "$work/$one" "$work/one_per_gene.tsv" "$work/one_per_gene.provenance.txt" "$dir/"
  elif [ "$DRY_RUN" = 1 ]; then
    rm -rf "$work"; continue
  fi

  ## 2. indices, every database and both kinds, built under their final names in $work and moved in after:
  ##    the one_per_gene set as peptide (after the switch) or peptide.one_per_gene (without it); with the
  ##    switch, the all-isoform set as peptide.all
  switched=0; [ "$(readlink "$dir/peptide.fa.gz" 2>/dev/null || true)" = "$one" ] && switched=1
  if [ "$SWITCH" = 1 ]; then sets=("$one:peptide" "$pep:peptide.all"); else sets=("$one:peptide.one_per_gene"); fi
  mkdir -p "$work/new"
  for kind in diamond blast; do
    [ "$kind" = diamond ] && [ "$DIAMOND_INDEX" = 0 ] && continue
    [ "$kind" = blast ] && [ "$BLAST_INDEX" = 0 ] && continue
    for set in "${sets[@]}"; do
      fasta=${set%%:*}; name=${set#*:}
      ## before the switch, peptide.* is the all-isoform index: replaced whatever the flags say
      if index_exists "$name" "$kind" && [ "$OVERWRITE_INDICES" = 0 ] && ! { [ "$name" = peptide ] && [ "$switched" = 0 ]; }; then
        echo "   $kind index $name exists (--overwrite-indices to rebuild)"; continue
      fi
      echo "   building $kind index $name ($fasta)"
      "build_$kind" "$dir/$fasta" "$name" "$work/new"
    done
  done

  ## 3. move the indices in (an index being replaced is removed first: a BLAST index's old files would
  ##    otherwise mix with the new), then switch peptide.fa.gz LAST
  for built in "$work"/new/*.dmnd; do
    [ -e "$built" ] && mv -f "$built" "$dir/"
  done
  for index in $(ls "$work/new" 2>/dev/null | grep -E '\.p..$' | sed -E 's/\.p..$//' | sort -u); do
    rm -f "$dir/$index".p?? "$dir/$index.pal"
    mv -f "$work/new/$index".p?? "$dir/"
  done
  if [ "$SWITCH" = 1 ] && [ "$switched" = 0 ]; then
    [ -e "$dir/peptide.all.fa.gz" ] || ln -s "$pep" "$dir/peptide.all.fa.gz"
    ln -sfn "$one" "$dir/peptide.fa.gz"
    echo "   switched: peptide.fa.gz -> $one (all isoforms: peptide.all.*)"
  fi
  rm -rf "$work"
done

if [ ${#failed[@]} -gt 0 ]; then echo "FAILED: ${failed[*]}" >&2; exit 1; fi
