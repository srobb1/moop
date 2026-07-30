# Search ranking: the literal tier was stricter than the match, and bm25 decided the rest

Found 2026-07-29 from a real user search. **Fixed 2026-07-30.** Two defects, not one — the
second only became visible once the user pasted their actual Expanded view.

---

## 1. What the user saw

Searching **`transposases`** in `Craseonycteris_thonglongyai`, the top ten rows were
identical and anonymous:

```
mRNA  CTH1_PVKE010006752.1_000001.1  —  —  EggNOG  ENSACAP00000021107  Putative DNA-binding
mRNA  CTH1_PVKE010009225.1_000001.1  —  —  EggNOG  ENSACAP00000021107  domain in centromere
mRNA  CTH1_PVKE010014781.1_000001.1  —  —  EggNOG  ENSACAP00000021107  protein B, mouse jerky
...                                                                     and transposases.
```

`THAP9`, `HMGXB3`, `POGK`, `TIGD1` — which carry that same EggNOG domain **and a name** —
sat below them.

User's diagnosis: *"named sequences with the included search term should rank higher."*
Correct, and note the qualifier: not "prefer named genes", but "among things that matched,
show me the ones I can identify".

---

## 2. Two separate causes

### (a) The literal tier tested the typed word, not the matched one

`ORDER BY name_match DESC, (a.annotation_description LIKE '%term%') DESC, bm25(...)`

FTS5 matched on the **stem**; the tier tested the **raw typed term**. A plural search
therefore credited only records carrying that same plural, and in this data the singular is
far more common. Measured on `Craseonycteris_thonglongyai`:

| typed | stem | matched rows | credited today | credited by stem |
|---|---|---|---|---|
| `transposons`  | `transposon`  | 234     | **2**     | 125    |
| `transposases` | `transposas`  | 517     | 27        | 336    |
| `receptors`    | `receptor`    | 146,173 | 7,155     | 57,673 |
| `kinase`       | `kinas`       | 112,984 | 36,434    | 36,434 (identical) |

With `transposons`, **two rows** decided the whole top of the list and everything after them
fell back to bm25 — which put six annotations of one unnamed gene (`Homeodomain-like`,
`consensus disorder prediction`, `-`) above `MAEL`, described as
*maelstrom spermatogenic transposon silencer*.

### (b) Within one tier, bm25 had nothing to go on

The ten rows above are the *same description* on ten different features. bm25 scores them
identically and orders them arbitrarily, so they filled the screen. This is what the user
actually hit, and (a) does not fix it — those rows contain `transposases` literally, so they
are top-tier either way.

---

## 3. What shipped

`lib/database_queries.php`, both search paths:

```
ORDER BY name_match DESC,                              -- gene named with the typed word
         (a.annotation_description LIKE '%term%') DESC, -- literal, unchanged
         (a.annotation_description LIKE '%stem%') DESC, -- NEW: what FTS actually matched
         (COALESCE(f.feature_name,'') <> '') DESC,      -- NEW: tie-break, has a name
         bm25(...), f.feature_uniquename
```

**The stem comes from SQLite's own tokenizer** (`moop_porter_stem()` — in-memory fts5 table
+ `fts5vocab`), not a PHP reimplementation, so it cannot drift from what the index stored.
`js/modules/search-terms.js` declines the same job for the same reason.

**Verified: SQLite's porter only truncates, never appends** — including the cases the
textbook algorithm rewrites (`relational`→`relat`, not "relate"; `digitizer`→`digit`;
`vietnamization`→`vietnam`). So the stem is always a prefix of the typed word, and
`%stem%` is always a *looser* pattern than `%term%`. `moop_porter_stem()` enforces that
rather than trusting it, and returns `''` (tier skipped) if it ever fails.

Result, `transposases`: top rows are now `THAP9`, `HMGXB3`, `POGK`, `Pogk`, `TIGD1`.
`transposons`: `THAP9`, `TP53`, `MAEL`×7, `JRKL`, `CENPB`, `JRK`.

---

