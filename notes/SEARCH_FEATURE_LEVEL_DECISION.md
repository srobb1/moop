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

### ⚠️ CORRECTION (2026-08-05): "one step" was wrong — it is a BOUNDED CLIMB

An earlier revision of this file said a single child→parent step was sufficient because
`:cds` and `:pep` both hang off the transcript. **They do not.** Measured on Nematostella:

```
NV2t021704001.1       mRNA     parent -> 43100 (gene)
NV2t021704001.1:cds   cds      parent -> 43101 (the mRNA)
NV2t021704001.1:pep   protein  parent -> 43102 (the CDS)
```

The chain is mRNA → cds → protein, so a protein is **two** steps from the annotation-bearing
level. Resolving one step turned a protein hit into a CDS hit and the duplicate reappeared
one row down — the implementation looked like it worked and returned 2 rows instead of 1.

The guard below still applies; it just needs a depth cap rather than a hard stop at 1.

### ⚠️ Bound the climb. Never recurse without a guard.

`parent_feature_id` is corrupt in ways that are still live:

| organism | `'NULL'` string | empty string | self-loop |
|---|---|---|---|
| `Schmidtea_mediterranea` | 17,294 | 0 | 0 |
| `Schmidtea_lugubris` | 0 | 33,716 | **14,313** |

All of it sits on **top-level** features, where those values correctly mean "no parent", and
**zero protein/CDS features lack a valid parent**. An *unguarded* climb would hang on those
14,313 self-loops — the failure recorded in [[project_db_loader_hierarchy_bugs]] — so the
climb carries both guards the existing walkers use: `f.feature_id <> c.feature_id` (stops a
self-parent) and `depth < MOOP_HIERARCHY_MAX_DEPTH` (stops a multi-row cycle). SQLite 3.34.1
here has no `CYCLE` clause, so both are required.

(lugubris was reloaded 2026-08-05 and is now clean — 0 self-loops, real SQL NULL roots. The
other databases are not, so the guards stay.)

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

---

## ⚠️ This is the FOURTH implementation of "walk up the hierarchy" (user, 2026-08-05)

The user flagged it mid-implementation — *"I feel like this is a problem we have addressed a
few times. maybe on the parent page, on moopmart, on sequence retrieval"* — and they are
right. All four exist today:

| where | function | stops at |
|---|---|---|
| gene page | `lib/parent_functions.php::getAncestors()` | returns the whole chain |
| MOOPmart | `lib/moopmart_functions.php::moopmartResolveInputIds()` | the ROOT (`parent IS NULL`) |
| sequence retrieval | `lib/extract_search_helpers.php::expandFeaturesToAllSequenceTypes()` | walks BOTH directions |
| search (new) | `lib/database_queries.php::moop_resolve_hits_to_level()` | the annotation-bearing level |

The new one deliberately **reuses the established shape** — same recursive CTE, same cycle
guard (`f.feature_id <> c.feature_id` plus `MOOP_HIERARCHY_MAX_DEPTH`), same
`require_once parent_functions.php` for the constant that MOOPmart already needed for exactly
this reason. The first draft was a hand-rolled PHP loop; it was thrown away in favour of the
pattern already in the tree.

But it is still a fourth copy, and CLAUDE.md §9b is explicit about saying so. **They differ
only in where the climb STOPS.** One shared helper taking a stop-condition (whole chain /
root / a set of types) would collapse all four. That is a real consolidation, not a
tidy-up — the four have already drifted on cycle-guarding once (the guards were added to
parent_functions and moopmart separately, after a hang).

**Not done now** because it touches the gene page, MOOPmart and sequence retrieval, which is
too wide a change to fold into a search fix pre-launch. Do it as its own pass, with the four
call sites' behaviour pinned by tests first.


## ⚠️ ID nesting is per-source, not a MOOP rule (user correction, 2026-08-05)

An earlier draft of this file and of the commit message said "MOOP derives child uniquenames
by suffixing the parent (:cds, :pep)". **That is wrong as a general statement.** Depositor IDs
are preserved — see [[feedback_original_data_stays_original]] — and `:cds`/`:pep` is
specifically the **transcript2gene** path suffixing MOOP's own copies to disambiguate them.
Measured:

| source | transcript | child ids | nests? |
|---|---|---|---|
| T2G | `NV2t021704001.1` | `…:cds`, `…:pep` | yes, as a SUFFIX |
| RefSeq | — | `cds-WP_011083461.1` vs `WP_011083461.1` | yes, as a PREFIX |
| Ensembl/FlyBase | `FBtr0070000` | `FBpp0291548` | **no — independent ids** |

This is the whole reason the fix resolves through the HIERARCHY instead of pattern-matching
IDs. Stripping `:pep`/`:cds` would be five lines, would work for T2G gene sets, and would
silently do nothing for RefSeq's prefix form. Drosophila needs no fix at all — with
independent ids there is no duplication to remove.

## Status

- [x] name/description path — filter to the derived level, with unfiltered fallback (`66cfca2`)
- [x] feature-ID path (search page) — bounded climb to the annotation level, dedupe, never drop
- [ ] **`api/feature_search.php` (index-page ID box) — NOT done.** Still returns 2 rows for
      `NV2t021704001`. It is a different shape: cross-organism, many ATTACHed databases, so the
      per-database climb has to run per attached DB rather than once.
- [ ] UI: surface `matched_uniquename` ("matched …:pep") in the results table.

Measured after the search-page change:

| organism | term | before | after |
|---|---|---|---|
| Nematostella | `NV2t021704001` | 3 | **1** |
| Schmidtea_lugubris | `SlugcT0000001` | 3 | **1** |
| Bradyrhizobium | `WP_011083461` | 2 | **1** |
| Petromyzon | `PM00915` | 4 | **2** |

Petromyzon keeps 2 by design: the term matches the gene `PM00915` *and* the mRNA
`PM00915.1`, and a gene cannot be lifted to mRNA because mRNA is its DESCENDANT, not its
ancestor. Both rows are genuine literal matches at different levels.
