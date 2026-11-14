# Assemblies with no gene set — design a real path instead of inventing `v1`

Status: **note only, nothing implemented. Deliberately deferred — do not start this without
the user.** Raised by the user 2026-07-20 during the manage_groups audit: *"we should make a
new path when an organism does not have a gene set."*

**The user's framing, which is the point of the whole note:** the site now *requires* a
gene-set level of organization, so **inventing a fake gene set is probably not good**. That is
the argument against the current behaviour and against option (C) below — if gene-set level
organization is load-bearing, then a fabricated gene set is not a harmless placeholder, it is a
fake row in the layer everything else keys off. The user wants to go over this properly later.

Related: `project_admin_page_audit` memory (THE METHOD), `plan_gene_set_pages`,
`project_gene_set_refactor` (the gene_set layer this all sits on).

---

## The state we have no design for

An assembly directory that exists and has data — typically `genome.fa` uploaded — but **no
gene-set subdirectory yet**:

```
organisms/Some_organism/GCA_123456.1/
    genome.fa           <- present
    genome.fa.fai       <- present
    (no gene-set subdir at all)
```

This is a **normal, expected, transient state**. It is exactly where an organism sits between
"upload the assembly" and "add a gene set" in the Organism Checklist workflow. It is also the
permanent resting state for a reference genome that will never get its own annotation.

The gene_set layer (`project_gene_set_refactor`) made `gene_set` part of the identity of almost
everything — access control, DB rows, file paths, group entries, tool URLs. The tuple is
`(organism, assembly, gene_set)`. **What that tuple is when there is no gene set was never
decided**, so the code invented an answer.

## What the code does today: invents `v1`

`v1` is a magic string appearing at **48 sites** (42 PHP, 6 JS). Critically:

**There are ZERO directories named `v1` anywhere in the tree.** All 98 group entries carry a
real gene-set name (`SIMR_2025-01-24`, `RS_101`, `kc1`, …). So `v1` is not a legacy convention
being honoured — it is a placeholder that never corresponded to anything.

Three categories, in descending order of harm:

### 1. Invents a gene set from nothing (3 sites) — the sharp edge
```php
lib/functions_access.php:412   $gene_sets = array_map('basename', $subdirs) ?: ['v1'];
admin/api/quick_add_group.php:52   if (empty($gene_sets)) $gene_sets = ['v1'];
admin/manage_users.php:67      $orgs_with_gene_sets[$org][$asm] = ['v1'];
```
These fire precisely when an assembly has **no** gene-set subdirs, and fabricate a tuple
pointing at a directory that does not exist.

`functions_access.php:412` is `getUnrepresentedGeneSetTuples()`, which feeds the
**"Assemblies Without Groups"** table on manage_groups. So the loop closes on itself:

1. `getUnrepresentedGeneSetTuples()` invents `org/asm/v1`.
2. manage_groups **offers it to the admin** as a row to assign groups to.
3. The admin clicks "Add Groups" → an entry for `org/asm/v1` is written to
   `organism_assembly_groups.json`.
4. manage_groups then computes `_fs_exists` via `is_dir(.../v1)` → false → the same page
   **flags the entry it just told the admin to create** as stale/missing.

`manage_users.php:67` does the same in the access-granting UI, so an admin can grant a
collaborator access to a gene set that does not exist.

### 2. `$_POST`/`$_GET` defaults (9 sites)
`jbrowse_register_assembly.php:28`, `generate_blast_indexes.php:19`,
`generate_feature_coords.php:15`, `jbrowse_reprep_gff.php:28`,
`retrieve_selected_sequences.php:49`, `manage_groups.php:136/171/204`, `get_gff.php:35`
(also documented as the default in its docblock, line 12).

A request that omits `gene_set` silently operates on `.../v1/` — a path that does not exist —
so the endpoint fails on a *missing file* rather than on a *missing parameter*, and the error
message points at a directory the admin never created.

