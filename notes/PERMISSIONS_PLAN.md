# Permissions for data sets, analyses and content pages — plan

Written 2026-11-13 from Sofia's requests (11-12: "move the permission layer work higher up … this is
important to us"; 11-13: analyses and content pages need permissions too; one page showing all
data and their permissions; an edit-permission control next to each page; a filter in Manage Pages).
Nothing below is built unless marked **built**.

---

## 1. The rule

**If any parent is private to you, so is the item.** Organism → assembly → gene set → analysis.
A content page is either open or tied to a data set, and follows the same rule.

- **Private and missing look the same: 404.** A private page or analysis never says "you may not
  see this" — that would confirm it exists.
- **A child can only narrow access, never widen it.** An analysis can be more private than its gene
  set, not more public. One direction keeps the rule checkable at a glance.

## 2. Who can see what — the levels (correction to an earlier answer)

Groups (`organism_assembly_groups.json`) do **not** grant access to people: only the `PUBLIC`
group means something for access. Collaborators get access from their own list in `users.json`;
internal-network visitors (IP_IN_RANGE) and admins see all data. So a page or analysis does not
get "groups"; it gets one of these:

| Setting | Who sees it |
|---|---|
| **Same as its data set** (default for an analysis) | whoever may see that organism / assembly / gene set |
| **Everyone** (default for a content page) | public |
| **Logged in** | collaborators, internal network, admins (any logged-in level) |
| **Internal** | internal network + admins (not outside collaborators) |
| **Admins only** | admins — drafts, things not ready |

For an analysis, "Everyone" is not offered (it would widen); the narrowest of its own setting and its
gene set's wins. A content page set to "same as data set" names the data set (e.g. a page about an
unpublished assembly follows that assembly — no second list to keep in step).

Named collaborators per page/analysis: **not in the first version.** Add only if a real case appears.

## 3. What exists today (2026-11-13)

**Built:**
- `moop_dataset_visible($organism, $assembly, $gene_set, $analysis)` — `includes/access_control.php`.
  Organism/assembly/gene set via `has_gene_set_access()`; `$analysis` accepted but ignored.
- Used by: gene images (gene page, gallery, `api/gene_image.php`), ortholog + gene family cards
  (`lib/clade_homologs.php`).
- Expression experiments: own `access_level` in `experiment.json`
  (`organisms/{Org}/{Asm}/{GeneSet}/expression/{experiment}/`), level-only, narrows only.

**Gaps** — ⚠️ "nothing is private today" was wrong: on simrbase only `smed_20140614` was PUBLIC among the
Smed gene sets (S3 h1/h2, A2, ddv6 not yet marked), so the crosswalk readers below DID show their ids to
the public. Closed by step 1 (2026-11-13); Sofia: all Smed gene sets are to be public, just not marked yet.
- Rosetta Stone ID Translator (`tools/crosswalk.php:19`) and Anatomical Expression Search
  (`tools/anatomy_search.php:23`, `lib/anatomy_expression.php:681`): check only that the organism has
  *something* public.
- Gene page "Same gene in this species" card: lists ids from every crosswalk source.
- Content pages: no access check at all. Read in six places: `page.php`, `index.php` (home tiles),
  `{include:}` (`moop_page_includes_expand`), Manage Menu, the ontology browser (term pages link pages),
  `admin/manage_languages.php`.

## 4. Design

### 4a. One stored list of restrictions — `metadata/access_rules.json` (site data, backed up)

Only items that differ from their default are stored, so the file stays short and readable:

```json
{
  "analyses": [
    {"organism": "Schmidtea_mediterranea", "assembly": "smed_20140614", "gene_set": "smed_20140614",
     "analysis": "expression:davies_embryo", "visible_to": "internal"}
  ],
  "pages": [
    {"slug": "unpublished-atlas", "visible_to": "dataset",
     "organism": "Schmidtea_mediterranea", "assembly": "schMedS3h1", "gene_set": "..."}
  ]
}
```

Data sets keep their current home (groups file + `users.json`) — not moved.

**Analysis keys** are `kind:name`, so every kind of analysis goes through one check:
`expression:<experiment>`, `images:<analysis>` (the `analysis` column of `gene_images.sqlite`),
`clade:<clade>` (an OrthoFinder run spans organisms — its rule is checked per organism row it shows).
Expression's own `access_level` folds in: read as that analysis's setting, so there is one rule, not two.

### 4b. Two checks, used everywhere

- `moop_dataset_visible(org, asm, gene_set, analysis)` — gains the analysis rule (narrowest wins) and
  accepts `gene_set = ''` for an assembly with no gene set (BLAST-only genomes,
  `notes/ASSEMBLY_WITHOUT_GENE_SET_PLAN.md`).
