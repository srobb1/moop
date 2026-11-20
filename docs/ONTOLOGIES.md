# Ontologies in MOOP

How ontology terms (PLANA, BSPO, ECO, GO) get from their publishers into MOOP, what uses them,
and how to add or refresh one. Code: `lib/ontologies.php` (files and builds),
`lib/ontology_browser.php` (tree and term pages).

## The whole path at a glance

```
purl.obolibrary.org/obo/{plana,bspo,eco,go}.owl          the published ontologies
        │  weekly GitHub Action in planosphere/PAGE
        │  (.github/workflows/plana-closure.yml, ODK container)
        ▼
planosphere/PAGE  ontology/*-terms.tsv, *-closure.tsv, releases.txt
        │  housekeeping (weekly release check) or "Rebuild now"
        │  or: php scripts/build_ontologies.php [PREFIX ...]
        ▼
<cache_path>/ontologies/{plana,bspo,eco,go}.sqlite      one read-only file per ontology
        │
        ▼
Expression by Anatomy, Ontology Browser + term pages, help "Where the data comes from",
(next) Data Exporter "include more specific terms", GO gene counts per term
```

Nothing in this path needs Java, a reasoner or an OBO parser on the web server. The reasoning is
done once a week on GitHub; MOOP only loads tab-separated files.

## 1. The GitHub Action (planosphere/PAGE)

`.github/workflows/plana-closure.yml` in the PAGE repository. Runs **Mondays 06:17 UTC** and on
demand (**Actions → "PLANA closure" → Run workflow**). A push to the repo does **not** run it.

For each ontology it downloads `http://purl.obolibrary.org/obo/<o>.owl`, reads the release date
from the file, and compares all releases (plus a fingerprint of `terms.rq` and `tidy_terms.py`)
with `ontology/releases.txt`. Only when something changed, or an expected file is missing, does
it rebuild and commit:

| File | Made by | Holds |
|---|---|---|
| `<o>-closure.tsv` (plana, eco, go) | `relation-graph` | every inferred link: `subject  relation  object`, any depth, is_a combined with part of etc. |
| `<o>-terms.tsv` (plana, bspo, eco, go) | `robot query` + `tidy_terms.py` | `term  field  value`: type, label, synonyms with their kind, definition, obsolete, replaced_by |
| `releases.txt` | the workflow | one line per ontology (`go 2026-07-26`) + `recipe <hash>` |

The container is `obolibrary/odkfull:v1.4`, the same one that builds PLANA. The job sets a 7 GB Java
heap (`JAVA_OPTS`, `ROBOT_JAVA_ARGS`) because of GO.

**Where the workflow is edited:** a draft copy lives in `/home/smr/page_repo_additions/` on the
web host. It is not a git clone; copy changed files to the PAGE clone on compute and push from
there. Then run the workflow by hand, since pushing alone does not run it.

**GitHub turns off scheduled workflows after 60 days with no commits to the repo.** The Action
commits only when a release changes; GO releases roughly monthly, which keeps it alive, but if
the weekly run stops, re-enable it on the Actions tab.

### Sizes and times (GO, release 2026-07-26, measured 2026-11-17)

| Step | Time | Peak memory | Output |
|---|---|---|---|
| relation-graph | 41 s | 3.2 GB | `go-closure.tsv` 716,170 rows, 25.6 MB (2.5 MB gzipped) |
| robot + tidy_terms.py | 28 s | 4.0 GB | `go-terms.tsv` 23 MB (3.3 MB gzipped) |
| MOOP build (`scripts/build_ontologies.php GO`) | 10 s | 100 MB | `go.sqlite` 162 MB, 74,156 direct links |

GitHub's file limit is 100 MB and MOOP's download cap (`MOOP_ONTOLOGY_MAX_BYTES`) is also 100 MB,
so GO has room to grow. PLANA, BSPO and ECO are much smaller.

