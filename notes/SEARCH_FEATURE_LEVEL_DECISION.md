# Search feature-level consistency — the decision, and why the obvious fix is wrong

Status: **decided 2026-08-05, implementing.** Supersedes the "Options" section of
`SEARCH_FEATURE_LEVEL_INCONSISTENCY.md`, which stated the problem correctly but proposed a
fix that the data does not support.

The problem, unchanged and still live: the same gene produces 1, 3 or 4 rows depending only
on which level of the gene model the search term happened to match.

```
Feature-ID search  "NV2t021704001"    -> 3 rows   mRNA + …:cds + …:pep
Name search        "Map4k4"           -> 4 rows   gene + mRNA + cds + protein
Annotation search  "kinase"           -> 1 row    mRNA only
```

---

## What changed: Option A, implemented literally, breaks two things

`SEARCH_FEATURE_LEVEL_INCONSISTENCY.md` recommended "filter the offending paths to the
annotation-bearing level", warning only that the filter must not be a literal `'mRNA'`. Both
halves of that turned out to understate the problem. Measured across all 85 databases:

### 1. There is no single annotation-bearing level

| level | annotated features | organisms |
|---|---|---|
| `mRNA` | 2,980,598 | 79 |
| `transcript` | 48,562 | **2** — `Schmidtea_lugubris`, `Schmidtea_nova` |
| `gene` | 7,812 | **9** — 7,708 of them `Bradyrhizobium_diazoefficiens` |

A literal `'mRNA'` filter returns **zero annotation results for Bradyrhizobium** — a
bacterium, whose annotations are gene-level by nature, not by defect — and silently drops
48,562 transcript-level annotations across the two Schmidtea. Correct for 79 of 85 is not
correct. **The level must be derived per DATABASE, not chosen per site.**

### 2. Filtering would break protein-ID search

Searching `NV2t021704001.1:pep` returns 1 row today. Under a filter to the annotation-bearing
level it returns **nothing** — a user pasting a protein accession is told the site does not
have it. That is a worse failure than the duplication being fixed, and it is silent.

### What the risk ISN'T

The originally stated risk — "a name-only search would stop returning `gene` rows; check
whether anyone relies on genes with no mRNA" — is **empirically near-zero**. Of 2,232,940
genes, 3,331 are childless; **all are in `Petromyzon_marinus`, and every one has no name and
no annotations**. Nothing in a name or annotation search can match them. They are reachable
only by exact ID, which is a different path and is not filtered. Checked with self-loops
excluded, so the 8 databases with self-parented features do not fake a result here.

---

## The decision: split by path. Filter where it is safe, RESOLVE where it is not.

| path | today | decision |
|---|---|---|
| name/description | gene + mRNA + cds + protein | **filter** to the level(s) carrying annotations *in that database* |
| annotation | mRNA only — correct by accident (the loader floats annotations up) | make it explicit, same derivation |
| feature-ID | mRNA + …:cds + …:pep | **resolve, do not filter** — map a hit up to its annotation-bearing parent, then dedupe |

Resolving rather than filtering on the ID path also settles the asymmetry introduced by the
GLOB version-tolerance work (`b746c77`): `NV2t021704001` currently returns mRNA + `:pep`
while `NV2t021704001.1` returns 1 row. After this they agree.

### ⚠️ Resolve ONE STEP. Never recurse.

`parent_feature_id` is corrupt in ways that are still live:

| organism | `'NULL'` string | empty string | self-loop |
|---|---|---|---|
| `Schmidtea_mediterranea` | 17,294 | 0 | 0 |
| `Schmidtea_lugubris` | 0 | 33,716 | **14,313** |

All of it sits on **top-level** features, where those values correctly mean "no parent", and
**zero protein/CDS features lack a valid parent**. So a single-step child→parent lookup is
safe, while a recursive walk would hang on those 14,313 self-loops — the failure recorded in
[[project_db_loader_hierarchy_bugs]]. One step is also sufficient: `:cds` and `:pep` hang
directly off the transcript, which is the annotation-bearing level.

Guard anyway: treat `parent == self` as "no parent" rather than trusting the data.

### UI consequence to accept

On the ID path the Feature ID column may show an ID the user did not type. The matched ID
must stay visible ("matched `…:pep`") or a correct result looks like a wrong one. This is the
known cost of resolution and is why it is not hidden.

---

## Where this is implemented — THREE files, not one

The six pages that search all funnel through one AJAX endpoint, so the page count is
misleading. The real surfaces:

| file | change |
|---|---|
| `lib/database_queries.php` | the 3 search functions + the level derivation helper |
| `tools/annotation_search_ajax.php` | the caller that selects between the three |
| **`api/feature_search.php`** | **the index-page ID box — a SECOND search surface** |

`api/feature_search.php` was nearly missed. It is an independent implementation with its own
hardcoded `['gene','mRNA','protein','polypeptide']` list (`:54`) — the same literal-type-list
defect in a different file. Fixing only the search page would have left the index box
inconsistent with it.

**Deliberately NOT changed:**
- `lib/moopmart_functions.php` — its `feature_types` come from the user's explicit filter
  choices. That is an intended filter, not accidental duplication; changing it breaks the
  export contract.
- `lib/extract_search_helpers.php` — maps uniquename→type for FASTA routing and must keep
  per-type identity (a protein ID has to extract protein sequence).
- `lib/parent_functions.php` — gene-page hierarchy display, not search.

---

## Verify against these, not against Nematostella alone

Nematostella is mRNA-only and will pass any implementation, including a wrong one. The cases
that discriminate:

- `Bradyrhizobium_diazoefficiens` — gene-level annotations. Must still return results.
- `Schmidtea_lugubris`, `Schmidtea_nova` — transcript-level, and lugubris carries the 14,313
  self-loops. Must return results and must not hang.
- `Petromyzon_marinus` — the 3,331 childless genes. Must not regress ID lookups for them.
- A protein ID (`…:pep`) — must still find something.

Related: `SEARCH_FEATURE_LEVEL_INCONSISTENCY.md` (the original diagnosis, still accurate on
the problem), `notes/SEARCH_RANKING_LITERAL_TIER.md`, [[annotations-attach-to-mrna]].
