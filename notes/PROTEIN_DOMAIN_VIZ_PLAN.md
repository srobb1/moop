# Protein domain maps on gene pages — storage, retrieval, rendering

Status: **plan, not built.** Raised by the user 2026-07-24: *"one day, id like to have
protein domain map images on the gene pages, but i dont store coords. i dont want db
bloat. any crafty way to handle this?"*

The instinct is right, and this follows the precedent already set by
`{gene_set}/feature_coords.tsv` and CLAUDE.md §9: **bulk, per-feature data that is
regenerable from source files does not belong in the database.**

---

## 1. Storage: pick by whether "find all genes with domain X" is a goal

This is the deciding question, and it should be answered before anything is built.

### Option A — flat TSV per gene set (display only)

`{gene_set}/protein_domains.tsv`, generated from InterProScan/Pfam output, which is already
TSV:

```
protein_id	source	accession	name	start	end	evalue
NV2t000123.1:pep	Pfam	PF00069	Protein kinase domain	 54	310	3.2e-45
NV2t000123.1:pep	Pfam	PF00433	Protein kinase C terminal	340	402	1.1e-08
```

- Costs nothing when unused, regenerates from source, never competes for the databases'
  page cache — which is the scarce resource (see `notes/QUERY_PERFORMANCE.md`).
- **The trap: a linear scan to find one protein.** A gene page needs exactly one protein's
  rows. Scanning a multi-hundred-MB TSV per page view would be far worse than the database.

**The fix is a sidecar offset index.** Sort the TSV by `protein_id`, then write
`protein_domains.idx` mapping id → byte offset + length:

```
NV2t000123.1:pep	4823910	218
NV2t000124.1:pep	4824128	109
```

Read the page's protein with `fseek()` + `fread()` of a couple hundred bytes. The index
itself is small enough to binary-search on disk, or to load once and cache. This is the
same shape as the existing coords loading, so it reuses a proven pattern.

### Option B — separate `domains.sqlite` per gene set (searchable)

If domains should ever be **searchable** ("show me every gene with a kinase domain",
faceting on Pfam accession, MOOPmart export by domain), that is an index problem, and an
index is what a database is for. A scan cannot do it acceptably.

Keeping it in its **own file** rather than in `organism.sqlite` preserves the whole point:
it never inflates the organism database, and it is not paged in when nobody asks for
domains. This is exactly the split already chosen for `expression.sqlite` in
`notes/EXPRESSION_EXPLORER_PLAN.md`.

```sql
CREATE TABLE domain_hit (
    protein_uniquename TEXT NOT NULL,
    source             TEXT NOT NULL,   -- Pfam, SMART, PROSITE...
    accession          TEXT NOT NULL,
    name               TEXT,
    start_pos          INTEGER NOT NULL,
    end_pos            INTEGER NOT NULL,
    evalue             REAL              -- nullable; see the score lesson below
);
CREATE INDEX domain_hit_protein_idx   ON domain_hit (protein_uniquename);
CREATE INDEX domain_hit_accession_idx ON domain_hit (accession);
```

**Recommendation: B.** "Which genes have this domain" is a question users ask almost
immediately once they can see domains at all, and retrofitting search onto Option A means
building the index anyway. B costs one small extra file per gene set and keeps
`organism.sqlite` untouched. Take A only if this is firmly display-only.

### What NOT to do

- Do not add domain coordinates as columns on `feature`. That is the bloat being avoided,
  and it works against the small-and-rebuildable design (CLAUDE.md §9).
- Do not store rendered **images**. See §3.
- Do not load `-`, `NA` or an empty field into `evalue` as `0.0`. Zero is the strongest
  possible e-value, so score-less hits would sort as the most significant. Load them
  `NULL`. (This exact bug is live in `feature_annotation.score` — 599,447 rows holding the
  string `"-"`, fixed in the schema on 2026-07-24.)

---

## 2. Retrieval

One function, mirroring how coords are already loaded, so there is one door:

```php
// lib/domain_functions.php
function getProteinDomains(string $organism, string $assembly,
                           string $gene_set, array $protein_ids): array
```

- Resolve the gene set's directory through `ConfigManager`, never a hardcoded path.
- Take **all** of a gene's proteins in one call — a gene page renders every isoform, so
  per-protein calls would be N queries for one page.
- Return `[protein_id => [ {source, accession, name, start, end, evalue}, ... ]]`, sorted
  by `start`.
- Return `[]` when the gene set has no domain file. **Absence is normal, not an error** —
  most gene sets will not have domains at first, and the card must simply not render.
- Generate proactively at registration, mtime-smart, exactly like
  `feature_coords.tsv` (see the BLAST-linkout work). **No lazy fallback** — the same
  decision already taken for coords.

Protein length is needed to scale the track. It is not in the database; take it from the
gene set's `protein.aa.fa` (already indexed for BLAST) or carry it as a column in the
domain file, which is cheaper than opening the FASTA per page view.

---

## 3. Rendering: inline SVG, client-side — not images

Generate **no image files at all**. The page emits SVG from the JSON the endpoint returns:

- Nothing to store, regenerate, cache-bust, or clean up — and no new writable directory,
  which on this host would mean another SELinux label and another allowlist entry that
  silently fails when forgotten (CLAUDE.md §11).
- Crisp at any zoom and on any display, unlike a raster image.
- Hover gives the domain name, accession, coordinates and e-value for free; clicking can
  link out to Pfam/InterPro via the existing linkout mechanism.
- Accessible: real DOM nodes, so the domain list is readable by screen readers and
  selectable as text. A PNG is opaque.

**Layout:** one horizontal bar the length of the protein, with an amino-acid ruler; domain
rectangles positioned by `start`/`end`; overlapping hits stacked into rows rather than
drawn on top of each other. Colour by `source` (Pfam/SMART/PROSITE) using the teal-accent
palette, not one colour per domain — there are too many domains for colour to carry
identity, and the label already does.

Place it in the gene page's section-nav flow (`js/modules/parent-nav.js`) as its own
"Protein domains" section so it appears in the sidebar TOC.

---

## Sequencing

1. Decide A vs B (the "is it searchable" question).
2. Produce the domain file for **one** gene set; confirm IDs match `protein.aa.fa` with
   `scripts/check_sequence_id_match.sh` — an ID mismatch here fails silently, which is the
   defect that script exists to catch.
3. Build the retrieval function + endpoint; verify against a gene with several isoforms and
   a gene with no domains at all.
4. Render the SVG.
5. Only then generate across all gene sets.

Related: `notes/EXPRESSION_EXPLORER_PLAN.md` (same separate-file pattern),
`notes/QUERY_PERFORMANCE.md` (why cache footprint is the constraint),
`scripts/check_sequence_id_match.sh`.
