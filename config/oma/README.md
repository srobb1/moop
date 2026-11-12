# OMA standalone: reference set and species trees

Each datastore organism is run through OMA standalone 2.7.0 against a fixed set of reference
genomes. The runs are for:

1. **Human orthologs**, to name new proteins after their human gene whenever one exists.
2. **GO terms** transferred from well-annotated references (`mapGO/`).

This file explains the reference set and the species trees. For the step-by-step run, see
[`HOWTO_NEW_SPECIES.md`](HOWTO_NEW_SPECIES.md).

---

## 1. Default reference set (10 species)

Template: `BRAFL_CALMI_CAPTE_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE_NEMVE/OMA.2.7.0`. It is a
single OMA browser export, so the genomes and the precomputed all-against-all in `Cache/AllAll`
(55 pairs) come from one consistent release.

| Code | Species | Group | Source release | Proteins | Why it is in the set |
|---|---|---|---|---:|---|
| `HUMAN` | *Homo sapiens* | Mammalia | Ensembl 102, GRCh38 | 103,200 | The naming anchor; most curated GO |
| `MOUSE` | *Mus musculus* | Mammalia | Ensembl 104, GRCm39 | 67,158 | Second mammal; curated GO |
| `LEPOC` | *Lepisosteus oculatus* (spotted gar) | Actinopterygii, Holostei | Ensembl 74, LepOcu1 (2013) | 18,893 | Ray-finned fish that diverged **before the teleost genome duplication**: separates teleost duplicate pairs from older paralogs |
| `CALMI` | *Callorhinchus milii* (elephant shark, a chimaera) | Chondrichthyes, Holocephali | Ensembl Vertebrates 51 | 49,321 | Cartilaginous fish: the jawed-vertebrate level |
| `BRAFL` | *Branchiostoma floridae* (amphioxus) | Cephalochordata | RefSeq GCF_000003815.2 | 43,041 | Invertebrate chordate that diverged **before the vertebrate 2R duplications**; slow-evolving, and the nearest reference for lamprey, tunicates, echinoderms and acorn worms |
| `DROME` | *Drosophila melanogaster* | Ecdysozoa | Ensembl 106, BDGP6.32 | 30,719 | Curated GO; the ecdysozoan |
| `LOTGI` | *Lottia gigantea* (owl limpet) | Spiralia, Mollusca | JGI Sbi1_4 filtered models | 23,514 | Slow-evolving spiralian; anchor for molluscs and flatworms |
| `CAPTE` | *Capitella teleta* | Spiralia, Annelida | Ensembl Metazoa 27 | 31,325 | Second spiralian, which splits up the long flatworm branch (~25 planarian gene sets) |
| `NEMVE` | *Nematostella vectensis* | Cnidaria, Anthozoa | RefSeq GCF_932526225.1 (jaNemVect1.1) | 32,370 | Non-bilaterian animal: the animal-wide level, and the anchor for cnidarians and sponges |
| `MONBE` | *Monosiga brevicollis* | Choanoflagellata | JGI v1.0 | 9,164 | Single-celled sister group of animals: the root |

Total: 408,705 reference proteins.

**Where the GO terms come from:** almost all curated (non-IEA) GO annotations are on HUMAN, MOUSE
and DROME. The other references carry mainly electronic (IEA) or phylogenetically inferred
(IBA) annotations. For example, in an Acropora run HUMAN had 715k non-IEA annotations,
DROME 208k, MONBE 16k, CALMI 122 and LOTGI 0. The other references therefore improve GO
transfer by improving the **orthology**, not by adding annotations.

**Data caveats:**
- HUMAN is Ensembl 102 (2020), and its FASTA headers carry the description and HGNC ID
  (e.g. `HGNC:20773`) but no gene symbol. Look the HGNC ID up in the current HGNC table to get
  today's symbol and name, e.g. `TUBB8`, "tubulin beta 8 class VIII". The table is
  `/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/hgnc/` (`hgnc_complete_set.txt`, plus
  `withdrawn.txt` for merged or withdrawn IDs). Of the 19,809 HGNC IDs in HUMAN, 12 have been
  withdrawn or merged and 367 have had a symbol change since Ensembl 102 (as of Sep 2026).
- LEPOC is a 2013 annotation with few isoforms. A gene missing from it can look like a loss
  at the ray-finned-fish level.

---

## 2. Species tree: fixed, not estimated

Each run gets a **fixed species tree** in `parameters.drw` (`SpeciesTree := '<newick>';`).
`make_oma_db_files.pl` writes it from `species_trees.tsv`. OMA ignores `OutgroupSpecies` when
a tree is given; that setting only applies to `SpeciesTree := 'estimate'`.

