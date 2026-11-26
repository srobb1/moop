# Closest-species searches to add per gene set (notes, 2026-10-02)

"Additional BLASTs": a `closest_species` entry in `scripts/geneset_config.yaml`, fed by
`scripts/closest_species_rbh.sh` (MMseqs2 reciprocal best hits) and `scripts/closest_species_diamond.sh`
(DIAMOND, 17 columns) against ONE other species' proteome, and by the OMA run when the species is in the
template (`oma_code`). It gives every gene a "closest <species> gene" (GFF attributes, a Closest Gene table,
a column of naming_decisions.tsv). `use_for_names: false` = shown, never used to name.

Status of each row: DECIDED (user) or PROPOSED (Claude, not yet agreed).

**DECIDED 2026-10-02 (user): no closest species is used for naming, for any gene set, for now.** Every entry is `use_for_names: false`.

| Gene sets | Closest species | Names? | Source of the partner proteome | Status |
|---|---|---|---|---|
| Flatworms (all but S. mediterranea itself) | *Schmidtea mediterranea* | no | genomes/v2 Schmidtea_mediterranea -- which annotation is open (smed_20140614, ids SMED3..., or schMedS3h2_WBPS19); FASTA titles carry ids alone, so the closest gene shows an id | DECIDED: species, no names. OPEN: which annotation |
| Scolanthus callimorphus (sea anemone, same family as Nematostella) | *Nematostella vectensis* | no | in the OMA template (`oma_code: NEMVE`); RefSeq proteome in REF_DB/REFSEQ_nematostella_vectensis | PROPOSED (as Montipora and Acropora have it) |
| Furcifer pardalis, Bradypodion pumilum, B. ventrale (chameleons) | *Chamaeleo calyptratus* CCA3 (same family) | no | our own gene set; its names come from this pipeline or the Apollo curation, so not a naming source | PROPOSED. Pogona and anole are already searched by the annotation pipeline (Ensembl RBH) |
| Notamacropus eugenii (both gene sets) | koala (*Phascolarctos cinereus*, same order) or opossum (*Monodelphis domestica*) | no | RefSeq; neither is in REF_DB, a download under dev/smr_dev | PROPOSED |
| Parastichopus parvimensis (sea cucumber) | purple sea urchin (*Strongylocentrotus purpuratus*) | no | RefSeq; download. *Apostichopus japonicus* is closer (same family) but its annotation has few names | PROPOSED |
| Ptychodera flava (acorn worm) | *Saccoglossus kowalevskii* | no | RefSeq; download | PROPOSED |
| Petromyzon marinus PM2023_newmodels | *P. marinus* RS_2025_08 (RefSeq, the same species) | no | genomes/v2 | PROPOSED |
| Petromyzon marinus RS_2025_08 | none (native RefSeq names; Ensembl lamprey is already a pipeline database) | -- | -- | PROPOSED |
| Nothobranchius furzeri (RefSeq) | none extra: medaka and zebrafish are already pipeline databases | -- | -- | PROPOSED |
| Amphimedon queenslandica (sponge) | none: no close relative with a curated annotation | -- | -- | PROPOSED |
| Bats (49) | to decide when they are run (last) | | | OPEN |
| Bradyrhizobium, Medicago x2 | deferred with their OMA runs | | | OPEN |

Notes
- Names from another species are off everywhere unless reviewed: in Montipora, turning them on replaced ~2,200
  human-based names. A same-species annotation (lamprey, as Nematostella NV2) is the case where names can be copied.
- One entry per gene set may have `use_for_names: true`.
- A species in the OMA template needs no search for its rank-1 evidence (OMA ortholog); the RBH and DIAMOND
  searches add ranks 2 and 3 for genes OMA does not pair.
