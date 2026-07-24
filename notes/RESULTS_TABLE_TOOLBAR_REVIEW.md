# Results-table toolbar review — Print/PDF, and the Select/CSV/FASTA/colvis wiring

Requested by the user 2026-07-23: *"I think we can get rid of print/PDF functionality. Don't
remove that yet, but do a review to see what would happen and how to do it."* — plus *"if you
can see ways to improve the code of the JavaScript that makes the select and the
csv/print/fasta/vis work better, let me know. It seemed kinda clunky."*

Nothing here is built. This is the review.

Scope: `js/modules/datatable-config.js` (the toolbar) and `js/modules/shared-results-table.js`
(the table). One toolbar serves **six** pages — search, organism, assembly, gene_set, groups,
multi_organism — all of which reach it through `js/modules/annotation-search.js`.

---

## Part 1 — Print / PDF

### What the button actually is

`DataTableExportConfig.buttonDefs.print` extends DataTables' `print` button and is labelled
**"Print / PDF"**.

**It cannot produce a PDF.** A real PDF export in DataTables requires `pdfmake` + `vfs_fonts`
and the `pdfHtml5` button type. Neither is vendored — `js/vendor/` has `jszip.min.js` (for
Excel) and `buttons.print.min.js`, and no pdfmake. What the button does is open the browser's
print view. Any PDF the user ends up with is their *operating system's* "Save as PDF", which
they could get from Ctrl+P on any page without us shipping a button.

So the label promises a feature that does not exist. That alone is worth fixing whichever way
the decision goes.

### Why columns are missing from it (the user's actual complaint)

`datatable-config.js`, in `createButton()`:

```js
// For print, only include visible columns so header and data stay aligned
// (copy/csv/excel can include hidden columns like Species from the data model)
if (buttonType === 'print') return $(node).is(':visible');
```

Print excludes every hidden column — Species, and now Assembly and Gene Set. Copy/CSV/Excel
include them.

**That restriction was a workaround for a bug that is now fixed.** The "header and data stay
aligned" comment refers to the `.export-only` collision documented at the top of
`shared-results-table.js`: `css/parent.css` put `display: none` on `th.export-only,
td.export-only`, which DataTables could not override, so any attempt to surface a hidden
column produced a header with no cells under it. That is gone — visibility is now expressed
once, as DataTables `visible: false`. Verified in-browser: revealing a hidden column now gives
`thead` 7 cells and `tbody` 7 cells, aligned.

**Consequence: making print show everything is a one-line change** — delete that `if`. So the
real question is not "can we fix it" but "is it worth keeping".

### Would a user want it if it had all the data?

Honest answer: **probably not.**

- The full table is **11 columns**, two of which (Description, Annotation Description) are long
  free text and carry `wrap-text`. That does not fit a sheet of paper at a readable size in
  portrait or landscape. Adding the three hidden columns makes it 11 wide instead of 8 — it
  gets *worse* on paper, not better.
- Everything print is reached for is already served better by a sibling button: **CSV** and
  **Excel** for taking the data away (both now include Assembly and Gene Set), **Copy** for
  pasting into a document, **FASTA** for sequences.
- Users who genuinely want paper still have Ctrl+P, and `css/display.css` already has an
  `@media print` block for the page.
- It is the only button whose behaviour differs from the others in a way we have to explain.
  Removing it removes a special case from `createButton()` as well as a button.

### What removing it would take

Small and low-risk — the blast radius is one file:

1. `datatable-config.js` — drop `print` from `buttonDefs`; drop `this.createButton('print', true)`
   from `getSearchResultsButtons()` and `this.createButton('print')` from
   `getAnnotationButtons()`; delete the `if (buttonType === 'print')` special case in
   `createButton()`, which makes `exportOptions.columns` a plain "everything except column 0".
2. `includes/layout.php:217` — stop loading `js/vendor/buttons.print.min.js` (site-wide, every
   page). Leave the file in the repo.
3. Nothing else references it. Confirmed: no other DataTables config in the app defines a print
   button, and `DataTableExportConfig` has exactly two consumers —
   `shared-results-table.js:321` (`getSearchResultsButtons`) and `parent-tools.js:7`
   (`reinitialize` → the legacy `get buttons()` → `getAnnotationButtons`).

