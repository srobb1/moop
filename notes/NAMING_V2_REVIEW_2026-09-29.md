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

**Results (Congeria, runs/exp0929_*):** ignoring domain-named HGNC groups (regex) changed 271
names, but function-defined groups then took over ("CD molecules", "BAF complex subunits",
"RING finger E3 ubiquitin protein ligases") -- a pattern list always leaks, HGNC does not label
which groups are families by descent. PANTHER first (the family all human members are in and the
gene matches) changed 1,743 names: better tags for the same name (137 ISM|pthr -> ISO|fam), new
names (17 from None, 33 from domains), but uneven labels -- good (Tubulin, Tetraspanin/Peripherin,
Peptidase C1A), still domain-named (Sushi / I-set / C-type lectin domain containing), functional
InterPro names ("Complement & Cell Adhesion Regulators", "Extracellular Matrix Assembly and
Organization"), one fly gene ("CADHERIN-87A").
**Next proposal (to test):** use PANTHER to decide whether an HGNC group is a family by descent
-- the fraction of the group's human genes in its main PANTHER family (Swiss-Prot); a coherent
group ("Tubulin beta", "Anoctamins") keeps its HGNC name, a scattered one ("CD molecules",
"EF-hand domain containing") is not used and the PANTHER family (later subfamily, JSON) names it.

### 4. Closest human, tier 4: orthology is not transitive — TESTED, kept and defined in the Methods
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

## Tag vocabulary (decision, 2026-09-29)
One meaning per support mark: `+` agrees, `~` partly (similar, not the best), `C` contradicts,
`-` no evidence, `X` excluded. Renamed: `pthr=` -> `pthr+`, `pthrX` -> `pthrC` (`X` now only
means excluded, `omaX`). `sim~` stays `~`, not `C`: a close paralog outscoring the ortholog is
common and alone does not contradict OMA (dropping on `sim~` alone lost 700 sound names).
Table in GENE_NAMING_METHODS.md §5.1. Earlier notes use the old spellings.

## Direction: consensus, not ranking (user, 2026-09-29)
Collect independent methods (OMA pairwise, OMA HOG, MMseqs2 RBH, DIAMOND full-length best hit,
PANTHER family/subfamily -- later TreeGrafter placement -- and orthology via another species) and
count how many point to the same human gene or family; the name follows the agreement (plain when
several agree on one gene, -like when only similarity does, a family name when they agree only at
family level, none when they disagree), and the tag can show the count. `omaX` / `omaC` are
already "count the dissent" rules. First step: measure agreement on the current Congeria names.

## Direction: PANTHER trees as the backbone for family decisions (after the JSON)
Paralog ties (5% rule), the proposed outgroup test, HGNC-group coherence and "does the gene predate
the human duplication" are all approximations of a gene tree. TreeGrafter's graft point (JSON)
places the gene on the PANTHER family tree and answers them directly. Plan: PANTHER trees for
family/subfamily decisions and ties; OMA kept as the independent genome-wide call, checked
against the tree; HGNC for the names; InterPro domains as the fallback. Validate on the fly
benchmark (Ensembl Compara) before it replaces the heuristics.

## HGNC-group coherence (tested, awaiting decision)
Coherence = fraction of an HGNC group's human genes (with a Swiss-Prot PANTHER family) in its main
PANTHER family. Families by descent 0.6-1.0 (Tubulin beta 1.00, Tetraspanin 0.94, Anoctamins 1.00,
Cathepsins 0.73, HSP70 0.65, Kelch-like 0.60); domain/function groups 0.06-0.21 (CD molecules 0.07,
EF-hand 0.09, Sushi 0.16, BAF complex 0.15, RING E3 0.21); RABs 0.26 (PANTHER splits them finely).
Congeria: threshold 0.8 changes 1,095 names, 0.6 changes 899; 0.6 keeps Peroxiredoxins, Cathepsins,
RAS type GTPase, Fucosyltransferases as HGNC names. Recommended 0.6. Open: InterPro family labels
that are functional categories ("Complement & Cell Adhesion Regulators") when no group qualifies.
