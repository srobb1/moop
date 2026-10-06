# Site session handoff — 2026-10-06 (site agent)

All of this is committed and pushed to main (last: `3184187b`). Pipeline-side work is in the
other agent's handoff (`notes/SESSION_HANDOFF_2026-10-06.md`).

## Shipped today

| Commit | What |
|---|---|
| f1dc457b | Gene page naming card: statements under the title, "Unnamed gene", evidence code stripped, Copy includes statements; protein length (overview + tree); Annotations section starts collapsed |
| 6c3679f3, 4b177581 | Score meanings: `lib/annotation_scores.php` + editable table on Manage Annotations (`metadata/annotation_scores.json`, defaults in `.json.example` — NOT yet saved by the admin, so defaults apply). Rules by source (ordered) + fallbacks by table (always last) |
| e8b17aab | Chips back under the title; sidebar highlights the gene on load (skip hidden sections in scrollspy) |
| dd097381 | `.fai` index is authoritative — no blastdbcmd to confirm "not found" (non-coding genes 0.49 s → 0.065 s); fallback kept for no-index case and now logs |
| 486f98b3 | nginx caching (`expires` only — never add_header) — DEPLOYED and verified on :80/:443 |
| 3184187b | Big genes (≥ 50 transcripts, `MOOP_BIG_GENE_TRANSCRIPTS`): list + on-demand cards, one-isoform diagram, FASTA downloads. Fixed the 500s on onun.kc3.gc000000 / pmor.kc3.gc000000 |

## Open — next session

1. **Banner images** — `pacificLampreyMouths_1200x300.jpg` is really 4800×1200, 1.9 MB; two PNG
   photos ~670 KB (incl. the clam's `cavemollusk-banner-1200x300.png`). Banners are picked AT
   RANDOM per page load, so any page can pull the 1.9 MB one. No image tools on the server and no
   sudo for me: either the user resizes and re-uploads, or `sudo dnf install ImageMagick`.
2. **Protein `Isoform` statement** — site side built and smoke-tested (`moop_resolve_naming_protein`),
   never seen on real data: Congeria has one protein per gene. Needs a multi-isoform organism reloaded.
3. **GO namespace** lost (score column is REAL); pipeline should put it in the description.
4. **Annotation-heavy normal pages**: Myotis 118665364 (11 isoforms × 169 identical annotations) is 2.3 MB.
5. **Chameleon MENDER set**: UBXN8 (CCA3g009686000.1) merges a retrotransposon; 360 "isoforms"
   are 91 transcript shapes × several ORFs; 39 of its 360 proteins are missing from protein.aa.fa.
   User decided: leave the MENDER set as is.
6. **Giant clusters** in planarian transcriptomes (189 genes ≥ 50 site-wide) — now render, but the
   clustering itself may be worth a look pipeline-side.

## Gotchas hit today (worth remembering)
- A top-level `const` is a global NAME but not `window.X` — `window.DataTableExportConfig` is always undefined.
- Rows on other DataTables pages are detached: read them via `dt.rows().nodes()`, never the document.
- A CLI test harness that `extract()`s every controller variable hides a variable the real page
  never passes in `$data` (the score Type dropdown shipped empty). Pass only the `$data` keys.
- Compare rendered pages with the random banner masked out (`images/banners/...` differs per load).