### 3. Read-side display/matching defaults (~15 PHP `?? 'v1'`, ~6 JS `|| 'v1'`)
`manage_groups.php` ×6, `organism_checklist.php` ×3, `manage_users.php` ×2,
`functions_access.php` ×2, `functions_data.php:1057`, `parent.php:171`,
`gene_set_functions.php:312`, plus `manage-groups.js` ×5 and `gene-model-viewer.js:595`.
Also a function-signature default: `blast_functions.php:801`
`generateBlastIndexes(..., $gene_set = 'v1')`.

These mostly cannot fire today (every entry has a real gene_set) but they mask the state:
a missing gene_set renders as the string `v1` in the UI instead of as "none".

## Current exposure: latent, not live

- 0 of 96 assemblies have zero gene-set subdirs.
- 0 phantom `v1` entries exist in `organism_assembly_groups.json`.
- 0 `v1` directories on disk.

So nothing is broken right now. It becomes reachable the moment someone uploads an assembly
and visits manage_groups or Manage Users before adding a gene set — i.e. the normal checklist
order.

## The actual design question

**What is the identity of an assembly with no gene set?** Pick one and apply it everywhere:

- **(A) `gene_set = null`/`''` is a first-class value.** The tuple becomes
  `(organism, assembly, null)` meaning "assembly-level, no annotation". Requires auditing every
  `?? 'v1'` for what null should mean there, and a display convention ("— no gene set"). Most
  honest; touches the most code. Note `is_public_gene_set()` already compares
  `($entry['gene_set'] ?? '') === $gene_set`, so `''` is partially precedented.
- **(B) Such an assembly is simply not a groupable/grantable unit.** The three invention sites
  return nothing instead of `['v1']`; manage_groups and Manage Users just don't list it, and the
  Organism Checklist is the place that tells you it needs a gene set. Smallest diff, and it
  stops the self-inflicted loop immediately. Risk: an assembly could become invisible in the
  admin UI with no explanation of why — so it needs a checklist/"needs setup" pointer to
  compensate.
- **(C) Keep a placeholder but make it real.** Auto-create a default gene-set directory on
  assembly registration. Rejected on sight: it puts fabricated structure into the data tree,
  which is the opposite of the direction the tree has been moving.

**Leaning (B) for the three invention sites regardless of the wider decision**, since it is
contained and stops the UI offering the admin a broken action. (A) is the fuller answer for the
read-side defaults and can follow.

But note the user's framing points at a question upstream of A/B/C: **if gene-set level
organization is now required, is an assembly with no gene set a valid state to display and
operate on at all, or is it an incomplete-setup state that only the Organism Checklist should
surface?** Answer that first — it decides whether (A) is even wanted, or whether (B) plus a
clear "needs a gene set" pointer is the whole answer. Do not pick from A/B/C in isolation.

## Before implementing

Per THE METHOD, verify against reality rather than trusting this note:
- Re-run the counts (`grep -rn "'v1'"`) — 48 sites is a 2026-07-20 figure.
- Create a real assembly dir with a genome and no gene set on a scratch organism and drive
  manage_groups + Manage Users **as apache** to confirm the phantom row appears as described.
  The loop above is derived from reading the code; it has NOT been reproduced live, because no
  such assembly currently exists.
- Whatever is chosen, `getUnrepresentedGeneSetTuples()` and `quick_add_group.php` must agree —
  they are two implementations of the same "which tuples exist" question, and that duplication
  is how they would drift apart again.

---

## 2026-11-13 — a real use: BLAST-only genomes (Sofia)

Planosphere (and SIMRbase) want genomes that are offered **for BLAST only** — e.g. every
*S. mediterranea* assembly, with no gene set loaded. This answers the open question above: an
assembly without a gene set **is** a valid state to display and operate on, with a narrow purpose.
Candidates (Sofia 2026-11-13; today offered as downloads only):
- the **chromosome analysis assembly** — `planosphere/downloads/smed_chr_ref_v1.fa.gz` (220 MB), on Planosphere's
  Chromosomes page;
