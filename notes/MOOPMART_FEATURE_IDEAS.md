# MOOPmart feature ideas

Ideas raised while polishing MOOPmart. Not built.

---

## "Select all features in the selected organisms" (user, 2026-07-23)

In the **By Feature IDs** section of Step 2, add a control — a button or checkbox —
that builds the list from **every** feature in the selected organisms, without the user
having to paste any IDs. "Select all features in selected organisms."

**Show a gene count next to it**, so the user knows how many features they are asking
for before they run it — e.g. "Select all features (12,431)".

### Why the count matters here specifically

This is the same huge-query risk the organism Select-All modal already guards
(none-by-default + a "this can take a while" warning). "All features × all organisms"
with no filter is the largest query MOOPmart can produce. So this control must not make
that *too* frictionless:

- The count is not just informational — it is the brake. A user who sees "(2,300,000)"
  will think twice; one who sees "(430)" proceeds happily.
- Consider mirroring the Select-All-organisms pattern: if the count is large, confirm
  ("this will export N features across M organisms and can take a while").
- The count depends on the current organism selection, so it recomputes on selection
  change — same trigger as the annotation availability counts already wired in
  `js/modules/moopmart.js` (`refreshAnnotationCounts`), and could reuse a similar
  lightweight endpoint that returns a feature total for the selected organisms.

### Interaction with the AND filter model

Step 2 sections combine with AND. "All features" is really "no By-Feature-IDs
constraint" — so if the user also fills another section (By Annotation, By Location),
"all features" should not override it; the other filters still apply. Cleanest framing:
the checkbox means "do not filter by ID" (the default today), and the count shows how
many features the *rest* of the current criteria would return — turning the count into a
live preview-size readout for the whole Step 2, not just this section. Decide whether it
counts "all features" or "features matching the other filled sections" before building;
the second is more useful and more honest about query size.

