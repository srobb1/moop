# Per-gene-set config file — planning notes

Not started. Captured 2026-09-14 after adding the homology-naming-fallback feature
(see `analysis_parsers/GeneNameInformativeness.pm` and the commit that added it) —
picking curated overrides back up made it obvious they're one of at least two
different "config for a specific organism/gene-set" mechanisms already living in this
pipeline, hardcoded a third way in each case. Notes only, nothing implemented.

## What already exists today, and where

**1. `build_gene_name_params()` in `process_one_geneset.sh` (~line 434-487)** — three
bash associative arrays, hand-edited directly in the script, keyed by the exact
`Organism/Assembly/GeneSet` triple:

```bash
BEST_MAPPING["Nematostella_vectensis/GCA_033964005.1/NV2"]="/n/sci/.../RefSeq_jaNemVect1.RBBH.moop.tsv"
BEST_MAPPING["Chamaeleo_calyptratus/CCA3/MENDER_20260701"]="/n/sci/.../apollo_moop.tsv"
NEXT_BEST_MAPPING["Montipora_capitata/HIv3/HIv3_geneset"]="/n/sci/.../RefSeq_jaNemVect1.RBBH.moop.tsv"
```

Three priority slots (`BEST_MAPPING` above OMA/RBBH/Swiss-Prot, `BEST_MAPPING_2` right
after it, `NEXT_BEST_MAPPING` below RBBH but above Swiss-Prot). There's also a dead,
commented-out `BEST_MAPPING_2` entry for Chamaeleo with a note calling its path stale
— exactly the kind of thing that rots silently in a script and wouldn't in a config
file with, say, a "last verified" field.

**2. `OMA_BASE` in `process_one_geneset.sh` (~line 359)** — a single hardcoded
absolute path, not even per-organism:

```bash
OMA_BASE="/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/OMA_v2"
OMA_DIR="$OMA_BASE/$THIS_ORG/$ASSEMBLY/$GENE_SET"
```

OMA is explicitly "not part of the big per-org pipeline... run by hand and symlinked
in" (the comment right above it), so the convention-based subpath
(`$OMA_BASE/$org/$asm/$geneset/{Output,mapGO}`) is a real assumption baked into the
script, not derived from anything.

**3. `metadata.yaml` next to the gene set's own data** (read via `read_meta_key()`,
~line 600) — a *third*, already-established mechanism, and it's per-gene-set config
that actually lives with the gene set's data rather than in a script:

```yaml
moop-strip-id-prefix: Bradypodion_ventrale_
moop-add-id-prefix:   BraVen_
active: true
```

**4. `paths.sh`** — global (not per-organism) constants, but already env-var
overridable (`: "${GENOMES:=...}"` etc.) — the one existing example of "config
outside the script."

## The real open question, not just "add a config file"