- **SmedSxl genome v3.1** — `moop/downloads/smedgd/SmedSxl_genome_v3.1.nt.gz` (258 MB), linked from Planosphere's
  SmedGD pages. (v4.0 and SmedAsxl v1.1 sit beside it — same treatment if wanted later.)
JBrowse: "maybe" (Sofia) — a sequence-only view so a BLAST hit can be seen in place; decide when building.

**What it would be:** genome FASTA + BLAST index, nothing else. No gene search, gene pages or
annotations. Optional: a small assembly page (source, size stats) and a JBrowse view with the
sequence only. BLAST hits on it link to the JBrowse location, not a gene page — the one real
behaviour change in BLAST results.

**How:** option (A) above — "no gene set" as a first-class value, not the invented `v1` — and
build it TOGETHER with the per-data-set permission layer (todo: data set = organism / assembly /
gene set / analysis). A BLAST-only genome is a data set that stops at the assembly level; if the
shared access check handles that from the start, groups, scope selector and BLAST follow from it.

**Rejected:** a separate "extra BLAST databases" list in config. Quick, but it bypasses the
organism tree, so access control, the scope selector and the Organism Checklist would each need
a special case — another drifting second source of truth.

**Before building:** make one on a scratch organism and drive manage_groups / BLAST as apache;
the phantom-`v1` loop above is still derived from code, not reproduced.

---

## 2026-11-14 — decided (Sofia), and the work list (permissions plan step 6)

**Decisions:** (1) BLAST-only **genomes** now; BLAST-only gene sets (FASTA not loaded in organism.sqlite,
with or without a genome) next, same mechanism. (2) Only under organisms that already exist on the site
(have organism.sqlite) — no BLAST-only organisms. (3) Hits link to a **sequence-only JBrowse** view.

**Found 2026-11-14 (code read, still to reproduce on a scratch organism as apache):**
- BLAST's database list comes from `getAccessibleGeneSets()` (lib/functions_access.php:328): groups-file
  entries, `gene_set ?? 'v1'`, and it REQUIRES a gene-set dir with FASTA — so an assembly with no gene set
  never appears. That is the one real blocker.
- `getBlastDatabases($path)` already finds `genome.fa` (pattern from sequence_types), and genome hits already
  link to JBrowse, not a gene page (tools/blast.php:291 `is_genome_db`, lib/blast_results_visualizer.php:482).
- `moop_dataset_visible()` rejects `gene_set = ''` (regex) — must accept it as "the assembly itself".
- `'v1'` inventions: 41 PHP + 8 JS lines (was 48 total on 07-20).
- BUG to fix with it: lib/blast_results_visualizer.php:503 builds `'/' . $site . '/jbrowse2.php'` (CLAUDE.md §3b
  — must be moop_url()).

**Work list (one commit each, tests with each):**
1. Reproduce first: scratch organism dir with genome.fa and no gene set; drive Manage Groups + BLAST as apache.
2. Identity: a groups entry `{"organism","assembly","gene_set": ""}` = the assembly as a BLAST-only data set.
   Valid only when the assembly dir has a genome FASTA (or its BLAST index) and the organism has organism.sqlite.
3. Access: `is_public_gene_set` / `has_gene_set_access` / `moop_user_access_allows` with `''` (granted by
   `{asm: true}`, `'*'`, or an explicit `''`); `moop_dataset_visible($org, $asm, '')` = assembly dir +
   that access. `moop_jbrowse_can_open_assembly` must count it.
4. `getAccessibleGeneSets()`: `''` -> path = assembly dir, needs the genome; identity from the DB's genome row if
   present, else the assembly dir name; mark `blast_only`. Check every caller that assumes a DB gene set
   (search scope `moop_search_permitted_scope`, MOOPmart, Primer tools, expression picker) skips it.
