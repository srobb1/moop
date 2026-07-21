# Admin UI follow-ups — dashboard wording, card placement, duplicate annotation type

Three items raised by the user on 2026-07-21, each investigated against the live site.
None is fixed yet; each entry records what was verified so the work does not start from scratch.

---

## 1. "Health checks above are cached" points at nothing

`admin/pages/admin.php:211` renders:

> Health checks above are cached — last run 1 hr ago, so a recent fix may still be listed. What runs?

**Verified:** with the site healthy there is nothing above it. Stripping HTML comments from the
rendered dashboard and re-checking:

| element | rendered? |
|---|---|
| Data Health Issues card | **no** — hidden when clean |
| Permission Manager alert | **no** — hidden when clean |
| Site Data Backup card | **no** |
| "About Admin Tools" heading | yes — the only thing above the message |

(A naive grep says "Data Health Issues" *is* present; that match is the HTML **comment** above the
partial, not a rendered card. Strip comments before concluding anything about this page.)

The card is deliberately always-visible — its own comment says it "stays visible when they are all
clean", because *"did my fix work?"* is exactly when the Run-housekeeping button is wanted. That
reasoning is sound; only the sentence is wrong, because it assumes cards are present.

**Fix:** make the wording conditional on whether any health card actually rendered. The partial
already sets `$has_health_issues`, and it stays in scope after the include; the permission alert is
`!empty($_SESSION['perm_summary']['findings'])`. When none rendered, say something true and useful —
e.g. *"All health checks passed — last run 1 hr ago"* — instead of pointing at absent cards.

---

## 2. "Organism Cache" card sits on its own, far below

**Verified** by character offset in the rendered dashboard:

```
 8432  Site Data Backup   (not rendered when clean)
10788  "Health checks above are cached…"
11370  "Run housekeeping now"
16561  Organism Cache — Up to date — 85 organisms, built 6d ago
```

The organism cache is a health/freshness figure of exactly the same kind as the housekeeping block,
but it renders ~5,800 characters further down with unrelated content between. It reads as an orphan.

**Fix:** move it up next to the housekeeping freshness card so all the "how current is this page"
information is in one place. Check first whether the dashboard JS that drives the cache-refresh
widget depends on its current position in the DOM.

---

## 3. Two "Gene Ontology" cards on Manage Annotations

**Root cause found — this is a DATA bug, not a UI bug.**

`metadata/annotation_config.json` holds **two** keys that differ only by a trailing space:

| key | order | colour | description | annotations | features | `new` |
|---|---|---|---|---|---|---|
| `'Gene Ontology'` | 5 | warning | 195 chars | 1,656,430 | 2,437,328 | — |
| `'Gene Ontology '` | 6 | secondary | *(empty)* | 2,345 | 7,923 | `true` |

The second was **auto-discovered from the databases**, so it is reporting real data truthfully.
Exactly one organism is responsible:

```
organisms/Chamaeleo_calyptratus/organism.sqlite
  annotation_source id=50 | OMA2GO | "Gene Ontology "     <-- trailing space
  (ids 36 and 40, InterPro2GO and PANTHER2GO, are clean)
```

So one loader run wrote the OMA2GO source's `annotation_type` with a trailing space, which the type
discovery then picked up as an eleventh annotation type.

**Do not just delete the config entry.** It would be rediscovered on the next refresh, and those
2,345 annotations would be left unclassified in the meantime.

**Fix, in this order:**
1. **Normalise on read** so this class cannot recur — trim `annotation_type` where types are
   discovered (`lib/database_queries.php:703` is the `SELECT DISTINCT ans.annotation_type` site).
   This alone merges the two cards without touching any data.
2. **Fix the source row** — `UPDATE annotation_source SET annotation_type = TRIM(annotation_type)`
   in that one database, so the data matches what every other organism stores. Note the web app
   opens organism databases **read-only**; this is a CLI fix, and the file is `smr:apache 660`.
3. **Remove the stale `'Gene Ontology '` key** from `annotation_config.json` and refresh the
   annotation cache. Only meaningful after step 1, or it comes straight back.

### The whitespace is pervasive — this is not a one-off

Swept every organism database:

```
annotation_source_name with leading/trailing whitespace : 1,207 rows across 71 organisms
annotation_type        with leading/trailing whitespace :     1 row  across  1 organism
```

71 of 85 organisms carry dirty values. So "remember to trim before loading" has already failed
**1,207 times** — it is not a discipline this system can rely on.

What made the difference was *which column* it landed in, not how careful anyone was:

- `annotation_source_name` is **display text** — 1,207 dirty rows merely look slightly wrong.
- `annotation_type` is a **grouping key** — a single dirty row silently created a phantom
  annotation type and split a card in two.

Same defect, blast radius decided by luck. That is the argument for normalising on read (fix 1
above) rather than only tightening the loader: the loader has to be right every time, the
normalisation has to be right once. Do both, but do not treat the loader as the defence.

The 1,207 display-name rows are worth a cleanup sweep too, though they are cosmetic by comparison.

---

## Method note

Two of these were only visible because the page was checked in its **healthy** state. A page that
tells the truth while something is broken can still talk nonsense once it is fixed — "N issues found"
is easy to get right, "nothing to report" much less so. Worth checking both states.