## 4. Why the new tiers are *below* the literal one

The literal tier is load-bearing — it is what fixed the porter precision bug, where
`transpos` stems to `transpo` and so prefix-matches TRANSPORT. Widening it would hand that
back; adding beneath it cannot. For `transpos` the 1,326 rows containing it still sort above
the 64,725 the stem reaches.

Same reasoning for the has-a-name tie-break: it is read only after exact and stem matches
are already separated, so an unnamed feature never falls below a *weaker* match — only below
an *equal* one that a reader can identify. That preserves the user's standing objection to
ranking on names (*"we don't always name a gene right, which is why we have the annotations
for searching"*), which was about not burying good unnamed annotations, and still holds.

### Regression checks (all through the live endpoint, not hand-written SQL)

| check | result |
|---|---|
| `transpos` — the recorded worst case, must stay ~100/100 | **100/100** |
| `kinase` — common singular that worked well | **100/100** |
| `"zinc finger"` quoted — phrase mode | **100/100** |
| `transposases` / `transposons` — the reported bug | fixed, top rows all named |
| gene-only path (all sources deselected) | fixed, same tie-break |
| cost, warm, 3 runs each | **5 ms old, 5 ms new** |
| `tests/smoke_tests.php` | 44 passed, 0 failed |

---

## 5. Quoted search: stemmed, but the exact text still wins

Quotes give **adjacency and order, not exactness** — FTS5 tokenizes the phrase with porter
like everything else, and there is no per-query tokenizer switch. The stem tier is
deliberately skipped for quoted input (a phrase has no single stem, and quoting is what a
user reaches for when they want exactness), so quoted searches get the exact-text tier only.

That is enough in practice. Quoted `"transposable elements"`: 2,500 rows, **exactly 1**
contains the plural phrase, 756 contain the singular — and that one row is **#1**, followed
by `PGBD5`, `TIGD2`, `TIGD5`, `TIGD3`, `TIGD4`, `POGK`. Exact text first, everything else
ordered by name-then-relevance.

**If truly exact quoted search is ever wanted**, the cheap route is to keep the FTS match
(a superset) and post-filter with `LIKE '%phrase%'` — no second index. Not done: it changes
documented behaviour, and `includes/search_help_modal.php` currently describes quotes
correctly as adjacency + order.

---

## 6. Still open from the same session

- **Tier 1 (`feature_name LIKE '%term%'`) has defect (a) too** — a plural search gives no
  name credit to a gene named with the singular. Not fixed because it is unmeasurable here:
  `name_exact` and `name_stem` are **both 0** for every transposon term in this organism
  (these are symbol-named RefSeq genes). Needs an organism with word-shaped feature names to
  measure before touching, and the safe position is *below* the description tiers — a name
  stem tier above them would let `transpo`→TRANSPORTIN outrank real transposase hits.
- **The gene-only path has no literal/stem tier at all**, only `name_match` → has-name →
  bm25. It returns tens of rows, not thousands, so flooding has not been an issue.
- **Junk annotation descriptions**: `-` and `consensus disorder prediction` are indexed and
  searchable as if they were content. They no longer reach the top, but they are still rows.
- **Gene page slowness.** User clicked a feature ID and reported "the system seems like it is
  dying"; server was healthy moments later. Unconfirmed — reproduce on a cold organism.
- **Benchmarks evict the cache brutally.** `crossorg.py` took page cache 12.2 GB → 0.3 GB;
  2.5 h later only 6.6 GB. Warming 49 bat organisms took 226 s. Do not run cross-organism
  benchmarks while anyone is using the site. See `notes/STORAGE_AND_RAM_TESTING.md`.

---

## 7. Files

| file | what |
|---|---|
| `lib/database_queries.php` | `moop_porter_stem()`, `ftsStemLikePattern()`, both ORDER BYs |
| `js/modules/search-terms.js` | `moopTermHighlight()` — same prefix rule, for highlighting |
| `includes/search_help_modal.php` | user-facing help; "Exact matches are listed first" stays true |