5. Labels: `gene_set_label()` -> "<genome> — genome only (BLAST)"; scope selector + BLAST page.
6. Admin: Manage Groups lists/creates `''` entries ("whole assembly — BLAST only"); `getUnrepresentedGeneSetTuples`,
   `admin/api/quick_add_group.php`, `admin/manage_users.php` stop inventing `v1` (offer `''` for an assembly with
   a genome and no gene set — kills the phantom loop). Access overview: a provider for BLAST-only genomes.
7. JBrowse: register a sequence-only assembly (no gene track); confirm the genome-hit link opens it.
8. Organism Checklist AND Manage Organisms: a BLAST-only assembly is "Genome only — BLAST", not broken.
   Manage Organisms lists every subdir, so it already appears -- but today it would be misreported three ways:
   (a) no genome row in organism.sqlite -> 'orphaned_assembly_directory' (lib/functions_filesystem.php:300),
   organism marked invalid + dashboard "orphaned" pointer; (b) status graded on gene sets (`gene_sets_complete`)
   -> Incomplete; (c) BLAST-index check walks gene-set subdirs only (lib/organism_cache.php:278) -> the
   genome's own index at assembly level is not counted. Its own checks instead: genome FASTA, BLAST index, .fai.
   OPEN (decide at start): how it is recognised -- `genome.json` declares it (lean: organism page/checklist
   then need no groups file) vs. "its groups entry has gene_set ''". Bump ORGANISM_CACHE_SCHEMA_VERSION.
9. Fix the visualizer link (§3b). Then the remaining read-side `?? 'v1'` defaults, one by one.
First candidates: smed_chr_ref_v1 (Planosphere chromosomes), SmedSxl genome v3.1.

**2026-11-14 — items 1–4 done.** Reproduced hermetically (temp organism tree, mocked session): the
"one real blocker" above was WRONG — an explicit `gene_set: ""` entry already passed
`getAccessibleGeneSets()` (the `?? 'v1'` never fires on `''`), but only by accident: path `asm/`
with a trailing slash, ANY `*.fa` qualified, `genome_accession` null; access (`{asm:true}`, `'*'`,
public flag) already granted `''`. `moop_dataset_visible()` did reject it. Now: `moop_blast_only_genome()`
(includes/access_control.php) is the one validity rule; `getAccessibleGeneSets(..., $include_blast_only)`
returns them only to callers that ask — `tools/blast.php` and `moop_jbrowse_viewer_gene_sets()`; every
other caller (search, MOOPmart, downloads, Primer, expression) is unchanged by construction, so the
per-caller audit in item 4 is not needed. Session cache key bumped to `_v2`. Next: item 5 (labels).

**2026-11-14 later — item 5 done + Downloads.** Downloads page opts in (b2966a2a's next commit): a BLAST-only
genome's genome.fa is offered at its assembly. Source list badge "genome only" (BLAST page). `gene_set_label()`
left alone: its only caller (expression) never receives BLAST-only genomes.
**Item 6 scoped:** the real breakage is JS `getAttribute('data-gene-set') || 'v1'` — '' is falsy, so a
BLAST-only row is saved/deleted as v1 (js/modules/manage-groups.js:231,371,463,661,685;
admin/pages/manage_users.php:560,900,930). PHP `$_POST['gene_set'] ?? 'v1'` is fine with an explicit ''.
`getUnrepresentedGeneSetTuples()` (lib/functions_access.php:589): no subdirs -> offer '' only when
moop_blast_only_genome() passes, else nothing (kills the phantom v1). Then quick_add_group.php:52,
manage_users.php:36-67, lib/functions_data.php:1630. Verify in a browser (Manage Groups add/save/delete of a
'' row, Manage Users grant).
**Item 8 also:** BLAST indexes for a BLAST-only genome — no prompt or button today (checklist walks gene-set
dirs only); generateBlastIndexes() works with gene_set '' (path = asm dir) but the API defaults to 'v1'
when the param is absent. The checklist row must offer Build BLAST Index / .fai.
