# Search results depend on WHICH LEVEL of the hierarchy your term matched

Status: **not fixed. Deliberately deferred 2026-07-23** — this is a change to search semantics
across six pages and it is a design decision, not a defect to quietly patch.

User's framing, which is the clearest statement of the problem:

> "it is not going to consistently return the same results if you start with different levels
> of children, that is not good"

Exactly right. The same biological gene can produce one row, three rows, or four, depending on
nothing more than which level of the gene model the search term happened to match.

---

## The three search paths do NOT agree

`tools/annotation_search_ajax.php` picks one of three queries in `lib/database_queries.php`.
**None of them filters on `feature_type`** — every one merely SELECTs it. Any consistency is a
property of the DATA, not of the query.

| path | function | what it returns per gene |
|---|---|---|
| annotation search (the normal one) | `searchFeaturesAndAnnotations()` | **mRNA only** |
| feature-ID search | `searchFeaturesByUniquenameForSearch()` | **mRNA + CDS + protein** |
| name/description only | `searchFeaturesByNameDescription()` | **gene + mRNA + CDS + protein** |

### Why the annotation path looks "carefully crafted" — and is, but not here

Measured on Nematostella (`GCA_033964005.1` / `NV2`):

```
feature_types that actually carry annotations:
    mRNA   46656
    (nothing else)
```

Annotations exist on mRNA and nowhere else, because the LOADER floats them up to the
transcript at load time (`find_annotation_target`; see the note "annotations attach to mRNA").
So the annotation query returns mRNA because mRNA is all there is to return — not because
anything in the search layer says so. Remove that loader behaviour and this path would
immediately behave like the other two.

**There is no `WHERE feature_type = 'mRNA'` anywhere in the search queries.** That was the
assumption going in, and it is not what the code does.

### The other two paths have no such backstop

The name/description FTS index covers every level:

```
feature_types in feature_search:
    protein  68251
    mRNA     68251
    cds      68251
    gene     43757
```

Measured consequences:

```
Feature-ID search for "NV2t021704001"        -> 3 rows
    mRNA     NV2t021704001.1
    cds      NV2t021704001.1:cds
    protein  NV2t021704001.1:pep

Name search for "Map4k4" (annotations off)   -> 4 rows
    gene, mRNA, cds, protein  (one each)
```

The feature-ID case is the nastier of the two, because it is not a coincidence of naming — it
is structural. The query is `feature_uniquename LIKE '%term%'`, and MOOP derives child
uniquenames by suffixing the parent (`…:cds`, `…:pep`). **A transcript ID is therefore always
a substring of its own CDS and protein IDs**, so pasting one transcript ID can never return
one row. It will return three, forever, until the query changes.

---

## Why this matters more than "some duplicate-looking rows"

1. **Same query, different answer depending on entry level.** Searching a gene name, a
   transcript ID, or a protein ID for the same gene gives 4, 3, and 3 rows — with different
   Type values. A user cannot form a stable expectation.
2. **It silently eats the results cap.** Searches are capped per organism
   (`moop_search_results_limit()`, 2500). Three-to-four-fold duplication means a capped search
   surfaces roughly a third as many *distinct genes* as the cap implies, and the "2,500+
   results found" warning misreports how much was really matched.
3. **The simple view does not collapse it.** `groupResultsByFeature()` groups by
   `feature_uniquename`, and `NV2t021704001.1`, `…:cds` and `…:pep` are three DIFFERENT
   uniquenames. So the "N features" badge counts three where a biologist counts one.
4. **It is most visible exactly where users start.** Pasting an ID is the most common first
   search anyone runs.

---

## Options (decide before building)

**A. Filter the two offending paths to the annotation-bearing level.**
Cheapest and matches stated intent ("return mRNA, not protein, to be consistent"). The filter
must NOT be a literal `'mRNA'` — that reintroduces exactly the hardcoding being removed
elsewhere this session. Drive it from config (see the `sequence_types` /
`_fasta_key_for_type()` work in `notes/MOOPMART_FEATURE_IDEAS.md`), or derive it as "the level
annotations attach to", which the loader already decides.
*Risk:* a name-only search would stop returning `gene` rows. Check whether anyone relies on
finding genes that have no mRNA.

**B. Resolve every hit up to a canonical level, then de-duplicate.**
Matching a protein ID returns its mRNA rather than the protein row. Most consistent for the
user, and symmetrical with `expandFeaturesToAllSequenceTypes()` — which already walks
ancestors/descendants type-agnostically and could be reused to normalise a hit set.
*Risk:* the Feature ID column would show an ID the user did not type, which needs to be
explained in the UI or it looks like a wrong result.

**C. Collapse in the results table instead of the query.**
Fold CDS/protein rows into their parent mRNA row in the display layer. Preserves the raw data
and needs no query change.
*Risk:* does nothing for the results cap or the counts, because the duplication still happens
server-side. Treats the symptom.

**Recommendation: A, with the level config-driven rather than literal** — it fixes the cap and
the counts as well as the display, which C cannot. B is the nicest behaviour if the extra UI
explanation is acceptable.

---

## Do not break these while fixing it

- **The loader's mRNA targeting is deliberate** — annotations are computed on proteins but
  attached to the transcript on purpose. Do not "fix" that end.
- **The FTS indexes cover all levels for a reason**: a user searching a protein ID must still
  find something. Filtering the QUERY RESULTS is not the same as narrowing the index, and the
  index should probably stay as it is.
- `searchFeaturesByUniquenameForSearch()` is the fast path tried BEFORE annotation search
  (`$uniquename_search = !empty($results)`), and setting `uniquename_search` switches the
  results table to its no-annotation-columns variant. Changing what it returns changes which
  table variant renders.

---

Discovered 2026-07-23 while adding Assembly and Gene Set columns to the results table — the
duplication became easy to see once Type sat beside them. **Long-standing; unrelated to that
change.**

Related: `notes/RESULTS_TABLE_TOOLBAR_REVIEW.md`, `notes/MOOPMART_FEATURE_IDEAS.md`
(the hardcoded-type map), the memory note "annotations attach to mRNA".
