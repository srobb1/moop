# Group / taxonomy consistency checker

Status: **idea recorded, not built.** Raised by the user 2026-07-23 after noticing that
`Cnidaria` means two different sets depending on which list you click it from. Deferred
deliberately — "any discrepancies is something I'll have to address in the future".

---

## The two kinds of grouping

MOOP groups organisms two ways, and they overlap by name:

| | Source | Membership |
|---|---|---|
| **Curated group** | `metadata/organism_assembly_groups.json` | hand-assigned per gene set |
| **Taxonomy rank** ("virtual group") | `metadata/taxonomy_tree_config.json` | derived from NCBI lineage |

Both render through `tools/groups.php` — `?group=` and `?taxonomy_rank=` respectively.

A name can be **both**, and then the two sets can disagree. The curated group is the one
that can be wrong, because it is maintained by hand.

## The worked example (measured 2026-07-23)

On the *Nematostella vectensis* organism page, `Cnidaria` appears in **two** lists:

- **Taxonomy Lineage** → `?taxonomy_rank=Cnidaria` → **4 organisms**
- **Member of Groups** → `?group=Cnidaria` → **3 organisms**

The missing one is *Scolanthus callimorphus*:

```
Scolanthus_callimorphus   groups = ['Sea anemone']
Nematostella_vectensis    groups = ['Cnidaria', 'Sea anemone']
```

Every other sea anemone carries **both** tags. Scolanthus only got `Sea anemone`, so it
drops out of the curated Cnidaria group while remaining a cnidarian taxonomically. This
looks like an oversight when it was added, not a decision.

**Nothing is currently lying to the user** — each badge matches its own destination (the
lineage chip says 4 and shows 4; the group chip says 3 and shows 3). The inconsistency is
in the data, so no display fix is warranted; a data check is.

## Decisions already made — do not re-litigate

- **Curated groups win the `?group=` URL.** `tools/groups.php` redirects `?group=X` to
  `?taxonomy_rank=X` only when **nothing at all** is filed under X (that is what makes
  `?group=Mammalia` work). It must NOT redirect a name that is both, or the manual group
  becomes unreachable and the "Member of Groups" chip would land somewhere other than the
  group it claims membership of.
- **Clicking a rank in Taxonomy Lineage goes to the virtual group**, which is what the
  user wants: it shows the complete set, and showing fewer than we hold is the thing to
  avoid.

## ⭐ Prevention beats detection — the user expects to repeat this

User, 2026-07-23: *"I can see me making this issue again."*

That reframes the work. A checker finds the mistake weeks later, on a page nobody thinks
to open; the mistake is made in **Manage Groups**, at the moment a gene set's groups are
being ticked. Catch it there and the checker becomes a safety net rather than the plan.

**Primary: an inline hint in Manage Groups.** When editing a gene set's groups, look up
that organism's taxonomy lineage, intersect it with the existing curated group names, and
surface any that are *not* ticked — "this organism is also in Cnidaria, which is a group
here. Add it?" One click to accept, easy to ignore.

Why this shape:
- It fires while the admin has the context in their head, not later.
- It suggests only names that are **already curated groups**, so it never invents groups
  or pushes the vocabulary toward being a taxonomy — the informal groups (`Sea anemone`,
  `Bats`, `Corals`) stay untouched.
- It is a **suggestion, never an auto-tick**. Same reasoning as the checker below: group
  membership is editorial. Silently adding a group would be worse than the current gap,
  because the admin would stop trusting what the file says.
- The lookup is the same intersection the checker does, so build that logic once and use
  it in both places.

**Secondary: the checker**, for gene sets added before the hint existed, and for the
inverse cases the hint cannot see.

## What the checker should do

Find curated groups that disagree with taxonomy, so the admin can decide — it must
**report, never auto-fix**: group membership is an editorial call (curated groups are not
required to be taxonomic; `Sea anemone`, `Bats` and `Planaria` are informal, and `Corals`
deliberately is not a rank).

Candidate checks:

1. **Organism in a rank but not in the same-named curated group.** The Scolanthus case.
   Highest value — this is the one that hides data from someone browsing by group.
2. **Organism in a curated group whose name is a rank it does not belong to.** The inverse;
   likely a mis-assignment.
3. **Gene sets of the same organism with differing group sets.** `Nematostella` RS_101 and
   NV2 both carry `['Cnidaria','Sea anemone']`; a mismatch between an organism's own gene
   sets is almost certainly an accident.
4. **Group named identically to a rank, with a smaller membership** — the summary form of
   (1), good for a dashboard count.

## Where it belongs

An admin page or a section on Manage Groups, not a housekeeping task that reports a number
nobody can act on. Per CLAUDE.md §10 the dashboard is a router: if it gets a card at all it
should say "N groups disagree with taxonomy -> go look" and link to the detail.

⚠️ It reads two metadata files and walks the taxonomy tree; it does **not** need the
organism databases, so it is cheap and can run live on page load rather than being cached.

Helpers that already exist and should be reused rather than rewritten:
`groupNameExists()`, `taxonomyRankExists()`, `getOrganismsAtTaxonomyLevel()`,
`getAccessibleOrganismsInGroup()` (all `lib/functions_data.php`).

Related: `notes/ASSEMBLY_WITHOUT_GENE_SET_PLAN.md` (the other metadata-consistency gap).