There are already three different places a per-gene-set setting can live (a script's
associative arrays, a script's hardcoded constant, `metadata.yaml`). Before adding a
*fourth*, worth deciding: does a new central config **replace** `BEST_MAPPING` et al.,
or does it also **absorb** `moop-strip-id-prefix`/`moop-add-id-prefix` out of
`metadata.yaml`? Those two already work fine and live with the data they describe —
there's a real argument for *not* moving them, and for the new file being specifically
"naming/homology overrides," not "all per-geneset config."

**Still open** — not decided in the 2026-09-14 discussion below.

## Discussion, 2026-09-14: format, location, and the reference-organism default

**Decided: keep `paths.sh` separate, don't fold it in.** `paths.sh` answers one
site-wide question ("where do the big data trees live" — `GENOMES`/`ANNOTATIONS`/
`REF_DB`, three scalars, env-var overridable, rarely changes). The new config answers
a per-gene-set question ("for THIS org/assembly/gene-set, what differs from the
default") — a lookup table that will grow and change far more often. Folding one into
the other would make the simple case require parsing whatever structured format the
lookup-table case needs, for no benefit. `paths.sh` stays exactly as it is.

**Decided (2026-09-14): nested YAML, keyed org → assembly → gene-set → settings**, mirroring the
actual `$GENOMES/$org/$asm/$geneset` directory structure, over a flat TSV with a
compound `"Org/Assembly/GeneSet"` string key (what `BEST_MAPPING` already does in
bash). The flat-string-key shape is exactly what already bit this pipeline once — a
bare-organism key silently matched the wrong gene set when `MENDER_20260701` replaced
an older Chamaeleo gene set (see the comment at `process_one_geneset.sh:435-440`).
Nested YAML structurally can't do that by accident — a same-shaped "org-level
default" block would have to be added deliberately. This also matches
`metadata.yaml`'s existing flavor: loosely YAML, hand-parsed by a small script rather
than a real YAML library, so no new dependency. Read it via a small Perl helper that
`process_one_geneset.sh` calls through command substitution (same pattern already
used everywhere, e.g. `GFF_SOURCE=$(perl -ne '...')`), not by parsing YAML in bash.

**Decided: default preferred reference organism for orthology naming = Human**,
overridable per org/assembly/gene-set via the new config. But the two places "Human"
is currently hardcoded in `build_gene_name_params()` don't generalize symmetrically:

```bash
local human_oma=(HUMAN.*.oma_orthologs.moop.tsv)
...
[ -f "Ensembl_Homo_sapiens.RBBH.moop.tsv" ] && PARAMS+=("Ensembl_Homo_sapiens.RBBH.moop.tsv")
```

- **Ensembl RBBH slot** generalizes cleanly now: it's just
  `Ensembl_<Org_Name>.RBBH.moop.tsv`, and `make_rbbh_moop()` already loops over
  whatever `ENS_*` reference targets a gene set's RBBH analysis actually has. Real
  reference organisms already present under `$REF_DB`: human, mouse, chicken,
  xenopus, medaka, anole, pogona, astyanax (+ cavefish), lamprey, C. elegans, yeast,
  E. coli. A config value naming the preferred organism slots straight into that
  filename template.
- **OMA slot** is messier: OMA orthologs are matched by species *code* (`HUMAN` in
  the live code — though a stale nearby comment uses `HOMSAP` instead for the same
  organism, an existing inconsistency worth cleaning up regardless), not by
  scientific name, and OMA codes aren't derivable from the Latin binomial.
  Generalizing this properly needs an org-name → OMA-code lookup table, which is its
  own small config problem, not just a rename.

**Scoping decision for the first pass**: generalize the **Ensembl RBBH slot** via the
new config now (low risk, clean mapping); leave the **OMA slot** Human-only for now.
`BEST_MAPPING` already covers the rare case where OMA-vs-Human isn't the right
signal (that's literally what it's doing for Nematostella today) — no evidence yet
that the OMA slot itself needs to be configurable beyond that.

**Concrete change list this implies** (not yet built):
1. New config file + a Perl resolver helper (org, assembly, geneset, field →
   resolved value or nothing), mirroring what `override_lookup()` does today but
   reading from the file instead of a hardcoded bash array.
2. `build_gene_name_params()`: replace the `BEST_MAPPING`/`NEXT_BEST_MAPPING` bash
   arrays with calls to the resolver. (`BEST_MAPPING_2` was removed from the script
   entirely on 2026-09-14 — see the open question below, now resolved: it's not
   coming back.)
3. `build_gene_name_params()`: resolve `preferred_reference_organism` (default
   `Homo_sapiens`), use it to build the `Ensembl_<org>.RBBH.moop.tsv` filename
   instead of the literal `Ensembl_Homo_sapiens.RBBH.moop.tsv`. OMA slot unchanged
   for now (see scoping decision above).
4. The OMA-directory section (`process_one_geneset.sh` ~line 356-426): resolve an
   OMA-dir override, defaulting to today's `$OMA_BASE/$org/$asm/$geneset` convention
   when the config has nothing for that gene set.
5. `paths.sh`: untouched.

**Resolved (2026-09-14): `BEST_MAPPING_2` does NOT carry forward into the new
config, and has already been removed from `process_one_geneset.sh` (dead code — it
had exactly one example and it was commented out and stale).** Checked the OMA discovery block
(`process_one_geneset.sh:354-426`) against what's actually on disk under `OMA_BASE`
(`/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/OMA_v2`): only 3 gene sets have output there
today (`Chamaeleo_calyptratus/CCA3/MENDER_20260701`, `Danio_malabaricus/assembly_2025/
geneset_1`, `.../geneset_2`), and Chamaeleo's `Output` symlink resolves to a real,
populated OMA run. That's why `BEST_MAPPING_2`'s one-ever example (a stale, commented-
out pre-OMA_v2 manual HOMSAP path for Chamaeleo) is unused: Chamaeleo now gets picked
up automatically by the discovery check, no manual file pointer needed. The check
itself is unconditional/automatic for every organism already — what's still manual is
running OMA itself and symlinking it into `OMA_BASE`. `BEST_MAPPING_2`'s only real
job, historically, was covering a gene set whose OMA run *doesn't* live at that
standard discovery path — which is exactly what the planned `oma_dir` override is for.
So `best_mapping_2` may be entirely redundant with `oma_dir` once that exists, rather
than a separate field to carry forward. **Not yet decided** — flagging it here rather
than silently dropping the slot.

## Example

Parse-checked (Python `yaml.safe_load`) — the shape resolves as intended, including
a real organism (`Chamaeleo_calyptratus/CCA3`) carrying two different gene sets with
different settings. `best_mapping`/`next_best_mapping` entries below are real,
migrated straight from today's `BEST_MAPPING`/`NEXT_BEST_MAPPING`. `oma_dir` and
`preferred_reference_organism` are illustrative only — shown to demonstrate the field
shape, not because anyone has decided to use them for these gene sets.

```yaml
# Per-gene-set naming/homology overrides.
# Key path: Organism -> Assembly -> GeneSet -> settings. Every setting is optional --
# anything not listed uses the pipeline's normal default.
#
# Precedence order (matches build_gene_name_params()'s PARAMS list today):
#   best_mapping > [OMA orthologs vs Human -- NOT configurable yet, see
#   preferred_reference_organism below] > [Ensembl RBBH vs
#   preferred_reference_organism, default Human] > next_best_mapping
#   > Swiss-Prot > PANTHER

Nematostella_vectensis:
  GCA_033964005.1:
    NV2:
      # Real -- migrated straight from BEST_MAPPING in process_one_geneset.sh today.
      best_mapping: /n/sci/SCI-003939-SBNVEC/genomes/Nvec200/aligned/tcs_v2/analysis/rbbh_2026_02_09/jaNemVect1/RefSeq_jaNemVect1.RBBH.moop.tsv

Chamaeleo_calyptratus:
  CCA3:
    MENDER_20260701:
      # Real -- migrated from BEST_MAPPING.
      best_mapping: /n/sci/SCI-004219-SBCHAMELEO/Chamaeleo_calyptratus/genomes/CCA3-ref/analysis/apollo_moop.tsv

    # --- Everything below this line is ILLUSTRATIVE ONLY: made-up gene set name
    # and a made-up path, just to show the shape of a field nobody has used yet. ---
    some_legacy_geneset:
      # This is the case the stale, commented-out BEST_MAPPING_2 example near
      # process_one_geneset.sh:445-448 was reaching for: a gene set whose OMA run
      # predates OMA_v2 and doesn't live under $OMA_BASE/<org>/<assembly>/<geneset>/
      # Output. Expressed here as an OMA_DIR override (points at a directory
      # containing Output/ and mapGO/), not a direct file. See the open question
      # above about whether this makes best_mapping_2 itself redundant.
      oma_dir: /path/to/a/pre-OMA_v2/manual/run

Montipora_capitata:
  HIv3:
    HIv3_geneset:
      # Real -- migrated from NEXT_BEST_MAPPING (lower priority than best_mapping,
      # still above Swiss-Prot/PANTHER).
      next_best_mapping: /n/sci/SCI-004111-SBCORAL/Montipora_capitata/genomes/Montipora_capitata_HIv3/analysis/RBBH/RefSeq_jaNemVect1.RBBH.moop.tsv

# --- ILLUSTRATIVE: nobody has asked for this yet, just showing the shape ---
Petromyzon_marinus:
  GCF_048934315.1:
    RS_2025_08:
      # Lamprey-to-fish orthology is more biologically meaningful as a naming
      # signal than lamprey-to-human. Danio_rerio is a real $REF_DB/ENS_danio_rerio
      # reference, so this is realistic -- just not a decision anyone has made.
      #
      # DEFAULT IS Homo_sapiens -- omit this field entirely to get today's
      # behavior. Setting it only redirects the Ensembl RBBH slot (the
      # Ensembl_<org>.RBBH.moop.tsv file). It does NOT touch the OMA slot,
      # which stays matched against Human regardless of this setting -- OMA
      # matches by species CODE, not organism name, and there's no
      # name-to-code lookup built yet. Don't set this expecting it to also
      # redirect OMA.
      preferred_reference_organism: Danio_rerio
```

## Candidate fields

**Confirmed real today (found by grep, not brainstormed):**
- The `BEST_MAPPING` / `NEXT_BEST_MAPPING` curated override file +
  its priority slot.
- OMA output directory — either overriding `OMA_BASE` globally, or (closer to what the
  stale commented-out TODO at line 445-448 was actually reaching for) a **per-gene-set
  override** for when a gene set's OMA run doesn't follow the standard
  `$OMA_BASE/$org/$asm/$geneset` layout (a pre-OMA_v2 manual run, e.g.).

**Decided (see the 2026-09-14 discussion above):**
- **Preferred reference organism for orthology-based naming**, default Human,
  override per org/assembly/gene-set. First pass scoped to the Ensembl RBBH slot
  only — see above for why the OMA slot doesn't generalize the same way.

**Brainstormed, not yet confirmed as a real pain point — flagging for you to weigh in:**
- **Force a specific `GFF_SOURCE`.** The refseq/ensembl/other auto-detect
  (`process_one_geneset.sh` ~line 496) is a regex sniff of the first gene line. No
  known failure case yet, but if one ever needs a manual override, that's a config
  field, not a script edit.
- **Per-organism informativeness exceptions**, now that
  `GeneNameInformativeness.pm` exists — e.g. a clade where a pattern on the shared
  list is actually a real name, or an organism-specific placeholder convention not on
  the shared list. No evidence this is needed yet; noting it because it's the natural
  next knob now that the mechanism exists.

**Your addition:** the OMA output directory, above.

**What else should go in?** — that's the open question back to you.