**Note `getAnnotationButtons()` reaches the gene page** via `parent-tools.js`, so removing
print there changes the gene-page annotation tables too. That is consistent, but it is a second
place to eyeball.

### Recommendation

**Remove it**, and do it as its own commit so it is trivially revertible. If instead you want to
keep it, then delete the one-line `:visible` special case so it stops lying about the data, and
relabel it **"Print"** so it stops lying about the PDF.

Either way the current state — a button labelled PDF that cannot make a PDF, showing a subset
of the columns for a reason that no longer exists — should not survive.

---

## Part 2 — The toolbar wiring is clunky, and two of the rough edges are real bugs

The user's instinct was right. The pattern underneath the clunkiness: **row selection is read
with page-wide jQuery selectors, but every results page can render several tables at once.**

`$('input.row-select:checked')` means "every checked row *on the page*". On the six search
pages, one table is rendered **per organism**, so this is wrong whenever a search returns more
than one organism.

### Bug A — a sibling table's selection satisfies another table's export guard

`validateSelectedRows()` and the `init` guard on each export button both count checked rows
page-wide, but `exportOptions.rows` correctly filters **per table**. The two disagree.

Measured (search for `kinase` across Nematostella + Petromyzon; one row checked in
Nematostella's table only):

| | value |
|---|---|
| page-wide `:checked` — what the guard reads | 1 |
| checked rows in Petromyzon's table | 0 |
| rows Petromyzon's CSV would export | **0** |

So clicking CSV on the Petromyzon table passes the guard and downloads a file containing
**headers and no rows**, with no warning. The user is told nothing.

### Bug B — FASTA can request sequences from the WRONG assembly

`fastaExportAction()` determines the assembly like this:

```js
const checkedRows = $('input.row-select:checked');          // page-wide
const firstSelectedRow = $(checkedRows[0]).closest('tr');   // ...first one anywhere
assembly = decodeURIComponent(firstSelectedRow.attr('data-genome-accession') || '');
```

Same measurement as above:

| | value |
|---|---|
| assembly FASTA would use for the Petromyzon table | `GCA_033964005.1` ← **Nematostella's** |
| Petromyzon's own assembly | `GCF_010993605.1` |

It also takes the organism from the table's own `.organism-results` ancestor, so the request
goes out as **Petromyzon organism + Nematostella assembly** — a mismatched pair.

**This has a second, subtler face that today's work makes visible.** Even within ONE organism's
table, the code assumes every selected row shares one assembly, because it reads only
`checkedRows[0]`. An organism can have several assemblies (that is exactly why Assembly became
a column), so a user who selects rows spanning two assemblies silently gets sequences resolved
against only the first. Now that Assembly is a column users can switch on and sort by, they are
*more* likely to do this.

### The fix for both

Scope every selection read to the table being acted on. DataTables hands the button its own
`dt` instance, so the table is already in scope:

```js
const $rows = $(dt.rows().nodes()).find('input.row-select:checked');
```

For FASTA, group the selected rows by their `data-genome-accession` rather than trusting the
first, and either submit one request per assembly or refuse with a clear message when a
selection spans assemblies. Grouping is the better behaviour and is not much more code.

### Other cleanups worth doing in the same pass

- **`alert()` is still the validation channel here** — three of them
  (`validateSelectedRows`, "Feature ID column not found", "No valid Feature IDs found"). Commit
  a8058d9 already replaced this pattern elsewhere with the inline amber `.tools-select-hint`;
  the export toolbar was missed. Same treatment, or a small toast anchored to the table.
- **`extractFeatureIds()` locates its column by matching the header text `'feature id'`.** Now
  that `RESULT_COLUMNS` gives every column a stable `key`, it should look the index up by key —
  header text is a display string that a future rename or translation would silently break.
  (Same for the `idx === 0` assumption that keeps Select out of exports: that should be derived
  from the registry, not from the number 0.)
- **`retrieve: true`** on the DataTable init silently returns the existing instance instead of
  erroring on a double init. That is what hides the dead double-init path in
  `annotation-search.js` (`pendingTableInits` builds selectors of the form
  `#resultsTable-Organism_name` with a hyphen, while the tables are created as
  `#resultsTable_Organism_name` with an underscore — so those inits match nothing and have
  never run). Worth deleting the dead path, and considering whether `retrieve` is hiding
  anything else.
- **Button construction rebuilds an `exportOptions` closure per button** and branches inside it
  on `buttonType`. With print gone this collapses to one shared options object.

### Status — all of Part 1 and Part 2 were done on 2026-07-23

Everything above is FIXED. Kept as the record of what was wrong and why.

- Print/PDF removed, including the `buttons.print.min.js` load in `includes/layout.php`.
- Bug A and Bug B fixed by scoping every selection read to `dt`.
- `alert()`s replaced by `DataTableExportConfig.notify()` — an inline amber note on the
  table's own toolbar.
- Export exclusion is class-driven (`NO_EXPORT_CLASS`), which also restored the gene page's
  `Organism` column to its downloads; the feature-ID column is found by `FEATURE_ID_CLASS`
  rather than by matching header text.
- The dead double-init path in `annotation-search.js` (`pendingTableInits`) is deleted.

### Bug C — the per-table FASTA button never returned sequences at all

Found after the above, reported by the user: *"when I select and click FASTA, the form gets
filled but I get no sequences."*

`tools/retrieve_selected_sequences.php` read
`$gene_set_name = trim($_POST['gene_set'] ?? $_GET['gene_set'] ?? 'v1')`, and
`fastaExportAction()` sent only `organism` and `assembly` — never `gene_set`. So every request
from the results table resolved to a gene set literally named `v1`, and the extraction
directory became `organisms/{organism}/{assembly}/v1/`.

**There are zero directories named `v1` anywhere in the data tree** (checked across all
organisms; Nematostella's real gene sets are `NV2` and `RS_101`). The `is_dir()` check failed,
`$displayed_content` stayed empty, and the page rendered with no sequences and no explanation.
This path had never worked.

`api/download_search_fasta.php` — the download-all FASTA in the results header — was
unaffected, because it iterates the organism's real accessible sources instead of composing a
path from a default. That is why only the per-table button was broken, and why it went unnoticed.

Fixed in three layers:

1. Result rows now carry `data-gene-set` alongside `data-genome-accession`.
2. `fastaExportAction()` groups the selection by **assembly + gene set** (not assembly alone)
   and sends `gene_set`.
3. The endpoint no longer invents `v1`: it lists the assembly's real gene-set directories,
   uses the single one when unambiguous, and otherwise reports which gene sets exist.

Also fixed while here, and the user called it before the code was read: **`buildTypedIds()`
was not scoped**. Its query was
`SELECT feature_uniquename, feature_type FROM feature WHERE feature_uniquename IN (...)` — no
gene-set or assembly filter — while the sibling `buildTypedIdsForGenes()` immediately below it
filters `AND f.gene_set_id = ?` at all three levels. A uniquename is only unique within a gene
set, so an organism with two gene sets could return several rows for one name and the last
would silently win. It now takes optional `$assembly` / `$gene_set` and joins `gene_set` +
`genome` when given; `retrieve_selected_sequences.php` passes both.

Verified end to end: the FASTA POST now goes to
`…?organism=Nematostella_vectensis&assembly=GCA_033964005.1&gene_set=NV2` and returns the
selected sequences (`>NV2t021704001.1`, `>NV2t001882001.1`, `>NV2t011739005.1`).

### Still open

- **A FASTA selection spanning several assembly/gene-set combinations is refused** with a
  message asking the user to narrow it, because `retrieve_selected_sequences.php` takes a
  single assembly + gene set. Supporting it properly means teaching that endpoint to accept
  pairs — worth doing, not done.
- **The two remaining `buildTypedIds()` callers are still unscoped**:
  `api/download_search_fasta.php:83` and `tools/retrieve_sequences.php:238`. Both have the
  assembly and gene set in hand, so passing them is small; done as its own change rather than
  bundled here.
- **The wider `'v1'` habit** — `notes/ASSEMBLY_WITHOUT_GENE_SET_PLAN.md` records that a
  fabricated `v1` is invented at ~48 sites while zero such directories exist. This fixed the
  one that was breaking a user-facing feature; the rest are still latent.

Related: `js/modules/shared-results-table.js` (the column registry and why it exists),
`notes/SHARED_SCOPE_SELECTOR_PLAN.md` (the same "one component, many pages" argument applied to
the scope selector).