To repeat a trial run on the web host (no Java installed there), use podman with the same image
and run the same commands; storage needs ~3 GB, so put it on the big disk (`/var/www/html`).

## 2. MOOP configuration

**Admin → Site Configuration → Ontologies** (the `ontologies` setting; defaults in
`config/site_config.php`, checked by `ConfigManager::checkOntologyList()`). One row per prefix:

| Key | Meaning |
|---|---|
| `purpose` | `anatomy`, `phenotype`, `evidence`, `function` (GO) or `names` (only names terms from another ontology). Code asks for "the anatomy ontologies", never for PLANA by name. |
| `terms` | the terms TSV (required) |
| `closure` | the link-tree TSV; without it there is no tree, no expansion and no browser |
| `releases` | `releases.txt`, so the weekly check can tell when to rebuild |
| `search` | offered in the anatomy term search box (PLANA, BSPO yes; ECO, GO no) |
| `relations` | the relationships shown (browser, term pages) and offered (anatomy search). `rdfs:subClassOf` = is a. |

Files are accepted **only** from `https://raw.githubusercontent.com/`, enforced both in the
config check and in the downloader.

The tree's starting terms: `ontology_browser` → `PREFIX` → `roots`. PLANA lists the seven Atlas
sections. With no roots set (GO), the tree starts at the terms that have no is-a parent, are not
obsolete and have children: biological process, molecular function, cellular component.

**Per site:** Planosphere sets its own `ontologies` list in its `config_editable.json`, so a new
default (GO) does not appear there until it is added to that site's list.

### The GO relations

All nine relationships in GO's closure are configured:

| Relation | Id | Direct links |
|---|---|---|
| is a | `rdfs:subClassOf` | 57,456 |
| part of | `BFO:0000050` | 7,058 |
| regulates | `RO:0002211` | 3,249 |
| positively regulates | `RO:0002213` | 2,629 |
| negatively regulates | `RO:0002212` | 2,623 |
| has part | `BFO:0000051` | 741 |
| occurs in | `BFO:0000066` | 373 |
| starts during / ends during | `RO:0002092` / `RO:0002093` | 27 |

The tree's "show parts too" view follows every configured relation, so a starts/ends-during link
appears there next to the parts; with 27 links, that was judged acceptable.

**Genes follow GO's own rule:** a term's genes are the genes annotated to it or to a more specific
term through **is a** and **part of** only. Not regulates: a gene annotated to "regulation of
lipid metabolic process" is not a "lipid metabolic process" gene. The same rule must drive the
gene counts and the Data Exporter's "include more specific terms", so that a count always equals
what its exporter link finds.

## 3. Building and refreshing

Each ontology is its own file, `<cache_path>/ontologies/<prefix>.sqlite` (downloaded sources in
`ontologies/sources/`), built to a temp file and swapped in, so a failed build leaves the previous
file in use. Tables: `term`, `synonym`, `closure` (as downloaded), `edge` (direct links, worked out
at build time), `meta` (release, built_at, counts).

- **First build is manual: building is opt-in per site.** Housekeeping refreshes only ontologies
  already built on that site, so adding one to the config does nothing until it is built once:
  **Rebuild now** on the Ontologies card, or

      php scripts/build_ontologies.php GO        # one
      php scripts/build_ontologies.php           # all configured

  (as the web user if possible: `sudo -u apache php ...`; otherwise the setgid cache folder makes
  the file group-writable, which also works).
- **After that it is automatic.** Housekeeping's `ontologies` task downloads each `releases.txt`
  once a week (a few bytes) and rebuilds only when it changed.
- The Ontologies card shows each file's release, build time and counts, and the last failure if
  any. The help page "Where the data comes from" lists every built ontology with its release.

## 4. What uses them

