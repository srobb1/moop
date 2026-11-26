# Request for compute: ship WHY an ID is not linked, and WHERE it aligned (2026-11-19)

Follows `notes/archive/ANATOMY_IDS_FROM_COMPUTE_2026-11-19.md`. Today an anatomy (PAGE) record whose ID
reaches no gene shows a bare "–" in the gene column, which reads as broken. You already know the
reason for every such ID, and where most of them aligned; the crosswalk file just doesn't carry
it, because it keeps only the IDs that reached a gene.

## What the web wants to show
In the gene column, instead of "–":

- `dd_Smed_v4_10005_0_1`  **no gene ⓘ** · *view in genome browser*
  hover: "Aligns to the genome (2_h1:230,846,233) where no gene is annotated."
- `SmedASXL_000017`  **no gene ⓘ** — hover: "Did not align to the genome."
- `OX_Smed_1.0.00005`  **no gene ⓘ** — hover: "Not in the reference data."

The genome-browser link opens schMedS3h1 at that span with the transcript drawn as a highlighted
feature (the same way BLAST hits are shown: `jbrowse2.php?...&loc=...&sessionTracks=...`).
A real sequence sitting where no gene is annotated is worth seeing — it may be a missing gene.

## What we need from compute
One more table **inside `crosswalk.sqlite`** (so it ships with the file, one copy, no new path):

```
CREATE TABLE unlinked (
  accession     TEXT NOT NULL,     -- as in the source
  accession_lc  TEXT NOT NULL,
  source        TEXT NOT NULL,     -- same source names as map.source
  reason        TEXT NOT NULL,     -- one of the values below
  assembly      TEXT,              -- MOOP assembly name of the alignment (e.g. schMedS3h1), NULL if none
  seqid         TEXT,              -- as in that assembly's FASTA/JBrowse (e.g. 3_h1)
  start         INTEGER,           -- 1-based, inclusive
  end           INTEGER,
  strand        TEXT               -- '+' / '-' / NULL
);
CREATE INDEX u_acc ON unlinked (accession_lc, source);
```

`reason` values — the columns of your "what happened to each input transcript" table, plus the
never-in-input case:

| reason | meaning (hover text the web will show) |
|---|---|
| `no_gene_at_locus` | aligns to the genome where no gene is annotated |
| `no_cds` | aligns over exons/UTR only, no coding sequence |
| `cds_overlap_short` | overlaps a gene's coding sequence by less than 100 bp |
| `did_not_align` | did not align to the genome |
| `not_in_input` | not in the reference data used to build the crosswalk |

Questions for you:
1. **One alignment per ID** is enough for the link — the best one, on S3h1 if it aligned there.
   OK, or do some IDs need more than one row (multi-mappers)?
2. **`not_in_input` needs the list of IDs you were never given** — e.g. the PAGE IDs. If that is
   awkward on your side, skip it: the web can call anything that is in neither `map` nor
   `unlinked` "not in the reference data" itself.
3. **Contigs:** the same table should key on source + ID (Contig1038 is two different sequences).
4. **Kao 2013's 429 unlinked `OX_Smed_1.0.N` IDs** (list: `notes/archive/ANATOMY_UNMAPPED_IDS_2026-11-19.tsv`,
   PMID 24238224): how many are numbers MISSING from `ox_Smed_v2.nt`, and how many are in it but
   aligned with no gene there? Sofia will hunt down only the missing ones (and whether v2 dropped
   them on purpose).

## Web side, once it arrives (not before)
`moop_crosswalk_gene_links()` returns the reason + location for an unlinked ID; the anatomy table
(api/anatomy_records.php) renders the marker, hover and link. The same data can serve the Rosetta
Stone translator and the menu-bar search's "maps to" hint. Until then the web shows a generic
"no gene ⓘ" with: "This paper's ID does not map to a gene in the current reference genomes."