**Why:** HOGs are built along the species tree, and with this taxon sampling OMA's estimated
trees were wrong in every earlier run:

| Earlier run | Estimated tree (branch lengths removed) | Problem |
|---|---|---|
| Acropora | `(MONBE,(((ACRMIL,(mammals,CALMI)),LOTGI),DROME))` | Coral placed inside Bilateria, next to the vertebrates |
| Nematostella | `(MONBE,((NEMVEC,(LOTGI,vertebrates)),DROME))` | Sea anemone inside Bilateria |
| Danio rerio | `(((MONBE,DROME),LOTGI),(DanRer,(mammals,CALMI)))` | Wrong root; zebrafish placed outside shark + mammals |
| Chamaeleo | `(((MONBE,DROME),LOTGI),((mammals,CHACAL),CALMI))` | Wrong root; fly grouped with Monosiga |

These are long-branch artefacts: fast-evolving DROME is pulled towards MONBE, and slow-evolving
cnidarians and LOTGI are pulled towards the vertebrates. Rooting with `OutgroupSpecies` cannot
fix a wrong unrooted tree. The relationships among these ten species are well established, so
the tree is given instead of estimated.

The pairwise orthologs (`Output/PairwiseOrthologs/`) do not depend on the species tree; the HOGs
(`HierarchicalGroups.orthoxml`) and HOG-based GO transfer do.

### Reference backbone
```text
MONBE ──────────────────────────────────────── choanoflagellate (root)
  └─ Metazoa
      ├─ NEMVE ─────────────────────────────── Cnidaria
      └─ Bilateria
          ├─ Protostomia
          │   ├─ DROME ─────────────────────── Ecdysozoa
          │   └─ Spiralia
          │       ├─ LOTGI ─────────────────── Mollusca
          │       └─ CAPTE ─────────────────── Annelida
          └─ Chordata
              ├─ BRAFL ─────────────────────── Cephalochordata
              └─ Vertebrata (jawed)
                  ├─ CALMI ─────────────────── Chondrichthyes
                  └─ Osteichthyes
                      ├─ LEPOC ─────────────── Actinopterygii
                      └─ HUMAN, MOUSE ──────── Mammalia
```
Newick: `(MONBE,(NEMVE,((DROME,(LOTGI,CAPTE)),(BRAFL,(CALMI,(LEPOC,(HUMAN,MOUSE)))))));`

### Where the target goes (`species_trees.tsv`)
`make_oma_db_files.pl` reads the target's `ncbi-taxon-id` from the datastore `metadata.yaml`,
walks up the NCBI taxonomy (`/n/sci/SCI-004223-SBGENOMES/dev/smr_dev/moop/ncbi_taxonomy/`, shared with the moop pipeline) and uses the most specific matching row.

| Lineage | NCBI taxa | Target placement | Datastore gene sets (Sep 2026) |
|---|---|---|---:|
| `tetrapod` | Tetrapoda | sister to `(HUMAN,MOUSE)` | 56 (bats, wallaby, chameleons) |
| `ray_finned_fish` | Actinopterygii | sister to `LEPOC` | 3 |
| `cartilaginous_fish` | Chondrichthyes | sister to `CALMI` | 0 |
| `cyclostome` | Cyclostomata | sister to jawed vertebrates | 3 (lamprey) |
| `tunicate` | Tunicata | sister to vertebrates | 0 |
| `cephalochordate` | Cephalochordata | sister to `BRAFL` | 0 |
| `ambulacrarian` | Echinodermata, Hemichordata | sister to Chordata | 2 (sea cucumber, acorn worm) |
| `mollusc` | Mollusca | sister to `LOTGI` | 1 (Congeria) |
| `annelid` | Annelida | sister to `CAPTE` | 0 |
| `flatworm` | Platyhelminthes | sister to `(LOTGI,CAPTE)` | 25 (planarians) |
| `ecdysozoan` | Ecdysozoa | sister to `DROME` | 1 (see overlaps below) |
| `cnidarian` | Cnidaria | sister to `NEMVE` | 6 |
| `sponge` | Porifera | sister to all other animals except MONBE | 2 |

**Caveats:**
- `tetrapod` is right for reptiles, amphibians, birds and non-Euarchontoglires mammals (bats,
  marsupials). A **primate or rodent** target sits inside `(HUMAN,MOUSE)`: give it a custom tree.
- `flatworm` follows the current consensus: Platyhelminthes are sister to Mollusca + Annelida.
- `sponge`: whether sponges or ctenophores branched first does not matter here, since the set
  has no other non-bilaterian besides NEMVE.
