# What JBrowse 4.3.0 unlocked — things to consider now

Written 2026-07-22, immediately after upgrading the bundled browser 4.1.3 → 4.3.0
(commit c58e645). These are opportunities, not commitments. **Nothing here should be
started before launch** — the browser is a high-traffic surface and the upgrade itself is
still bedding in.

Release notes reviewed: v4.1.11, v4.1.14, v4.1.15, v4.2.0, v4.2.1, v4.3.0.

---

## 1. Searchable metadata in the faceted track selector (v4.1.11) — wanted

> *"Allow searching metadata in faceted track selector"* (PR #5477)

With **1,243 tracks** this is the single most valuable item in the set. Today a user
scrolls; with this they filter by whatever metadata the track carries.

**The catch, and it is the interesting part: this feature is only as good as our track
metadata, and our track metadata is thin.** That makes it the concrete payoff for the
metadata-contract idea already recorded in the tracks-server curation notes — pipeline,
normalization, aligner, strand provenance. Until now that was "would be nice"; now there is
a UI that renders it.

Sequence, if we do it:
1. Decide the metadata contract (which fields, controlled vocabularies).
2. Backfill it in the track sheets — that is the real work, and it is human work.
3. Emit it into the track configs; the selector picks it up.

Related: **v4.2.0 supertrack/folder concept with faceted browsing** (PR #5515) — categories
and folders in the selector. Same dependency: it is only useful if tracks are categorised
meaningfully. Consider the two together, not separately.

---

## 2. `&highlight=` (v4.3.0) — wanted, and cheap

> *"Improved/unified behavior of the `&highlight=` matching the bookmark highlight behavior"*

We already build `&loc=` URLs in six places. Adding `&highlight=` would mark the region of
interest rather than merely centring on it. Best fits:

- **BLAST linkouts** — highlight the actual HSP span, so the hit is visibly marked instead
  of the user landing near it and hunting.
- **The gene page** browser link — highlight the gene body.

This is a small, self-contained change to URL construction
(`lib/blast_results_visualizer.php`, `tools/pages/parent.php`) with no data-model
implications. Verify the parameter's exact shape against the deployed build before shipping
— an unknown key is not ignored by JBrowse, it is fatal to the whole config.

---

## 3. Can we drop `feature_coords.tsv`? — **partly, and not the way it first looks**

The hope was that v4.2.1's *"Fix nav to search string via `&loc=`"* means JBrowse can
resolve a gene name itself, so we would no longer need to look up coordinates before
linking. That is true **for one of the three consumers only.** Checked against the tree:

| Consumer | What it takes from the file | Could JBrowse replace it? |
|---|---|---|
| JBrowse linkout (`blast_results_visualizer.php:646`) | coordinates, to build `&loc=` | **Possibly** — pass a name and let the trix text-index resolve it |
| Gene-page linkout (`:936`) | `gene_id` — the hit-ID→gene mapping, *not* coordinates | No. This is a MOOP fact about our own pages |
| MOOPmart (`moopmart_functions.php:314`) | coordinates, for range **filtering** | No. Server-side filtering; JBrowse is not involved |

So the file cannot simply go away.

### DECIDED 2026-07-22: keep the file. Coordinates stay OUT of the database.

`lib/moopmart_functions.php:654` states it plainly — *"coords are in feature_coords.tsv, not
the DB"*. That is **deliberate, not an oversight**, and the obvious-looking "improvement" of
moving coordinates into SQLite is the wrong direction here.

MOOP's schema optimises for many small per-organism databases that load quickly and can be
rebuilt independently when a gene set changes. Keeping them small is the point, and it is a
considered tradeoff against the expressiveness a larger, more normalised schema would give.

Coordinates are **bulk, per-feature, regenerable-from-GFF positional data** — precisely the
category that grows a database fastest while adding nothing a flat file cannot serve just as
well. A TSV beside the gene set is the cheaper home: it rebuilds from the GFF, costs nothing
when unused, and keeps `organism.sqlite` fast to load.

**So do not propose "just add a coordinates column" as a simplification.** It reads like one
and is not. The staleness-vs-GFF failure mode is a real cost, and the right mitigation is
regeneration discipline (already: proactive build at registration + a rebuild button on
Manage BLAST Linkouts), not schema growth.

⚠️ If the JBrowse linkout **does** move to name-based `&loc=`, it gains a dependency on the
`jbrowse text-index` (trix) being built and current for that assembly. Today a missing index
costs feature-name search; afterwards it would also break BLAST linkouts. That is a real
coupling, and an argument for keeping coordinate-based links even if names become possible.

---

## 4. Smaller items worth knowing

| Version | Item | Relevance |
|---|---|---|
| v4.1.15 | **HTTP 416 fix** — replaced http-range-fetcher | Directly ours: range requests are how JBrowse pulls BigWig/BAM slices from the tracks server. Already fixed by upgrading. |
| v4.1.15 | refname aliases in `displayedRegions` | We auto-detect aliases from `organism.sqlite`, so this was in our path. |
| v4.1.11 | PNG export in the Export SVG dialog | Free user-facing win, no work. |
| v4.1.11 | hierarchical tree sidebar for multiwiggle clusterings | Relevant to the Expression Explorer plan. |
| v4.1.14 | disable the gene heuristic per-config for BED/BigBed | Useful if a BED track is ever mis-rendered as genes. |
| v4.3.0 | bedMethyl auto-loads as multiquantitative | Only if we take on methylation data. |
| v4.3.0 | IGV-standard discordant pair colouring | Cosmetic change users may notice on alignment tracks. |

## 5. Checked and NOT a problem

**v4.3.0 changed multi-level synteny specification from URL query params** to a
two-dimensional `string[][]`. We are unaffected: MOOP specifies synteny views through
`defaultSession.views` in the generated config
(`lib/jbrowse/config_functions.php:231`), never through URL params. Verified 2026-07-22.

## 6. On the horizon

The project is mid-migration to **WebGL/WebGPU rendering** (PR #5468) — smooth zooming,
better performance — and is explicitly recruiting model-organism-database admins to beta
test. That is aimed at sites like this one. Worth a reply *after* launch, not before.

---

Related: [JBROWSE_UPSTREAM_PLUGIN_IDEA.md](JBROWSE_UPSTREAM_PLUGIN_IDEA.md),
[Upgrading JBrowse2](../docs/JBrowse2/UPGRADING.md),
[BLAST linkouts](../docs/BLAST_LINKOUTS.md)
