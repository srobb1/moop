# What the Score column holds, by annotation source (2026-10-06)

For the site's score lookup: what each table's `feature_annotation.score` means, which direction is better,
and a human-readable form. Checked against every table Congeria loads (values seen there in brackets) and the
parsers that write them. Scores that are not numbers (`-`, GO namespaces) load as NULL (load_annotations_sqlite.pl; checked: every GO
row's score is NULL in the database). The GO namespace is only in the file's Score column.

| Annotation type | Source (annotation_source_name) | Score is | Better | Seen | Readable form |
|---|---|---|---|---|---|
| Homologs | Ensembl <Species>, UniProtKB/Swiss-Prot | DIAMOND E-value of the best hit | lower | 0 .. 1e-5 | "E = 2e-37" |
| RBBH Homolog | Ensembl <Species> (MMseqs2 RBH) | MMseqs2 E-value of the reciprocal best hit | lower | 0 .. 2e-3 | "E = 3e-74" |
| RBBH Homolog | Ensembl <Species> (DIAMOND RBH) | eross E-value (no longer loaded, `LOAD_EROSS_RBBH=1`) | lower | | |
| Orthologs | OMA pairwise orthologs (<SP>), OMA HOG orthologs (<SP>) | the relationship code: 1 = 1:1, 2 = many:1, 3 = 1:many, 4 = many:many (this gene : partner) | 1 cleanest | 1 .. 4 | "1:1", "many:1" ... (also in the description) |
| Orthologs | OMA group orthologs (<SP>) | always 1 (a group holds one gene per species) | | 1 | none needed |
| Orthologs | EggNOG | eggNOG-mapper seed ortholog E-value | lower | 0 .. 1e-3 | "E = 6e-82" |
| Paralogs | OMA HOG paralogs | number of species sharing the duplication (1 = duplicated within this species only; more = an older duplication, the species are named in the description) | higher = older | 1 .. 9 | "duplication shared by 5 species" |
| Closest Gene | Closest human gene (HGNC/Ensembl/family) | tier 1-7 (Methods §6) | lower | | "tier 1: OMA ortholog" ... |
| Closest Gene | Closest <species> gene (/family) | rank: 1 OMA, 2 reciprocal best hit, 3 DIAMOND best hit, 4 hits file | lower | | "rank 1: OMA ortholog" ... |
| Domains | InterProScan (Pfam) | E-value (Pfam's own thresholds decide a hit; short repeats can exceed 1) | lower | 0 .. 5.6e3 | "E = 1.5e-5" |
| Domains | InterProScan (SMART), (CDD) | E-value | lower | SMART 0 .. 230; CDD 0 .. 1e-2 | "E = ..." |
| Domains | InterProScan (ProSiteProfiles) | normalised profile score | **higher** | 5 .. 264 | "score 36" |
| Domains | InterProScan (ProSitePatterns), (Coils), (MobiDBLite), (InterPro) | none (`-`) | | | show nothing |
| Gene Families | InterProScan (PANTHER), (Gene3D), (FunFam), (SUPERFAMILY), (PIRSF), (PRINTS), (SFLD), (NCBIfam) | E-value | lower | ≤ 1e-4 mostly; NCBIfam up to 8.6 | "E = ..." |
| Gene Families | InterProScan (Hamap) | profile score | **higher** | 11 .. 208 | "score 36" |
| Gene Ontology | InterProScan (InterPro2GO), (PANTHER2GO), OMA2GO, EggNOG (EggNOG2GO) | the GO namespace (text: biological_process, molecular_function, cellular_component), NULL in the database | | | the namespace, from the GO term |
| Protein Features | SignalP | probability of the signal peptide (SP class only) | **higher** | 0.41 .. 1.0 | "probability 0.98" |
| Protein Features | DeepTMHMM | number of transmembrane helices | (a count) | 1 .. 30 | "3 helices" |
| Protein Features | DeepLoc | probability of the top predicted location | **higher** | 0.23 .. 1.0 | "probability 0.95" |

Notes
- An E-value of 0 is below the program's smallest reported number: show it as "E < 1e-300" or "E = 0", not as no score.
- InterProScan rows are one per protein and entry (from 2026-10-06); a repeat hit of one entry keeps its best score.
- The naming statements (gene_naming table) have no scores; their sort_order is the display order.
