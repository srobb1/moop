# Which tool should MOOP add next? — survey of comparable sites + recommendations

Status: **research done 2026-08-06, nothing built.** Raised by the user: *"i want to add a new
tool… can you review popular genome data websites and determine which tools are often used?"*
Same session the user added a second idea: a **primer-making tool built on Primer3**, from
existing Perl they have for primers and RT-PCR primers.

This note records the survey, a verified data finding that changes the ranking, and the
recommendation, so none of it has to be re-derived.

---

## 1. What comparable sites ship

Surveyed: Ensembl, UCSC, NCBI, WormBase, FlyBase, PlanMine, Echinobase, VEuPathDB/PlasmoDB,
and the InterMine platform (which underpins PlanMine, FlyMine, WormMine, YeastMine…).

| Tool | Who ships it | MOOP today |
|---|---|---|
| Genome browser | everyone | ✅ JBrowse2 |
| BLAST / BLAT | everyone | ✅ `tools/blast.php` |
| Sequence retrieval by ID / region | everyone | ✅ Retrieve Sequences |
| Bulk file download | everyone | ✅ Downloads |
| Data mining / query builder | Ensembl BioMart, UCSC Table Browser, every InterMine, VEuPathDB | ✅ MOOPmart |
| Keyword / annotation search | everyone | ✅ Annotation Search |
| Gene report page | everyone | ✅ `tools/parent.php` |
| **Ortholog / comparative view** | Ensembl Compara, FlyBase (DIOPT), PlasmoDB "Transform by Orthology", Echinobase 1:1 ortholog maps, PlanMine orthologues | ❌ **gap** |
| **In-silico PCR / primer design** | UCSC — one of its *five* main tools; NCBI Primer-BLAST | ❌ **gap** (planned) |
| **Gene lists / saved sets** | every InterMine, VEuPathDB, NCBI clipboard | ❌ gap |
| **Enrichment (GO / domain / pathway)** | WormBase TEA-PEA-GEA, InterMine list widgets, VEuPathDB | ❌ gap |
| Multiple alignment | several | ⚠️ see §4 — the API exists and is surfaced nowhere |
| Assembly converter / liftOver | Ensembl, UCSC | ❌ needs chain files MOOP does not have |
| ID history converter | Ensembl | ❌ presupposes stable release-to-release IDs |
| VEP / variant tools | Ensembl, UCSC | ❌ MOOP holds no variant data |
| Expression browser | most MODs | ❌ planned — `notes/EXPRESSION_EXPLORER_PLAN.md` |
| CRISPR guide design | FlyBase/WormBase (mostly as outbound links) | ❌ |
| REST API / web services | everyone | ⚠️ partial |

**The pattern.** MOOP already ships the whole "standard six" that every one of these sites has
(browser, BLAST, retrieval, download, mining, search). The gaps that recur across *all* of them
are the same four: **orthology, primers, lists, enrichment.** Everything else on the missing list
is either data MOOP does not hold (variants, chain files) or already planned elsewhere.

---

## 2. ⭐ The finding that changes the ranking — the ortholog data is ALREADY LOADED

Measured on this host 2026-08-06, not assumed.

**83 of 85 organism databases carry both GO terms and EggNOG orthologs**, alongside per-species
Ensembl RBBH homologs (human, mouse, fly, worm, zebrafish, chicken, frog, yeast, *E. coli*,
Arabidopsis, and more). Scale, on Amphimedon alone:

```
Gene Ontology   EggNOG (EggNOG2GO)          343,527
Orthologs       EggNOG                       20,016
RBBH Homolog    Ensembl <species>        ~16–20k each, ~17 species
Domains         InterProScan (InterPro)      19,474
```

Sample rows, showing the accessions are real shared reference identifiers:

```
Ensembl Homo sapiens : ENSP00000239243.5 | MSX2: msh homeobox 2 [Source:HGNC Symbol;…]
EggNOG               : PAC_15725621      | MSX2: positive regulation of mesenchymal…
Gene Ontology        : GO:0016055        | Wnt signaling pathway: "The series of…"
```

**The cross-organism join was tested and it works.** Two organisms about as far apart as MOOP
holds — a sponge and a land planarian — matched on shared human RBBH accession:

```
Amphimedon_queenslandica : 8,195 distinct human RBBH proteins
Bipalium_kewense         : 7,730
shared between the two   : 4,144
```

So **"show me this gene across all 85 organisms" is computable from data already in the
databases** — a join through a shared reference protein. No new pipeline, no OrthoFinder run,
no reload.

⚠️ **Two caveats that must not be lost:**

- **RBBH-through-a-reference is *inferred* orthology, not a tree-based ortholog call.** It is
  reciprocal-best-hit to a common third species, which is a weaker claim than Compara or DIOPT
  make. The UI must label it as such — say what the evidence is (RBBH via human, EggNOG group),
  never present it as a curated ortholog assertion.
