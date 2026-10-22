# Gene track indexes — what they are, how they stay current, how to rebuild by hand

Every registered gene set has a JBrowse gene track. JBrowse does not read the gene set's GFF
directly. It reads a **sorted, bgzip-compressed copy plus a tabix index**:

    data/genomes/{organism}/{assembly}/{gene_set}/
        annotations.gff3          → symlink to organisms/{organism}/{assembly}/{gene_set}/genes.gff
        annotations.gff3.gz       the copy JBrowse reads
        annotations.gff3.gz.tbi   its index (.csi instead when a chromosome is over 512 Mb)

The symlink follows every gene-set reload automatically. **The `.gz` and its index do not**:
they are a snapshot taken when the track was built. After a reload, JBrowse keeps showing the
old gene models — and, if feature IDs changed, gene-page links into JBrowse miss — until the
index is rebuilt.

## The rule (one place)

`gene_set_jbrowse_problems()` (`lib/jbrowse/gene_track_index.php`) answers "does this gene set
need a Re-prep?". A new `genes.gff` stales three things, and it checks all of them:

| Part | Files | Out of step when |
|------|-------|------------------|
| `track` | `annotations.gff3.gz` + `.tbi`/`.csi` | `gene_track_index_problem()` — table below |
| `coords` | `feature_coords.tsv`, `exon_coords.tsv` (in the gene set's own directory) | missing, or older than `genes.gff` |
| `search` | `trix/annotations.gff3.gz.ix` | missing, older than the `.gz`, not linked from the track config, or built with other attributes than `MOOP_JBROWSE_TEXT_INDEX_ATTRIBUTES` (`Name,ID,Alias`) |

Until 2026-10-21 only the track was checked. The Register/Re-prep button also asked for no
search index (`text_index: '0'`) and the web server could not run the JBrowse CLI (see
`docs/SELINUX_AND_HARDENING.md`), so **no gene set had a search index** and JBrowse's search box
found no gene by name — with every check green.

`gene_track_index_problem()` in `lib/jbrowse/gene_track_index.php` decides whether a track
needs work. Everything uses it: the dashboard count, the background rebuild, registration
itself, and the gene page (which reads a gene's model from the tabix copy only when it passes).

| Kind      | Meaning | What the user sees |
|-----------|---------|--------------------|
| `broken`  | `.gz` or index is empty, truncated or not BGZF (checked by its header and EOF block) | **No genes at all** on the track |
| `stale`   | `.gz` older than the GFF, or index older than the `.gz` | Old gene models / dead links |
| `missing` | No `.gz`, or no index | No genes (registration's compression step failed) |

Age alone is not enough. On 2026-10-13 a failed rebuild left six **0-byte** `.gz` files that
were *newer* than their GFFs, and the old age-only check called them fine.

## It rebuilds itself

Housekeeping's `jbrowse_indexes` task re-preps every gene set the rule flags: the manager's own
`prepareGeneSetForJBrowse()` (track + coordinate files, whichever are out of date), then the
search index if that is still out of date. A gene set counts as done only when the rule finds
nothing afterwards. It rebuilds — **only in the
background CLI run** (`scripts/run_housekeeping.php`, spawned from an admin page load at most
once an hour). Broken ones go first, then smallest first, for about five minutes per run; the
rest wait for the next run. A page request never re-compresses a GFF.

The build (`gene_track_build_index()`) is safe to interrupt or fail:

1. bgzip and tabix are resolved to absolute paths first (`moop_find_executable()`: PATH, then
   `/usr/local/bin`, `/usr/bin`). If either is missing, **nothing is touched**.
2. Output goes to a temp file beside the target, under `set -o pipefail`.
3. The temp `.gz` and its index must be complete BGZF, and tabix must succeed.
4. Only then are they renamed into place. A failure at any step leaves the old files as they were.

## Will I know? Where do I look?

- **Manage JBrowse** — each gene set in Assemblies & Tracks carries a badge for what is out of
  step ("track out of date", "coordinates out of date", "no search index"), checked live; hover
  for the reason. "Only rows needing attention" includes them.
- **Admin dashboard** — a "JBrowse gene sets need a Re-prep" card appears whenever
  anything is broken, stale or missing, or the last rebuild had failures. It lists the tracks,
  what the last run did, and each failure's reason. It is hidden when everything is current.
- **Rebuild now** on that card starts the background rebuild immediately instead of waiting for
  the next hourly run. Reload the page after a few minutes.
- **Run housekeeping now** (bottom of the dashboard) counts the indexes inside the request —
  which takes milliseconds and rebuilds nothing — and, if there is work, starts the background
  rebuild and says so.

Failure reasons are stored in `logs/.housekeeping_status.json` (`jbrowse_index_summary.last_rebuild.failed`)
because the background child's stderr goes to `/dev/null`.

## By hand

Run it **as the web user**, so the files stay writable by php-fpm:

    sudo -u apache php scripts/run_housekeeping.php --task=jbrowse_indexes

Then read the result:

    python3 -c 'import json; print(json.dumps(json.load(open("logs/.housekeeping_status.json"))["jbrowse_index_summary"], indent=1))'

Older versions of MOOP needed `env PATH=/usr/local/bin:$PATH` in front of that command, because
`sudo`'s `secure_path` omits `/usr/local/bin`. The tools are now resolved explicitly, so it is no
longer needed — but if the dashboard reports "bgzip and tabix not found", install htslib
(`setup-check.php` shows how for your OS).

To force one gene set regardless of state, use **Manage JBrowse → Assemblies & Tracks → rebuild
(wrench)** on its gene track, which calls `admin/api/jbrowse_reprep_gff.php`.
