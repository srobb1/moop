# Two annotation-loading defects (compute side, not MOOP code)

Found 2026-07-23 while looking at why a transcript page rendered an empty CDS annotation
section. **The parent page was working as intended** — it renders a CDS card because the
database genuinely says CDS features carry annotations in that gene set. The defects are
upstream, in how annotations are loaded.

Both are fixed on the compute server, alongside the RefSeq `cds.nt.fa` header work in
`scripts/check_sequence_id_match.sh`.

---

## 1. Annotations on mitochondrial CDS never bubble up to mRNA

MOOP's loader deliberately floats annotations up to the transcript at load time
(`find_annotation_target`) — analysis is protein-based, but annotations attach to the mRNA so
the whole site can assume one level. That is the design and it should stay.

It silently fails for mitochondrial features.

**Measured, Amphimedon queenslandica (`GCF_000090795.2` / `RS_2026_04`):**

| feature_type | features with annotations | annotations |
|---|---|---|
| mRNA | 23,134 | 854,848 |
| **cds** | **13** | **572** |

The 13 are a contiguous block of `YP_` accessions:

```
cds-YP_001031198.1 … cds-YP_001031210.1
```

`YP_` is NCBI's prefix for organelle/mitochondrial proteins. **Mitochondrial genes in a RefSeq
GFF go `gene -> CDS` with no mRNA in between** — they are not spliced, so no transcript feature
is emitted. `find_annotation_target` climbs looking for an mRNA, does not find one, and leaves
the annotation on the CDS.

So the bubble-up implicitly assumes an mRNA level always exists. It does not, for organelle
genomes.

### Why 13 rows matter

`getAnnotatedFeatureTypesInGeneSet()` computes, per gene set, which feature types carry
annotations — and `generateChildAnnotationCards()` skips child types not in that list. Thirteen
outliers out of 23,000 put `cds` in the list for the whole organism, so **every** transcript
page in Amphimedon renders an empty "No annotations loaded for this cds" card. Protein is not
in that list, which is why the page shows an empty CDS section and no protein section at all —
that asymmetry is data, not intent.

### The fix

On the loader: when climbing to the annotation target, if no mRNA ancestor exists, decide
deliberately what to do rather than defaulting to "leave it where it is". Options: attach to
the gene, or synthesise the transcript level for organelle genes. Attaching to the gene is
probably right — a mitochondrial gene has no isoforms, so the gene IS the unit.

**Do not fix this in the display layer.** Hiding empty child cards in
`generateChildAnnotationCards()` was considered and rejected: those 13 CDS features have 572
real annotations, and suppressing the card would hide them from the user entirely. The page is
reporting the data accurately.

---

## 2. Annotation source names are not trimmed — 17 duplicate pairs

**Measured, same organism:**

```
annotation_source rows            61
distinct after TRIM()             44
names with stray whitespace       17
```

Seventeen sources exist twice, differing only by a trailing space:

```
[Ensembl Homo sapiens ]  and  [Ensembl Homo sapiens]
[Ensembl Mus musculus ]  and  [Ensembl Mus musculus]
[Ensembl Danio rerio ]   and  [Ensembl Danio rerio]
…15 more, all "Ensembl <species>"
```

Each variant holds roughly half that source's annotations (10 and 10 in the sample checked).

### Why this one is user-visible and worth fixing first

`annotation_source_name` is not an internal key — it is **the thing users pick from**:

- **Annotation Search Step 3** lists the available annotation types/sources for the selected
  organisms. The user sees "Ensembl Homo sapiens" twice, with no way to tell them apart, and
  **selecting one silently searches only half the annotations**.
- Same list drives the Annotation Source Filter modal on the organism/assembly/group pages.
- MOOPmart's By-Annotation criteria dropdown uses the same names, so an export filtered on one
  variant quietly misses the other half.
- The names are matched as strings in the search path (`ans.annotation_source_name IN (...)`),
  so no amount of UI work fixes it.

This is squarely the "silent wrong answer" class: nothing errors, the user just gets fewer
results than they asked for and cannot tell.

### The fix

`TRIM()` the source name at load. Then de-duplicate the existing rows: merge each pair into one
`annotation_source_id` and repoint `annotation.annotation_source_id`. Worth checking the other
name columns for the same treatment while in there.

A guard is cheap and would have caught it:

```sql
SELECT COUNT(*) FROM annotation_source
 WHERE annotation_source_name != TRIM(annotation_source_name);   -- expect 0
```

---

## Scope check still to do

Both were measured on Amphimedon only. A site-wide sweep timed out — 85 databases, 66 GB
against ~12 GB of page cache, so it is disk-bound cold (see `notes/QUERY_PERFORMANCE.md`). Run
it per organism, or overnight:

```sql
-- non-mRNA annotation carriers
SELECT f.feature_type, COUNT(*) FROM feature f
  JOIN feature_annotation fa ON fa.feature_id = f.feature_id
 GROUP BY f.feature_type;

-- untrimmed source names
SELECT COUNT(*) FROM annotation_source
 WHERE annotation_source_name != TRIM(annotation_source_name);
```

Related: `scripts/check_sequence_id_match.sh` (the RefSeq CDS FASTA mismatch, same build
pipeline), `notes/SEARCH_FEATURE_LEVEL_INCONSISTENCY.md`, and the memory note "annotations
attach to mRNA".
