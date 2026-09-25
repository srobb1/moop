# Gene naming v2: critical review, in progress (2026-09-25)

The state at the end of the session. The code is committed on `naming-v2`:
- 08eef9ab: launchers
- 84065baa: naming v2
- c56352ee: main()-last layout, guard, e2e test, CI
- 2b64132b: Methods and annotation descriptions

Test outputs (not in the repo) are in the session scratchpad:
- directory: `/tmp/claude-2935/.../scratchpad/v8/{cok,fly,mnat}_{new,old}`
- runner script: `scratchpad/run_naming.sh`

**Next session.** Do a top-to-bottom review from the point of view of an evolutionary biologist and a computational biologist. Go through the naming steps in order. For each step, sample real names and ask two questions: could this name be wrong, and are we throwing away good names? Report the findings, and let the user decide before changing any code.

## Step counts (the unit is rows in `gene_name_source.*.moop.tsv`, so a gene and its transcript are 2 rows)

| gene set | 2 native | 3 OMA | 4 -like | 5 PANTHER | 6 InterPro domain |
|---|---|---|---|---|---|
| Congeria (OMA + manual BLAST) | – | 13,416 | 682 | **25,836** | 6,290 |
| fly (no OMA) | – | – | 36,640 | 51,705 | 3,235 |
| Miniopterus (RefSeq) | 104,561 | – | 2,157 | 493 | 24 |

Plain OMA names in Congeria (genes):
- 1:1: 3,654 pairwise + 118 HOG
- many:1: 1,312 pairwise + 104 HOG

## Finding 1 (open, the biggest): PANTHER is the largest naming step and the least vetted

The PANTHER step (5) names more Congeria genes than OMA does. Yet it has **none** of the safeguards the BLAST step (4) has:
- **No coverage check.** A PANTHER HMM hit that covers only one domain still names the gene. By contrast, -like names require ≥80% of both proteins to be aligned.
- **No E-value floor** beyond InterProScan's own cutoff. In Congeria:
  - 3,727 genes have E > 1e-20;
  - 4,237 have E between 1e-50 and 1e-20;
  - 4,954 have E < 1e-50.
  - Example: COKUS1KC_0000002 is "FIBRILLIN-RELATED family member" at E = 3e-10.
- **Uninformative family names get through `is_informative_hit`.** Examples:
  - EXPRESSED PROTEIN (120)
  - AGAP001331-PA-RELATED (89)
  - LD39211P (57), LD33804P (49)
  - OS10G0105400 PROTEIN (50)
  - BONUS, ISOFORM C-RELATED (148)
  - ATILLA, ISOFORM B-RELATED-RELATED (49)

  These are clone and locus ids (fly LD..P, rice OS..G, mosquito AGAP) and fly isoform names. The patterns should go in GeneNamingV2.pm.
- **Families named after one specific gene read as a specific name.** Examples:
  - "N-LYSINE METHYLTRANSFERASE KMT5A family member" (100 genes)
  - "TRANSIENT RECEPTOR POTENTIAL CATION CHANNEL, SUBFAMILY M, MEMBER 6 member" (TRPM6)
  - "MITOGEN-ACTIVATED PROTEIN KINASE KINASE KINASE 7-RELATED"
  - "MYOSIN LIGHT CHAIN 1, 3" (67 genes)
  - "THREE PRIME REPAIR EXONUCLEASE 1, 2"

  A coral SET-domain gene called a "KMT5A family member" implies more than the HMM shows.
- **Wording bugs from `family_member()`:**
  - "DNA HELICASE RECQ FAMILY MEMBER member"
  - "... SUBFAMILY M, MEMBER 6 member"
  - "...PROTEIN-RELATED member"

  The rule is to append " member" when the name already contains "family", which is wrong when "family" appears mid-name or the name already ends in MEMBER.
- **Transposons** are named as ordinary families, for example "INTEGRASE CATALYTIC DOMAIN-CONTAINING PROTEIN family member" (114) and "HAT FAMILY DIMERISATION...".
- **Options to discuss** (not decided):
  - Require PANTHER coverage, or an E-value floor.
  - Extend the uninformative patterns.
  - Name only families whose name is not a single member's gene name. Otherwise fall through to the InterPro domain step, which claims less.
  - Or drop PANTHER to *after* domains.
- **Input:** `PANTHER.iprscan.moop.tsv` (family level, PTHRnnnnn). `read_panther` keeps the lowest E-value per gene.

## Other items to check next session (not yet looked at)

1. **many:1 plain names.** For example, "APOH ... one of 25 copies". Is a plain name right for large lineage-specific expansions? Should we mark copies above some count?
2. **OMA 1:1 with no RBH support** is kept for now. The fly OMA benchmark decides this once the reference run's part 3 is done; the user sbatches `oma-part3.sh`.
3. **InterPro domain names** (step 6):
   - sample the wording;
   - check repeat and zinc-finger names;
   - decide whether a single short repeat should name a gene.
4. **Native names (RefSeq).** The gene-level Desc carries "isoform X2" from a transcript (Miniopterus GALNT13). Strip the isoform/transcript-variant suffix at gene level?
5. **-like names point at one paralog.** This needs the eross next-hit columns (reciprocal_alignment.py copy in scripts/).
6. **Closest-gene tiers 5–7** (DIAMOND, Swiss-Prot→Compara, PANTHER subfamily). Are they still sound?
7. **Methods doc:** update it after any change (notes/GENE_NAMING_METHODS.md).

## Still pending from before
- The fly benchmark with OMA.
- After NV2 and zebrafish OMA part 3: run mapGO, then `MOOP_RELOAD=1` those gene sets.
- Merge notes/annotation_config_descriptions.json into the live annotation_config.json (the user does this). If the PANTHER step changes, the Score text needs updating.
- Plain names from BLAST (3b) once the eross columns exist.
- A PANTHER audit of 100 names. This is now the priority, per Finding 1.