- `moop_page_visible($slug)` — new; "dataset" pages call `moop_dataset_visible()`.

No call site decides access by itself. A new feature calls one of these two.

### 4c. One admin page with everything — **Access overview** ("Who can see what")

Yes, it makes sense: today the answer to "what is private?" is spread over Manage Groups, Manage
Users, `experiment.json` files and nothing at all for pages. One page answers it.

- **One table, one row per item:** organisms → assemblies → gene sets, their analyses (discovered from
  the files: expression experiments, image analyses, clade runs), and every content page.
- **Columns:** kind · item · who sees it (plain words: "Everyone", "Logged in", "Admins only") ·
  *why* ("inherited from gene set smed_20140614" vs "set here") · Edit.
- **Filters:** text box, kind (data set / analysis / page), organism, and "only restricted" — the
  last one is the useful view: everything that is NOT public, on one screen.
- **Edit** opens a small dialog (the setting from §2, plus the data set for a "same as data set"
  page). Data-set rows link to Manage Groups / Manage Users rather than duplicating them.
- Precomputed? No — the item lists are cheap (directory listing + small JSON), so it is live.

**The same Edit dialog** is reused, not copied: a lock button next to each page in Manage Pages, and
next to each analysis where analyses are listed. One editor, two entry points, one file.

### 4d. Manage Pages — filter + permission column

- A filter box above the pages table (title, slug, language), DataTables like the other admin tables.
- A "Visible to" column with the lock/edit button (§4c).

## 5. Work list, in order (one commit each, with tests)

1. **Built 2026-11-13** — one rule in `lib/crosswalk.php` (`moop_crosswalk_entry_visible()` /
   `moop_crosswalk_row_visible()`) applied where the crosswalk is read, so every reader gets it; the
   anatomy search's one direct query too. Smoke tests proven red on the old code. PAGE curations
   themselves (published literature) stay shown; only their gene links are filtered.
   Original item: **Close the gaps** — translator, anatomy search, Same-gene card through `moop_dataset_visible()`.
   Needs each row's data set: crosswalk sources already carry assembly/gene set in the crosswalk
   config; a curation's accession reaches its source through the crosswalk. *Small; real leak risk.*
2. **Built 2026-11-13** — `lib/access_rules.php` (`access_rules.json` "pages", fails closed: unknown setting
   or unreadable file = admins only; a no-op clear never writes) + `moop_page_for_visitor()` in
   `page.php`, home (falls back to MOOP's home), `{include:}`, the menu (`ConfigManager::menuLink`), the
   ontology term page. Manage Languages/Menu/Pages are admin-only, so unchanged. Deleting a page drops its
   rule. Snapshot list has `access_rules.json`. NOT done: links to a private page typed by hand inside
   another page's HTML (e.g. home tiles) stay as links and 404 — they cannot be dropped generically.
   Original item: **Pages:** `moop_page_visible()` + `access_rules.json` "pages"; check in all six readers;
   private = 404; drop from menus, tiles and `{include:}` for those who can't see it.
3. **Built 2026-11-13** — "Visible to" column + filter box (DataTables) in Manage Pages; the same button
   in the page editor. ONE dialog, `admin/pages/_access_rule_dialog.php` + `js/modules/access-rule-dialog.js`
   (button class `js-access-edit`, data-kind/key/title/rule; posts `_action=save_access`) — reuse it for
   step 5. Data-set list = groups file, each assembly also on its own. Verified in a real browser.
   Original item: **Manage Pages:** filter box + "Visible to" column + Edit dialog.
4. **Analyses:** analysis rules in `moop_dataset_visible()`; expression `access_level` folded in;
   images and clades pass their analysis key.
5. **Access overview page** (§4c), reusing the dialog.
6. **BLAST-only genomes** — `gene_set = ''` path (separate note).

Tests (`tests/smoke_tests.php`): for each layer, the same item as PUBLIC / COLLABORATOR with and
without the data set / IP_IN_RANGE / ADMIN; a private page through each of the six readers;
"narrowest wins" for an analysis; 404 identical for private and missing.

Estimate: steps 1–3 one session, 4–5 another; 6 after.

## 6. Decisions — proposed; Sofia approved the plan 2026-11-13 ("it looks good")

1. The five settings in §2 (vs. per-page named users). **Proposed:** the five settings.
2. An analysis can only narrow its gene set's access. **Proposed:** yes.
3. A private item is a 404, same as missing. **Proposed:** yes (as already for retired data).
4. Expression's `access_level` folds into the one rule (§4a). **Proposed:** yes.
