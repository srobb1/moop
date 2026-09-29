# Gene naming v2 review — 2026-09-29 (evolutionary critique, plans)

Follows `NAMING_V2_REVIEW_2026-09-28.md`. Code reviewed: `assign_gene_names_v2.pl` and modules on
branch `naming-v2` at 04eec96e. Experiments run from a scratch copy with switches
(`naming_review/code_exp/`, `naming_review/run_exp_0929.sh`); the repo code is unchanged by them.

## Perl style
- `$a` / `$b`: only inside sort blocks (allowed).
- `$_`: 51 lines in `assign_gene_names_v2.pl`, 15 in `tests/naming_end_to_end.pl` (map/grep and
  `foreach` modifiers); none in the modules. To convert to named variables; outputs must stay
  byte-identical.

## Critique and decisions

### 1. OMA names: support too weak to catch hidden paralogy — TESTING
`oma_supported` accepts any human hit to the named gene (E <= 1e-5, any coverage) or any shared
PANTHER family. Hidden paralogy (differential loss) passes both: paralogs share domains and
families. `sim~` (best human hit is another gene) and `pthrX` (conflicting PANTHER family) are
positive evidence against the call but only go into the tag. Test: drop OMA names flagged
`sim~` AND `pthrX`, and `sim~` OR `pthrX`, and let the next step name the gene.
Family comparison: the support check strips subfamilies; the InterProScan TSV has no
subfamilies at all (20,423 Congeria PANTHER rows, none with `:SF`). Subfamilies come with the
JSON (next annotation-pipeline run); the code then needs to read the JSON.

### 2. `-like` ties: replace the 5% bitscore window with an outgroup test — PLAN (not yet coded)
The best bitscore finds the slowest-evolving paralog (the step-3 argument applies to step 5
too); ohnolog bitscores often differ by more than 5% through rate differences, so a `-like`
name can point at one paralog (8% of `-like` names in the fly benchmark).
Outgroup test: let H1 be the best human hit and H2 the next human paralog. If
score(H1, H2) >= score(query, H1), H1 and H2 duplicated after the query's lineage split from
the human lineage: the query is equally related to both -> name the shared HGNC group (or
PANTHER family / subfamily); otherwise H1 is genuinely closer -> `H1-like`.
Needs: one human-vs-human similarity search (DIAMOND, all Ensembl human proteins, once per
release; keep bitscores per gene pair, best isoform pair). Then compare with the 5% rule on
Congeria and the fly benchmark (Compara orthologs as truth).

### 3. HGNC groups defined by a domain or function name "families" — TESTING
Congeria family names from "Sushi domain containing" (28), "EF-hand domain containing" (17),
"BTB domain containing" (14), "RNA binding motif containing", "Zinc fingers C2H2-type",
"Ankyrin repeat domain containing", "Complement system regulators and receptors". "X family
member" claims common descent the group does not. Test: ignore groups whose name says domain /
repeat / motif ... containing, zinc fingers, F-boxes, regulators and receptors; the gene then
falls to PANTHER. Separate issue seen: "Pregnancy specific glycoproteins family member" in a
mussel (a mammal-specific group).

### 4. Closest human, tier 4: orthology is not transitive — TESTING
target -> reference ortholog -> reference's human ortholog is safe only for 1:1 -> 1:1 (DROME
has many lineage-specific duplications and losses). Test: tier 4 only through 1:1 (OMA) /
one2one (Compara) links. If few are lost, restrict; if many, keep and DEFINE "closest human
gene" explicitly (it is not claimed to be an ortholog below tier 2 — say so in §6).

### 5. Naming-species hits file has no coverage — TO DO
The hits-file fallback of naming step 2 uses the best E-value only; every human `-like` needs
>= 80% of both proteins. Only NV2 uses it (same_species, RefSeq jaNemVect1 RBBH file); neither
the moop file nor the raw RBBH table (`*.results.tsv`: query, best_hit, evalue,
reciprocal_score, genes) has coverage. Plan: optional coverage columns in hits files; for names
require FULL (80/80); a hits file without them is not used for names (closest gene only).
Needs the RBBH step to write coverage. Measure NV2 names from the hits file first.

**Decision (user): two small scripts, launched by hand when a closest species needs them** (the
annotation pipeline is not changed; occasional species-specific runs):
1. `diamond_closest_species.sh <gene set proteins> <partner proteome> <out dir>` -- DIAMOND blastp
   exactly as the annotation pipeline runs it (ultra-sensitive, E <= 1e-5, the 17-column output
   with query and subject coverage), written as `<out dir>/diamond_results.tsv` so the naming
   code reads it with the reader it already has.
2. `rbh_closest_species.sh <gene set proteins> <partner proteome> <out dir>` -- MMseqs2
   `easy-rbh` (the same version as the annotation pipeline) with `--format-output` including
   `qlen,tlen,qcov,tcov`, so coverage is in the file and the naming code needs no partner FASTA.
Both write a `## Annotation Source / Version / date / command` header (provenance in evidence
text). geneset_config.yaml: each closest_species entry gets `diamond:` and `rbh:` paths
(next to or instead of `hits:`). Naming rule: a naming-species name needs a full-length hit
(>= 80% of both proteins), as a human `-like` name does; for same_species, a full-length
reciprocal best hit copies the name as is. First use: NV2 vs RefSeq jaNemVect1
(`jaNemVect1.fa` is next to the current moop file).

### 6. Labels and transparency — TO DO
- Tier 3 is MMseqs2 reciprocal best hits only (OMA is tiers 1-2), but its links carry
  `type => '1:1'`, OMA's label: relabel `rbh`. Tier 5 DIAMOND best hits: evidence to say
  "homolog (best hit)".
- Transposable elements: `$TE_MIN_COPIES = 5` was chosen by testing but is arbitrary; say so
  in the Methods (it catches some TE families, not all; those it catches get a cleaner name).
- PANTHER subfamily names: waiting for the JSON.

## Other analyses
- **TreeGrafter (PANTHER tree placement)** — already run inside InterProScan's PANTHER step.
  The JSON (not the TSV) gives per PANTHER match the subfamily (`PTHR10913:SF78`) and the
  `graftPoint` (`PTN000857367`), plus hmmStart/hmmEnd/hmmLength. Keep it: `interproscan.sh
  -f TSV,JSON`. Turning a graft point into human orthologs needs PANTHER's tree files (which
  leaves descend from which node, speciation/duplication nodes) — a download, not a compute job.
  This is the phylogeny-based orthology the score-based rules approximate.
- **Synteny with a close well-annotated relative** (Dreissena for Congeria, Anolis for
  Chamaeleo): the standard way to separate ohnologs / paralogs; resolves many:many. `jcvi`
  (MCScan) conda env exists. FUTURE.
- **eggNOG-mapper**: already run; its database (eggNOG 5, 2019) is old and gave real problems
  with Chamaeleo orthologs. Not used for names.
- **Domain architecture check** — TO DO: compare the gene's InterPro domains with the named human
  gene's; add to the tag and the provenance ("has the core domains of ALPHA: X, Y" / "lacks
  ALPHA's X domain"). Needs human InterPro domains per HGNC gene (UniProt human proteome with
  InterPro cross-references, via update_reference_data.sh).