- Rows are matched by NCBI taxid. To support a new lineage, add a row with a `TARGET` leaf; the
  script rejects any tree whose leaves are not all in `DB/`.

---

## 3. What this set does not cover

- **Non-animals:** *Medicago sativa*, *Medicago truncatula* and *Bradyrhizobium diazoefficiens* have no
  lineage here. An animal reference set with a choanoflagellate root cannot place them. They need
  their own set, or are named from Swiss-Prot and PANTHER only.
- **Gene sets that ARE a reference genome** (handled automatically). A gene set whose proteins
  are ≥ 50% identical to one reference genome is that genome, and must not be an OMA target
  (it would be in OMA twice). Today: *Nematostella vectensis* GCF_932526225.1 (RS_101) = `NEMVE`
  (100%) and *Drosophila melanogaster* FB_Rel_6.54 = `DROME` (99.5%). Nobody has to remember this:
  - `make_oma_db_files.pl` compares sequences with every reference in `DB/`. For a reference
    genome it stops, writes only `REFERENCE_GENOME.txt`, and tells you not to run OMA there.
    10–50% identical (e.g. Nematostella NV2: 17.9% `NEMVE`, same species in another annotation)
    is only a warning; that gene set is a normal target.
  - The moop pipeline makes the same check for any gene set without OMA output of its own, and
    takes a reference genome's orthologs from the template's **reference run**
    (`<template>/reference_run`: OMA on the reference genomes alone, backbone tree), with every
    OMA id mapped back to the gene set's own protein ids by identical sequence.
  - `oma_status_check.sh` shows such gene sets as "reference genome … uses the reference run".
  - The reference run is made once per template with `make_reference_run.sh`, then its part 1
    and part 3 (no part 2: the reference all-against-all is precomputed in the template), then
    `mapGO/get_OMA_GO_terms.sh NEMVE` for `go.tsv`. Such gene sets also get an OMA2GO table:
    the reference genome's exported GO annotation plus OMA's predictions, on their own ids.

---

## 4. Files

The scripts and these notes live in the moop repo, `config/oma/`. They read their paths from
`oma.conf` next to them (not in git; copy `oma.conf.example`). The data stays where it was: the run
folders under `OMA_v2/`, and the templates, reference runs and setup/submit logs under
`OMA_v2/OMA_TEMPLATE_RUNS/` (`OMA_TEMPLATES` in oma.conf).

| Path | What it is |
|---|---|
| `OMA_TEMPLATE_RUNS/BRAFL_CALMI_CAPTE_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE_NEMVE/` | **Default template.** Copy its `OMA.2.7.0/*` into a new run dir |
| `setup_oma_runs.sh` | Sets up runs for one gene set or a list: run dir, template copy, DB files, species tree; prints the submit commands |
| `submit_oma_runs.sh` | Submits parts 1, 2, 3 of set-up runs, chained; skips runs submitted before |
| `species_trees.tsv` | Lineage → species tree with a `TARGET` placeholder |
| `make_oma_db_files.pl` | Writes `DB/<PREFIX>.fa`, `DB/<PREFIX>.splice` (only when there are isoforms), and the `SpeciesTree` line; stops with `REFERENCE_GENOME.txt` for a reference genome (§3) |
| `make_reference_run.sh` | Sets up `<template>/reference_run` (§3) |
| `<template>/reference_run/` | OMA on the template's reference genomes alone; used for gene sets that are a reference genome |
| `oma_status_check.sh` | Progress of every run under `OMA_v2` and the next command for each |
| `HOWTO_NEW_SPECIES.md` | Step-by-step run instructions |
| `CALMI_DROME_HUMAN_LOTGI_MONBE_MOUSE/`, `BRAFL_DROME_HUMAN_LEPOC_LOTGI_MONBE_MOUSE/` | **Legacy templates, do not use for new runs.** Their `parameters.drw` outgroup options are wrong (see below) |

### Which template a run used
Each template's `OMA.2.7.0/README.exportedAllAll` ends with an `OMA template: <name>` block
(plus the export file and the template's status). It is copied into every run made from the
template. Runs made before that block was added can be identified by the genome list at the top
of their `README.exportedAllAll`. `oma_status_check.sh` shows the template for each run and
flags anything that is not the default template as `(legacy)`.

### About the legacy templates
Runs before Sep 2026 used the 6-species CALMI template with `SpeciesTree := 'estimate'` and
hand-picked `OutgroupSpecies`. OMA requires the outgroup to be a group that branches off at the
root. Several suggested options, e.g. `['LOTGI','MONBE']` or `['DROME','MONBE']`, do not form
such a group, and `['LOTGI','DROME','MONBE']` roots the tree between vertebrates and everything
else. Those runs are being redone on the default template.
