# Assemblies with no gene set — design a real path instead of inventing `v1`

Status: **note only, nothing implemented.** Raised by the user 2026-07-20 during the
manage_groups audit: *"we should make a new path when an organism does not have a gene set."*

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