Related: the organism Select-All warning (`tools/pages/moopmart.php` #mm-select-all-modal),
`notes/QUERY_PERFORMANCE.md` (query cost), the annotation-count wiring in
`js/modules/moopmart.js`.

---

## Make MOOPmart's sequence types agnostic too (user, 2026-07-23 — "eventually, not now")

The feature-expansion side of MOOPmart is already type-agnostic as of 2026-07-23: its FASTA
export now calls `expandFeaturesToAllSequenceTypes()` (a pure parent/child graph walk) instead
of `buildTypedIdsForGenes()`, which hardcoded gene -> mRNA -> CDS -> protein as three
sequential queries. Verified equivalent on 25 genes: 87 features either way, zero difference.

**What is still type-coupled is the OUTPUT SELECTOR** — `$fasta_mode` and the
`sequence_types` config:

- `api/moopmart_export.php` splits on `$genomic_modes` (gene / upstream / downstream / exons,
  handled by `moopmartStreamGenomicFasta`) versus the pre-built FASTA modes
  (protein / transcript / cds), and then writes exactly one of them:
  `$extract_result['content'][$fasta_mode]`.
- `_fasta_key_for_type()` in `lib/extract_search_helpers.php` is the remaining hardcoded map
  from DB `feature_type` to a `sequence_types` config key: mRNA|transcript -> transcript,
  CDS|cds -> cds, protein|polypeptide -> protein. It returns null for anything else, which is
  what makes "gene" and "exon" harmless to include in an expansion.

So a new level in the hierarchy would be walked correctly today, but would have no FASTA file
and no picker entry. Making that agnostic means driving the mode list from `sequence_types`
(admin config) plus what the gene-set directory actually contains, rather than from a literal
list in code — i.e. the picker becomes "which of the sequence files this gene set has do you
want", and `_fasta_key_for_type()` becomes a lookup into the same config instead of a static
array.

### The five hardcoded sites (mapped 2026-07-23)

| # | Where | What is literal |
|---|---|---|
| 1 | `lib/extract_search_helpers.php` `_fasta_key_for_type()` | static map: `mRNA\|transcript -> transcript`, `CDS\|cds -> cds`, `protein\|polypeptide -> protein`; **null for anything else** |
| 2 | `api/moopmart_export.php:36` | `$valid_fasta_modes = ['gene','upstream','downstream','exons','protein','transcript','cds']` |
| 3 | `api/moopmart_export.php:317` | `$genomic_modes = ['gene','upstream','downstream','exons']` |
| 4 | `tools/pages/moopmart.php:706` | the picker itself: `['gene'=>'Genomic','transcript'=>'mRNA','cds'=>'CDS','protein'=>'Protein']` |
| 5 | `tools/pages/moopmart.php:286` | prose: "Paste gene, mRNA or protein IDs" |

Site 1 is the load-bearing one. It returns null for unrecognised types, which is *why* a new
level in the hierarchy is walked correctly by the expansion and then silently produces no
sequence: it has no config key, so it never gets a bucket.

### What the config would have to gain

`sequence_types` already declares the file patterns and is the natural source of truth:

```
protein    pattern=protein.aa.fa
transcript pattern=transcript.nt.fa
cds        pattern=cds.nt.fa
genome     pattern=genome.fa
```

What it does NOT declare is **which database feature_types feed each entry** — that mapping
lives only in `_fasta_key_for_type()`. So the change is to move it into the config:

```php
'transcript' => ['pattern' => 'transcript.nt.fa', 'feature_types' => ['mRNA', 'transcript']],
'cds'        => ['pattern' => 'cds.nt.fa',        'feature_types' => ['CDS', 'cds']],
'protein'    => ['pattern' => 'protein.aa.fa',    'feature_types' => ['protein', 'polypeptide']],
```

Then `_fasta_key_for_type()` becomes a lookup into that, sites 2 and 4 derive from
`array_keys($sequence_types)`, and adding a sequence type is a config edit rather than five
code edits. An admin adding a type to the config would get a working picker entry.

### One distinction to KEEP

`$genomic_modes` (gene / upstream / downstream / exons) are **computed** from the genome FASTA
plus the GFF by `moopmartStreamGenomicFasta()`, not read from a per-feature FASTA file. That is
a genuinely different mechanism, not an oversight — so it should stay a distinction, just a
*declared* one (e.g. a `source: computed|file` key per entry) rather than a literal list in
two places.

### Sequencing

Do site 1 first and alone — it is the one with behavioural reach, and
`scripts/check_sequence_id_match.sh` can verify no gene set changed which types it resolves.
Sites 2-5 are presentation and follow safely once the map is config-driven.

Worth pairing with `scripts/check_sequence_id_match.sh`, which already reports, per gene set
and per type, whether a FASTA's keys match the database — the same inventory the picker would
need.


---

## Merged: Cross-Annotation Search Feature Plan

*(Was `notes/CROSS_ANNOTATION_SEARCH_PLAN.md`, merged here 2026-11-25; the separate file was deleted the same day -- old copy: `git show c8ddce1c:notes/archive/<name>`.)*

**Date:** January 27, 2026  
**Status:** Planning Phase

### Problem Statement

Currently, MOOP cannot search for sequences that have annotations from MULTIPLE different sources (e.g., find genes that have BOTH a Pfam domain AND a Gene Ontology term). The search looks within individual annotation fields, so results must have all search terms in the same field.

**Example that fails:**
- User selects both "Gene Ontology" and "Pfam" sources
- Searches for: `PF00055 GO:0005886`
- Expected: Find sequences with PF00055 annotation AND GO:0005886 annotation
- Actual: No results (because it looks for both in same field)
- Sequence that should match: `ACA1_PVKU01000001.1_000004.1` (has both annotations, but in separate fields)

### Solution Options

#### Option 1: Simple AND Logic (Easiest)
**Concept:** When multiple annotation sources are selected, create an AND query instead of combining into one field.

**How it would work:**
- User searches for `protein kinase` with both "Gene Ontology" and "Pfam" selected
- Query: Find sequences with (`GO annotation contains "protein kinase"`) AND (`Pfam annotation contains "protein kinase"`)
- Would find sequences that have the term in either annotation type

**Pros:**
- Relatively simple to implement
- Works with current search infrastructure
- User-friendly

**Cons:**
- Still limited (needs same term in both)
- Doesn't allow independent searches (GO ID + Pfam ID)

#### Option 2: Advanced Multi-Source Search Tool (Recommended)
**Concept:** Create a dedicated "Advanced Multi-Source Search" tool that allows separate queries for each annotation source.

**Architecture:**
```
/data/moop/tools/multi_source_search.php  (entry point)
├── /tools/pages/multi_source_search.php  (UI template)
├── /js/multi_source_search.js           (search logic)
└── /lib/multi_source_queries.php        (database queries)
```

**Features:**
1. **Query Builder UI:**
   - Multiple rows, each for a different annotation source
   - User selects: Source (GO, Pfam, InterPro, etc.) + Search term + Logical operator (AND/OR)
   - Can add/remove rows dynamically
   - Example:
     ```
     [Gene Ontology] [contains] [kinase] [AND]
     [Pfam] [contains] [PF00055] [OR]
     [InterPro] [contains] [domain]
     ```

2. **Database Logic:**
   - Run separate queries for each annotation source
   - Combine results based on logical operators
   - Handle AND/OR/NOT operations on result sets in PHP
   - Return sequences matching the criteria

3. **Result Display:**
   - Show which annotations matched for each sequence
   - Highlight which criteria were satisfied
   - Allow filtering/sorting by number of matches

**Pros:**
- Most flexible and powerful
- Allows independent searches by annotation ID
- Can handle complex boolean logic
- Future-proof for additional features

**Cons:**
- More complex to implement
- Potentially slower for large result sets
- Need to process results in PHP instead of pure SQL

#### Option 3: Hybrid Approach (Best Balance)
**Concept:** Keep simple searches as they are, but add a "Multi-Source Query" option.

**Implementation:**
1. Keep existing search tool unchanged
2. Add new "Advanced Multi-Source Search" tool
3. From search results, offer "Refine with additional sources" option
4. User can further filter/combine results

**Workflow:**
1. Search for `kinase` in Gene Ontology → Get 500 results
2. Click "Refine with another source"
3. Add filter: `Pfam contains "PF00055"`
4. Results narrowed to sequences with both

### Recommended Approach: Option 2 (Advanced Multi-Source Search Tool)

#### Implementation Plan

**Phase 1: Database Query Layer**
- Create `buildMultiSourceQuery()` function
- Handle separate queries for each source
- Implement AND/OR/NOT logic on result sets
- Cache common queries for performance

**Phase 2: Backend**
- Create `/tools/multi_source_search.php` entry point
- Handle form submission
- Route to appropriate queries
- Format results

**Phase 3: Frontend**
- Create `/tools/pages/multi_source_search.php` UI
- Dynamic query builder (add/remove rows)
- Source selector dropdown
- Operator selection (AND/OR/NOT)
- Results display with annotation highlighting

**Phase 4: Integration**
- Add menu item for "Advanced Multi-Source Search"
- Update help documentation
- Add examples to help pages
- Test with real annotation data

#### Database Query Logic Pseudocode

```php
// For: (GO contains "kinase") AND (Pfam contains "PF00")
$queries = [
    'GO' => "SELECT feature_id FROM features WHERE annotation_source='GO' AND annotation LIKE '%kinase%'",
    'Pfam' => "SELECT feature_id FROM features WHERE annotation_source='Pfam' AND annotation LIKE '%PF00%'"
];

$results_go = executeQuery($queries['GO']);
$results_pfam = executeQuery($queries['Pfam']);

// AND logic: Find common IDs
$intersection = array_intersect($results_go, $results_pfam);

// Return features with intersection IDs
$final_results = fetchFeaturesByIds($intersection);
```

#### Estimated Effort
- **Phase 1 (Database):** 2-3 hours
- **Phase 2 (Backend):** 2-3 hours
- **Phase 3 (Frontend):** 4-5 hours
- **Phase 4 (Integration & Testing):** 2-3 hours
- **Total:** 10-14 hours

#### Performance Considerations
- Result sets could be large (thousands of sequences)
- AND queries will reduce results naturally
- Consider limiting results per source (e.g., max 5000 per query)
- Cache frequently used queries
- Consider pagination for large result sets

#### Future Enhancements
1. Save/reuse common multi-source searches
2. Export result combinations
3. Visual Venn diagrams showing overlap
4. Timeline/version-aware queries (old vs new annotations)
5. Probability scoring based on annotation confidence

### Next Steps
1. Get user feedback on preferred approach
2. Review database performance implications
3. Create UI mockup for query builder
4. Start Phase 1 implementation
5. User testing and iteration

---

**Related Issue:** Cross-annotation searches currently not possible  
**Affected Users:** Researchers needing complex annotation queries  
**Priority:** Medium (useful for advanced researchers, not blocking basic functionality)