- **It is an 83-database fan-out**, the cost shape that already dominates search
  (`notes/QUERY_PERFORMANCE.md`, `notes/SEARCH_COST_MODEL_2026-07-31.md`). An indexed lookup on
  `annotation_accession` is a very different shape from an FTS scan — a point query, not a
  corpus sweep — but that is a prediction. **Measure it cold before promising it.**

---

## 3. Recommendation, ranked

1. **Ortholog Explorer** — biggest payoff. It is the standard feature at every comparable site
   *and* it is MOOP's actual differentiator: nobody else has this organism set in one place.
   The data is loaded and the join is verified (§2). Start by measuring the cold fan-out.
2. **Primer / in-silico PCR tool** — the user's own idea, and they have Perl for it. UCSC ranking
   in-silico PCR among its five main tools confirms the demand is not niche.
   `notes/PRIMER_BLAST_TOOL_PLAN.md` already specs the hit-pairing and product-size half;
   Primer3 extends it from *"where do my primers land"* to *"design me primers."*
   **RT-PCR primers are a real differentiator** — exon-junction-spanning design needs exon
   coordinates, and MOOP has them (`feature_coords.tsv`, plus the GFF).
   ⚠️ Open: the user's Perl is **not on this host** (`/home/smr/moop-dbtools/` holds only
   loaders and parsers), and **`primer3_core` is not installed** here. Both needed before build.
3. **Gene lists + enrichment** — the InterMine pattern (upload a list → widgets for GO, domains,
   orthologs). Larger build, and enrichment is easy to get statistically wrong: it needs a
   defensible background set and multiple-testing correction (InterMine offers Bonferroni,
   Holm-Bonferroni, Benjamini-Hochberg). Worth doing, but after 1–2.

Deliberately **not** recommended: liftOver/assembly converter (no chain files), VEP (no variant
data), ID history converter (presupposes versioned release IDs MOOP does not mint).

---

## 4. Incidental finding — the MAFFT alignment API is dormant

`api/galaxy_mafft_align.php`, `api/galaxy/mafft.php` and `lib/galaxy/mafft.php` exist, but
grepping `tools/`, `js/` and `includes/` for `mafft` returns **nothing outside the help pages**.
There is a Galaxy-backed multiple-alignment capability built and reachable by no user.

Either surface it or retire it — but decide, rather than leaving a fourth state. Note that
multiple alignment is a natural *second step* after an ortholog lookup ("align these orthologs
across organisms"), so §3.1 may give it its home.

---

## 5. Sources

- [Ensembl Tools](https://www.ensembl.org/info/docs/tools/index.html) — BLAST/BLAT, VEP, Assembly Converter, ID History Converter, Data Slicer, File Chameleon
- [UCSC Other Tools](https://genome.ucsc.edu/util.html) — the five main tools: Genome Browser, BLAT, **In-Silico PCR**, Table Browser, LiftOver
- [WormBase Enrichment Analysis](https://wormbase.org/tools/enrichment/tea/tea.cgi) — TEA (tissue), PEA (phenotype), GEA (GO)
- [FlyBase: enhanced orthology / DIOPT](https://flybase.org/commentaries/2016_03/enhanced_orthology.html)
- [PlanMine user guide](https://planmine.mpinat.mpg.de/planmine/user_guide.html) — InterMine-based; BLAST, GO, orthologues, expression
- [Echinobase (NAR 2022)](https://academic.oup.com/nar/article/50/D1/D970/6430489) — JBrowse + BLAST+, 1:1 ortholog maps
- [VEuPathDB (NAR)](https://academic.oup.com/nar/article/50/D1/D898/6413610) and [GO enrichment webinar](https://static-content.veupathdb.org/documents/27May2020VEuPathDBWebinarfinal.pdf) — strategies, enrichment, Transform by Orthology
- [InterMine list analysis](http://intermine.org/intermine-user-docs/docs/lists/list-analysis-pages/) and [list widgets](http://intermine.org/im-docs/docs/webapp/lists/list-widgets/index/)
- [Alliance of Genome Resources infrastructure update (Genetics 2024)](https://academic.oup.com/genetics/article/227/1/iyae049/7637331) — BLAST via SequenceServer, results linked to JBrowse and gene pages

---

## Related

`notes/PRIMER_BLAST_TOOL_PLAN.md` (the pairing/product-size half of recommendation 2),
`notes/USE_CASES_AND_HELP_ROUTER_PLAN.md` (the work that was scheduled for today),
`notes/QUERY_PERFORMANCE.md` and `notes/SEARCH_COST_MODEL_2026-07-31.md` (the fan-out cost any
cross-organism tool inherits), `notes/EXPRESSION_EXPLORER_PLAN.md`,
`config/tools_config.php` (where a new tool gets registered).
