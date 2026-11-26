# Congeria (COKUS1KC) build review, 2026-10-02

Code read end to end before the full build: `assign_gene_names_v2.pl`, `GeneNamingV2.pm`,
`process_one_geneset.sh`, `moop_process_genome_data_v2.sbatch`, `panther_placements.py`,
`interproscan_model_coverage.py`, `interproscan_json.py`, `parse_DIAMOND_to_MOOP_TSV.pl`.
Three readings: evolutionary biologist, bench scientist, coder. Numbers are from Congeria
(43,768 genes), run `naming_review/runs/cok_review1002`.

Branch `cokus-review-2026-10-02` (from main), changes NOT committed.

## Analysis folder (consolidated)

`dev/smr_dev/moop/annotations/SBGENOMES_2026-05-21/Congeria_kusceri/GCA_027627225.1/COKUS1KC`
now holds everything the build reads (see `README.consolidated.txt` there): the annotation
pipeline's busco, deeploc2, deeptmhmm, eggnog_mapper, rbh_eross, rbh_mmseq, signalp6 and diamond
(copied from `annotations/sbgenomes_2`), the 17-column DIAMOND reruns for human (50 targets) and
Swiss-Prot in `diamond/`, and InterProScan 5.78 TSV + JSON. The other 15 DIAMOND databases are the
pipeline's 4-column files: loaded as homolog tables, not used by naming.

Build: `ANNOTATIONS=<that root> SKIP_COPY=1 ... moop_process_genome_data_v2.sbatch --reload Congeria_kusceri`.

## Fixed (uncommitted)

1. **Gene statements described the wrong human gene for a "-like" name** (coder, bench).
   Support, Alignment, Domains and Cautions were written about the closest human gene; a -like name
   is taken from the best full-length hit, a different gene for 77 of 500 such names (70 with a
   tier-4 closest gene, 7 after an OMA name was withheld). `COKUS1KC_0006409` "ARNT-like" read
   "Supported by OMA pairwise ortholog" (that is NPAS1) and cautioned that the reciprocal best hit
   "points to another gene: ARNT". Now the statements follow the gene in the name. Names unchanged.
2. **"Co-ortholog of human X (OMA HOG, 1:1)" beside Relationship = ortholog** (118 genes): the opening
   word now follows the relationship (1to1 = ortholog).
3. **Source version of DIAMOND homolog tables** (`process_one_geneset.sh`): `db_version.txt` is now
   `<db><TAB><version>`; the whole line went into "Annotation Source Version". Last field is used.
4. **EggNOG version** read from `db_version.txt`, which the pipeline does not write
   (`eggnog_mapper_version.txt`): the version came out as " DB_". Either file is read now.
5. **Homolog tables** (`parse_DIAMOND_to_MOOP_TSV.pl`): with 50 human targets the human table would
   load 401,689 rows for 17,355 proteins (mostly isoforms of one human gene). Each protein's top hit
   is kept, as before the search kept several targets.

6. **Loader wiped the gene statements** (`setup_new_moopdb_and_load_data.sh`): the SignalP / DeepTMHMM call's
   `*.domains.moop.tsv` also matched `gene_statement.domains.moop.tsv`; loading a naming file clears the gene
   set's gene_naming rows first, so 152,201 statements were removed and the 17,552 Domains statements left.
   Found by checking the first build's database. Gene statement files are now loaded by their own call alone.

`tests/naming_end_to_end.pl`: 188 checks pass. Names: 0 of 43,768 differ from the run before the fixes.

## Open, the user's call