| Feature | Ontologies | Code |
|---|---|---|
| Expression by Anatomy (PAGE records) | anatomy (PLANA, BSPO), evidence (ECO) | `tools/anatomy_search.php`, `lib/anatomy_expression.php` |
| Term search box | those with `search` | `api/ontology_terms.php`, `js/modules/ontology-term-box.js` |
| Ontology Browser + term pages | every ontology with a closure | `tools/ontology_browser.php`, `tools/ontology_term.php`, `api/ontology_children.php` |
| Help "Where the data comes from" | every built one | `tools/pages/help/data-sources.php` |
| Data Exporter link from a term page | GO | `tools/moopmart.php?annotation=GO:…[&annotation_source=…][&annotation_sub=1][&annotation_columns[]=Gene Ontology]` prefills a criterion, ticks "and more specific GO terms" and the GO annotation columns |
| Exporter "and more specific GO terms" | GO | `moopmartMoreSpecificTerms()`: the term plus everything below it by is a + part of (`MOOP_GO_PROPAGATE`, the counts' rule -- verified equal to the counts) |
| GO gene counts per term | GO | `lib/go_gene_counts.php`: one `<cache>/go_counts/<Organism>.sqlite` per organism, counts PER GENE SET; read with `moop_go_counts_for_terms()`, which sums only the gene sets the visitor may see. Shown as tree badges (closed: term + more specific; opened: on the term itself) and in the term page's Genes table: one row per gene set, its number opens the exporter for that gene set, the arrow beside it downloads the same genes as TSV. One gene set at a time by design -- no all-organisms export of a broad term |

## 5. GO gene counts

For each GO term and gene set: the genes annotated to the term or to a more specific one (is a +
part of, `MOOP_GO_PROPAGATE`), every GO source together, a gene counted once. Also kept: genes
annotated to the term itself (`direct`) and genes with any GO term per gene set (`genes_with_go`).

- **Built by** housekeeping's `go_gene_counts` task, in the background run only, about four
  minutes per run (the rest on the next run), or by hand:

      php scripts/build_go_gene_counts.php                      # every stale organism
      php scripts/build_go_gene_counts.php Acropora_millepora   # one, recounted now
      php scripts/build_go_gene_counts.php --force              # all

- **Recounted only when needed:** an organism's file records the size and time of its
  `organism.sqlite` and the build time of `go.sqlite`; when either changes it is counted again.
  Files of organisms that are gone are removed.
- **Cost (2026-11-17):** ~10 s and ~160 MB per organism (*Acropora millepora*: 726k GO links,
  20,107 genes with GO, 20,005 terms); a 470 KB file. All 66 organisms: three background runs.
- **Permissions:** counts are stored per gene set and summed at read time over the visitor's own
  `getAccessibleGeneSets()`, so a restricted gene set never adds to a number someone else sees.

## 6. Adding another ontology

1. In the PAGE workflow add its prefix to the download loop, the closure loop (if it needs a tree)
   and the terms loop, to the "missing files" list and to `git add`. Push, then run the workflow.
2. Check the committed files: `releases.txt` has its line; the closure starts with
   `subject  relation  object`.
3. Site Configuration → Ontologies: add the row (purpose, the three raw.githubusercontent.com URLs,
   relations). If its purpose is new, add it to `checkOntologyList()`, the purpose list on the
   admin card and `moop_ontology_by_purpose()`'s comment.
4. **Rebuild now** (or `scripts/build_ontologies.php PREFIX`) on each site that should have it.
5. Look at it: Ontology Browser `?o=PREFIX`, a term page, and the help page.

## Troubleshooting

- **A new release is out but MOOP still shows the old one.** Check the Action ran (a push does not
  run it) and `releases.txt` on GitHub changed; then wait for the weekly check or press Rebuild now.
- **"refused: … over the size cap".** The file passed 100 MB; raise `MOOP_ONTOLOGY_MAX_BYTES`
  and, on GitHub, gzip the file (GitHub refuses files over 100 MB).
- **An ontology is configured but nothing shows.** It was never built on this site (opt-in, see
  §3).

Background and decisions: `notes/ONTOLOGY_BROWSER_PLAN.md` (original plan; the GO part is
superseded by this document).
