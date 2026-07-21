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

---

## 4. Admin card headers use four competing idioms — ✅ DONE 2026-07-21 (commit b2a2474)

**Resolved.** 81 headers across 16 files unified on `.adm-head` in `css/admin-cards.css`, loaded
globally from `head-resources.php`. Colour kept only for state: 3 danger (broken registrations,
orphaned in database, JBrowse not installed) and 6 warn (data health, environment issues, stale
entries, stale assembly refs, unused functions x2). `cfg-card`/`cfg-head` were promoted to the
shared classes rather than left page-local. `tools/pages/` deliberately untouched.

Original finding:

Raised by the user looking at Manage JBrowse: is its card styling different **by design**?
Checked across the admin pages — it is not design, it is accumulation. Four idioms coexist:

| idiom | example | pages |
|---|---|---|
| `bg-X bg-opacity-10` | `card-header bg-info bg-opacity-10` | manage_jbrowse, manage_organisms, manage_annotations |
| `bg-X-subtle` | `card-header bg-danger-subtle` | manage_groups |
| solid fill | `card-header bg-secondary text-white`, `bg-light` | manage_organisms, manage_annotations |
| `cfg-head` (teal wash + accent rule) | `card-header cfg-head` | manage_site_config only |

`manage_jbrowse.php` is the clearest case: **eight cards, seven different colours** — danger, dark,
info, primary, secondary, success (x2), warning, all at `bg-opacity-10`. Colour there is not
carrying meaning; when nearly every card is a different colour, none of them signals anything. The
one place it *should* signal — the Broken Registrations card being red — is lost in the rainbow.

Honest note: the `cfg-head` idiom is **mine**, added on 2026-07-21 during the Manage Site
Configuration pass. It fixed that page (which had its own two-tier split) but made the site-wide
inconsistency worse by adding a fourth style rather than a shared one. If it is the direction we
want — it matches the stated teal/soft-wash preference — it should move out of
`css/manage-site-config.css` into a shared stylesheet and be applied across the admin pages, with
colour reserved for genuine severity (a red header meaning "this is broken", not "this is the
tracks card").

**Suggested approach:** pick one idiom, promote `.cfg-head` to a shared `admin-cards.css`, and
allow exactly one deviation — a danger variant for cards that represent an actual problem. Do the
sweep in one pass so the pages cannot drift apart again, the same reasoning as the single
definition for registry sources and sequence file names.

---

## 5. `tools/pages/registry.php` and `tools/pages/js_registry.php` are almost certainly obsolete

Found 2026-07-21 while checking whether the tools pages needed the admin card sweep. These two are
the ONLY tools pages not following the tools header convention — `js_registry.php` still carries the
full pre-sweep admin rainbow (`bg-info`, `bg-primary`, `bg-secondary`, `bg-success`, `bg-dark`).
They never drifted back into line because **nothing renders them**:

| check | result |
|---|---|
| `tools/registry.php` controller | does not exist — URL 404s |
| `tools/js_registry.php` controller | does not exist — URL 404s |
| direct hit on `tools/pages/registry.php` | 500 |
| real code references | **none** |
| last modified | December 2025 |

The one apparent reference is a **docblock example** in `admin/registry-template.php`
(`'content_file' => __DIR__ . '/pages/registry.php'`) — `__DIR__` there is `admin/`, and
`admin/pages/registry.php` does not exist either. That same docblock already carries a note saying
the tools-side registry files do not exist.

Almost certainly leftovers from when the registries moved to `admin/` (which produced
`admin/manage_registry.php` and `admin/manage_js_registry.php`, both audited 2026-07-21 and working).

**Suggested:** delete both. They are recoverable from git, and a stale copy of a page that still
exists elsewhere is the `#19`-class hazard — someone edits the wrong file and cannot see why nothing
changes. Confirm first that no deployment has its own controller for them.

---

## 6. Tools page styling is consistent by hand, not by stylesheet

The tools pages DO look consistent — solid teal header with white text — but that consistency is
maintained by copied literals rather than enforced anywhere:

```
24  inline style="background-color: #..."  declarations across tools/pages/
 8  uses of a shared semantic class (bg-search-results, bg-tools, organism-header)

 5  distinct hardcoded hex values:
    #0891b2 (x11)  #0f766e (x5)  #e11d48 (x3)  #6366f1 (x3)  #d97706 (x1)
```

This is the same fragility that produced the admin card drift — it simply has not broken yet,
because the copies happen to still agree. Changing the site's teal today means finding 24 inline
declarations across 15 files, and missing one is invisible until someone notices two slightly
different headers.

Note the tools look (solid fill, white text) is deliberately BOLDER than the admin look settled on
in b2a2474 (pale wash, dark text, accent rule), and that distinction is worth keeping — public pages
announce, admin pages get out of the way. This item is about where the values live, not about making
the two match.

**Suggested:** promote the five colours to CSS custom properties in `css/moop.css` alongside the
existing `.bg-search-results` / `.bg-tools`, and replace the inline styles with semantic classes.
Same reasoning as `css/admin-cards.css`, `lib/registry_sources.php` and the sequence file-name
helper: one definition, so drift is impossible rather than merely unlikely.
