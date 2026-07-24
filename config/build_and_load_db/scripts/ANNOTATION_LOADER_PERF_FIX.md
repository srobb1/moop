# Fix: annotation loader could hang forever on cyclic parent chains (+ was slow)

## Real root cause (revised after testing — this is the important part)

Original theory was "cache rebuild scales with DB size" — that's real and
worth fixing, but it turned out **not** to be why kc3/kc4 actually died.
The real cause is a genuine infinite loop:

`data_loaders/load_annotations_sqlite.pl`'s `find_annotation_target()` walks
`parent_feature_id` up from a protein to its mRNA, with **no cycle guard**:

```perl
while (defined $cur) {
    ...
    $cur = $parent_cache{$cur};
}
```

For pvel.**kc4**, `analysis_parsers/parse_transcript2gene_to_MOOP_TSV.pl`'s
`resolve_parent()` assumes TransDecoder-style protein IDs
(`transcript_id.p<N>`) and strips that suffix to find the parent transcript.
kc4's protein IDs have **no `.pN` suffix at all** — they're identical to
their transcript ID. So `resolve_parent()` resolves every protein's parent
to *itself*. `data_loaders/load_genes_sqlite.pl` then does a get-or-insert
by `feature_uniquename` (UNIQUE indexed), so the protein row and mRNA row
(same uniquename) collapse into **one row whose `parent_feature_id` is its
own `feature_id`**. Verified in the actual data:

```
sqlite> SELECT COUNT(*) FROM feature WHERE feature_id = parent_feature_id;
29555   -- every single protein in kc4
```

`find_annotation_target()` hits this self-reference on essentially the
first annotation row it processes and spins forever. That's why kc4's log
went silent right after "Loading EGGNOG Orthologs" and never produced
another line in 4 hours. kc3 has normal `.p1`-suffixed protein IDs (0
collisions) — it has no data bug of its own; it just never got the
`organism.sqlite` flock because kc4 (or kc1) was holding it, and kc4 never
let go because it was stuck in the infinite loop.

The secondary, still-real issue: `load_annotations_sqlite.pl` was invoked
once per annotation file (~35-46 per geneset) and rebuilt its full
`feature`/`feature_annotation` cache from the whole (shared, per-organism)
DB every single time. Fixed as part of this change too — real speedup, just
not the thing that was actually killing the jobs.

## What was changed

1. **`data_loaders/load_annotations_sqlite.pl`**
   - `find_annotation_target()` now tracks visited feature ids and breaks
     out (attaching the annotation to the starting feature, with a
     `warn`) instead of looping forever on a cycle. Memoized per starting
     feature id so a corrupted dataset doesn't warn once per annotation row.
   - Accepts multiple annotation files per invocation; `feature` /
     `feature_annotation` caches are built once per run, not once per file.
   - Added `POSIX::_exit(0)` at the very end after all DB work is committed
     and disconnected — large caches (100k+ features, 500k+
     feature_annotation rows) were segfaulting during Perl's normal global
     destruction on process exit (reproduced independently of the other
     changes; only shows up at real scale, not on small test files).
     `_exit()` skips that teardown entirely; nothing after "Done." does any
     more work anyway.

2. **`scripts/setup_new_moopdb_and_load_data.sh`** — `load_files()` now
   passes every file matching a pattern to a single perl invocation instead
   of looping one invocation per file.

## Verification performed (against real kc3/kc4 data, scratch DB copies — the
   live organism.sqlite was never touched)

- `perl -c` on both files: clean.
- kc3 (clean data) homolog files (17 files, 583,571 feature_annotation
  rows): OLD per-file loop took 30s; NEW batched invocation took 6.5s.
  Full content diff (feature/annotation/score joined and sorted) between
  the two: **zero differences**.
- kc4's full real annotation set (46 files, the exact same files its
  production run was loading when it died — homologs, iprscan, protnlm,
  eggnog orthologs, EggNOG2GO reduced; 945,211 annotation rows): **used to
  hang forever (verified: >10 min on a single 2032-line file alone, would
  never have finished). Now completes in 10 seconds, exit 0, no crash.**
  26,466 distinct corrupted proteins hit the cycle guard and got a warning;
  all other rows loaded normally.

## Known follow-up NOT fixed here (needs a product decision, out of scope
   for this pass)

`parse_transcript2gene_to_MOOP_TSV.pl`'s `resolve_parent()` produces
a duplicate/self-colliding uniquename whenever a T2G-path geneset's protein
IDs aren't suffixed with `.p<N>` relative to their transcript ID (true for
kc4; not true for kc1/kc3). Right now that means kc4's mRNA-level features
got silently overwritten/merged with their protein counterpart in the DB —
kc4 protein annotations now load fine (thanks to the cycle guard) but there
is no separate mRNA feature row for those genes, and the collision needs a
real fix in the feature-table generator (something has to end up with a
disambiguated uniquename — CDS-vs-protein collisions already get a `:cds`
suffix elsewhere in that script as precedent). Didn't attempt this because
it touches what ID scheme downstream consumers (the moop webapp, BLAST
lookups against transcript.nt.fa) expect, which isn't visible from this
repo. Worth a follow-up conversation before touching it.

## Status

- [x] load_annotations_sqlite.pl: cycle guard + multi-file batching + clean exit
- [x] setup_new_moopdb_and_load_data.sh: batched file loading
- [x] syntax-checked
- [x] functionally verified against scratch DB copies (correctness + no hang + no crash)
- [ ] kc3 / kc4 resubmitted to SLURM (not done yet — next step)
- [ ] follow-up decision on the protein/transcript ID collision in kc4's feature table (separate task)