### A. Orthology through another species (closest human, tier 4) skips the checks OMA pairs get
A direct OMA human pair is set aside when nothing supports it (omaX) and withheld when the best
human hit is another gene AND the PANTHER family differs (omaC). A chain through a reference
species (our gene -> its OMA ortholog in limpet/annelid/shark... -> that gene's OMA human ortholog)
gets neither check. Of 1,743 genes whose closest human gene comes from such a chain:

| best human hit | PANTHER family | genes |
|---|---|---|
| agrees | same / none / differs | 489 / 6 / 54 |
| another gene | same / none / differs | 370 / 17 / **439** |
| none at all | same / none or unknown / differs | 55 / **164** / **149** |

439 have the omaC signature and 313 the omaX one (bold): 752 of 1,743 (43%) closest human genes
that the direct-OMA rules would not accept. They are shown as "Closest human gene" (Sushi-domain
proteins -> PRR33, CD46, SELE); they do not name the gene. 228 of the 1,743 are genes whose direct
OMA pair had already been set aside, and the chain brings a human gene back.
Proposed: apply the same two checks to tier-4 chains; a chain that fails is set aside and the next
evidence decides.

### B. At tier 4 an OMA chain always loses to an MMseqs2 RBH + Compara chain
Tier-4 links are ordered by bitscore; OMA chains have none (counted 0). 107 of the 304 genes whose
closest human gene comes from an RBH chain (often through worm or fly, reaching a large family)
also have an OMA chain through a closer or slower-evolving species. Proposed: decide the order on
purpose (OMA chain first if A is adopted; else leave).

### C. Identical proteins: copies or assembly duplicates
2,242 sets of byte-identical proteins hold 5,739 proteins (13% of the gene set); at >= 100 aa, 1,288
sets / 3,112 proteins: 794 sets on chromosomes only, 352 with a chromosome copy and 479 copies on
unplaced scaffolds, 142 on unplaced scaffolds only. "CDC6 (1 of 8)": five copies in two tandem
clusters on CM051040.1 (a ~220 kb unit repeated) and three on unplaced scaffolds of 51-78 kb, two of
them identical to a chromosome copy. A reader takes "(1 of 8)" as eight genes; a primer, probe or
dsRNA cannot tell identical copies apart, and an unplaced duplicate may be the same locus assembled
twice. Proposed: a statement on such genes ("N other genes encode an identical protein: ids; M on
unplaced scaffolds"), built from protein.aa.fa and the GFF. Not a naming change.

### D. Smaller
- `by_uniprot` in `load_hgnc`: 69 UniProt accessions belong to several HGNC genes (identical
  proteins: H4C1..H4C16, DEFB103A/B); the last one in the file wins. A Swiss-Prot or tree hit to
  such an accession is credited to one arbitrary copy.
- `read_transcript_hits` keeps the hit covering most of the protein, then identity: a 100%-coverage
  hit at 60% identity hides a 95%-coverage hit at 99%. Positive-only, so it can only miss a match.
- `ENSG00000273554` (OMA 1:1 partner of COKUS1KC_0004476) is a model of SLC6A6 on an unplaced human
  scaffold; `read_human_models` reads loci from `chromosome:` headers only, so it is not recognised
  as SLC6A6 and the gene is named "SLC6A6-like" (homolog) instead of SLC6A6 (ortholog).
- 4-column DIAMOND files of the other 15 databases count as "hits" for the None reason
  ("hits did not pass the naming tests", 203 genes moved from "no hits") although naming cannot use them.

## Read and found sound
PANTHER tree tracing (`classify`: moving a graft up to the species' own lineage only widens the
human set; duplication at the joining node -> paralog_family), model coverage from the JSON,
paralog-tie rule, family naming (HGNC group coherence, PANTHER whole-member rule), readthrough and
same-gene-model handling, deterministic ordering (no hash-order dependence found).

## Build result (2026-10-02, second run, after fix 6)
`build_and_load_db/v2/data/Congeria_kusceri/organism.sqlite`: 175,072 features, 1,365,281 feature
annotations, 152,201 gene statements over 9 kinds. Names equal `cok_review1002` (0 differ). Run in
the interactive session (the cluster refuses sbatch from a Claude session), `--reload`, `SKIP_COPY=1`:
not copied to moop. Log: `slurm_logs/MOOP_COKUS_insession_2026-10-02b.out`. The July database is
kept as `organism.sqlite.before_2026-10-02`.

## A, B, C tried (2026-10-02, afternoon; uncommitted, same branch)
Runs: `naming_review/runs/cok_trial1002_A` (A alone, `NAMING_TRIAL=noB`) and `cok_trial1002_AB`.
`NAMING_TRIAL=noA` / `noB` turn each rule off. Tests: 189 pass (G49 given the partial hit its chain needs;
a test of a chain being set aside is still to write). C needs `--gff` and `--scaffold-sizes`, not yet passed
by `process_one_geneset.sh`; the kind `identical` is new to the loader and the site.

- **A** (chains checked like a direct OMA pair): 821 genes change closest human gene -- 567 now have none,
  168 fall to the DIAMOND best hit, 73 take another chain, 13 tiers 6-7. Tier 4: 2,047 -> 1,299. Names: 3 change.
- **B** (OMA chain before RBH + Compara), on top of A: 58 genes change closest human gene; 41 reach fewer
  human genes (FRS3/DOK x8 -> FRS2; OTOP1/2/3 -> OTOP1), 12 the same number, 5 more. One more name changes.
  Compara types of the 304 RBH chains before B: one2one 107, one2many 77, many2many 120; all through fly (178),
  worm (115) or yeast (11).
- **C** (identical proteins): 5,739 genes get the statement; 3,540 identical to one other gene, 818 to five or
  more; 2,627 are under 100 aa.
- Names changed by A+B: COKUS1KC_0021456 SLC38A10 (tree + chain) -> "Amino acid transporter family member";
  POMGNT1 copies 2 -> 3 (COKUS1KC_0030898 gains the tree name).

## Asked: non-human Swiss-Prot names
Step 6 names from human genes alone (Ensembl human, Swiss-Prot HUMAN entries). Among genes with a PANTHER
family name, a domain name or none, the top Swiss-Prot hit is full-length (80/80, E <= 1e-10) to another
species with an informative name for 234 / 92 / 19 genes (345). Species: fly 33, worm 28, mouse 18,
Dictyostelium 18, the kelp Macrocystis pyrifera 18, Arabidopsis 17, zebrafish 15, bacteria ~25, molluscs ~21.

## Decided and built (2026-10-02, 12:40)
User: keep A and B; keep C (statement kind `identical`, order 4; the later statements moved down one; the site needs
the new kind and a label); add the Swiss-Prot step. Naming steps are now 1-9: 7 = Swiss-Prot protein of another
species ("name-like (species)", tag `ISS|bh|sp`, link kind `uniprot`), 8 PANTHER family, 9 InterPro domain.
Step 7 declines when the gene has a full-length human hit that step 6 left unnamed (a paralog tie: 75 genes,
"Calmodulin-like (Macrocystis pyrifera)" for a CALM1/2/3 tie) -- the user wants a great alignment used when there
is no human name, so this is open: with the rule 220 genes are named, without it 295.
`process_one_geneset.sh` passes `--gff` and `--scaffold-sizes`. Methods doc updated. Tests: 196 pass.
Congeria rebuilt: 158,169 gene statements; log `MOOP_COKUS_insession_2026-10-02d.out`. Not copied to moop.
