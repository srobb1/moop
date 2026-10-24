# Synteny in MOOP — plan (draft for review, 2026-10-23)

Goal: use JBrowse's synteny views (linear synteny view, dot plot, and the right-click
"Launch synteny view for this position" from a synteny track in a normal browser) across MOOP's
genomes, **without** an all-vs-all run, in a way that is easy to keep up and easy for analysts.

Your questions, answered up front:

| Question | Short answer |
|---|---|
| Do I need spreadsheets for synteny? | **No.** A comparison is a *pair* of genomes, not a track of one genome. One list of pairs, edited on an admin page, replaces the ASSEMBLY_1 / ASSEMBLY_2 / BED*_PATH / MAF sheet columns. |
| Can each browser have synteny tracks that open the synteny view? | **Yes — verified in our JBrowse 4.3 build.** A synteny track shown in a normal browser offers "Launch synteny view for this position" on right-click. MOOP generates these tracks for both genomes of every computed pair. |
| How do I stay organised? | One registry of pairs with a status (computed / queued / running / failed), one folder layout, one admin page. Nothing per organism. |
| How does someone make a request? | A "Compare with another genome" button on the genome browser page: pick a genome; if the pair exists it opens, if not they press **Request** (one click, optional note). Requests land in an admin queue. |
| Can jobs go to Galaxy? | **Partly.** usegalaxy.org has minimap2 and MUMmer (both make files JBrowse reads) but no gene-based synteny tools, cannot reach MOOP (internal IP), and is a public server. Good for public genome pairs; the cluster is the better default. See §5. |
| All vs all? | Not needed. Precompute ~2 comparisons per genome (a reference + its closest relative); everything else on request. 49 bats → ~95 pairs instead of 1,176. See §3. |

---

## 1. What we already have (checked 2026-10-23)

- **JBrowse 4.3 (our build)** includes: LinearSyntenyView, DotplotView, the in-browser synteny display
  (`LGVSyntenyDisplay`) with **"Launch synteny view for this position"**, and readers for PAF,
  indexed PAF (`.pif.gz`), MCScan anchors (`.anchors` / `.anchors.simple`), MUMmer `.delta`, chain and MashMap.
- **MOOP**: `api/jbrowse2/config.php?assembly1=…&assembly2=…` already builds a two-genome config
  (`generateDualAssemblyConfig()`), and `loadSyntenyTracks()` reads synteny track JSON from
  `metadata/jbrowse2-configs/tracks/synteny/<asm1>_<asm2>/`, with access checks. **No synteny tracks exist yet.**
- **Storage**: MOOP has its own `data/tracks/` (served by MOOP's `api/jbrowse2/tracks.php` with the same
  signed tokens), 643 GB free. Results can live **on MOOP** — no copy step to the tracks server.
- **Tools on MOOP**: the JBrowse CLI (`/usr/local/bin/jbrowse`) for `jbrowse make-pif` (indexing PAF so large
  alignments load by region).
- **Galaxy**: MOOP already submits MAFFT jobs to usegalaxy.org (`lib/galaxy/client.php`). usegalaxy.org has
  `minimap2`, `mummer`/`nucmer`, `diamond`, `liftoff`, `jbrowse2`; it has **no** MCScan/JCVI synteny, SyRI or ntSynt.
- **Latent bugs to fix first** (memory: plan_synteny_tracks): the PAF and MCScan track types read
  `track_path` but the sheet parser emits `TRACK_PATH`; MCScan expects `assembly1`/`assembly2` vs the sheet's
  `assembly_1`/`assembly_2`. Moot if synteny tracks come from the registry instead of sheets (recommended).

## 2. Two kinds of synteny analysis — pick by distance

| Pair | Method | Output | Notes |
|---|---|---|---|
| Close relatives (same order: bat–bat, lamprey–lamprey, Bradypodion–Chamaeleo) | **Whole-genome alignment**: `minimap2 -x asm20 -c` | PAF → `jbrowse make-pif` → `.pif.gz` | Base-level, shows inversions/rearrangements; ~30–60 min and ~20–30 GB RAM per 2 Gb pair. |
| Distant (cnidarian–cnidarian, across phyla) | **Gene-anchor synteny**: JCVI/MCScan (`jcvi.compara.catalog ortholog` → `jcvi.compara.synteny screen`) | `.anchors.simple` + two BEDs | Uses MOOP's own proteins + gene coordinates (we have both). Whole-genome DNA alignment is useless at this distance. Cluster only (not in Galaxy). |

One rule decides the method from the NCBI taxonomy MOOP already caches (e.g. same order → minimap2; otherwise MCScan),
with an admin override per pair.

## 3. Not all-vs-all: which pairs to precompute

Per group (bats first):
1. **A reference genome** (1–2 per group): every member vs the reference. For bats, a chromosome-level
   Bat1K genome — e.g. *Myotis myotis* (Vespertilionidae, echolocating) and *Rousettus aegyptiacus*
   (Pteropodidae, fruit bat), one per suborder. *(your call)*
2. **Closest relative**: every member vs its nearest neighbour in MOOP by NCBI taxonomy (computed, not typed).
3. **Everything else on request.**

49 bats → ~47 reference pairs + ~45 nearest-neighbour pairs (some coincide) ≈ 95, run in batches.
Popular requested pairs get added to the "precompute" list automatically (or by an admin click).

## 4. Organisation — one registry, no spreadsheets

**`metadata/synteny_pairs.json`** (backed up with other site data), one entry per pair:

```json
{ "a": "Myotis_myotis/GCF_014108235.1", "b": "Pipistrellus_kuhlii/GCF_014108245.1",
  "method": "minimap2-asm20", "status": "computed",
  "files": "data/tracks/synteny/Myotis_myotis__Pipistrellus_kuhlii/mmy_pku.pif.gz",
  "why": "nearest-relative", "requested_by": [], "run": {"where": "cluster", "date": "2026-11-02", "params": "-x asm20 -c"},
  "access": "follows both assemblies" }
```

- **Files**: `data/tracks/synteny/<OrgA>__<OrgB>/` on MOOP (names sorted, so a pair has one home).
- **Tracks are generated, never hand-written**: for each computed pair, MOOP writes the synteny track into
  `metadata/jbrowse2-configs/tracks/synteny/` and shows it **in both genomes' browsers** under a
  `Synteny` folder ("Synteny › vs Pipistrellus kuhlii"). Same folder/naming rules as the track sheets.
- **Access**: a synteny track is visible only to someone who can open **both** genomes (the existing
  `loadSyntenyTracks()` rule, extended).
- **Admin page "Manage Synteny"**: the pair list with status, method, size, date, requests; buttons
  *add pair*, *mark for precompute*, *re-run*, *remove*; and a **file check** (each file + index answers
  through tracks.php — the same check we ran on the bat tracks today).
- **Housekeeping**: report pairs whose files are missing/unindexed (dashboard card), like the gene-track check.

The track-sheet columns ASSEMBLY_1, ASSEMBLY_2, BED1_PATH, BED2_PATH, MAF can be retired (they were never used).

## 5. Running the jobs

| Option | Use for | Why / why not |
|---|---|---|
| **A. Cluster batch (recommended default)** | precompute and approved requests | One command on the compute box, e.g. `moop-synteny run --pending`: reads the queue (exported from MOOP), submits one slurm job per pair (minimap2 or MCScan), runs `make-pif`, copies results to MOOP's `data/tracks/synteny/`, marks them done. Analysts never write a job script. Handles private/unpublished genomes. |
| **B. usegalaxy.org (optional)** | requests for **public** genomes (GCA_/GCF_) when the cluster is busy | minimap2 → PAF is available. But usegalaxy.org cannot reach MOOP (internal IP, no DNS), so genomes must be fetched there by NCBI accession or uploaded (~2 GB each); public server — not for unpublished genomes; per-user quotas; no MCScan. Result is downloaded back by MOOP (same pattern as MAFFT). |
| C. MOOP server itself | — | No: 15 GB RAM shared with the site; the BLAST queue already budgets it. |

Galaxy can be added later behind the same queue: the request record says *where* it ran, so A and B are interchangeable.

## 6. Requests — how a user asks, and how the admin sees it (brainstorm 2026-10-23)

**Starting point (checked 2026-10-23): MOOP has no request mechanism at all.** The only contact route
is a `mailto:` link to `admin_email` (footer, "no access" pages). **The server cannot send e-mail**:
nothing in MOOP calls `mail()` and postfix is not running. So notifications go to the dashboard first;
e-mail only if IT provides a relay later.

**Direction: one general request queue, synteny as its first kind.** Nothing exists yet, so build it once:
the same queue, dashboard card and admin page can later take "missing track", "add a genome",
"BUSCO for this gene set". Each request has a `kind` and a small kind-specific payload.

### 6.1 Where a user asks — where the need arises, not a separate form page
- **Genome browser page**: **Compare with another genome** → the genomes this user can open (grouped,
  e.g. bats first for a bat), each marked **Open** (pair exists) or **Request**.
- **Gene page (later)**: "See this region in…", same list.
- Users **pick from a list, never type a genome name**, so every request is an exact pair and duplicates
  merge automatically.

### 6.2 What a request holds
- One click + an optional note ("looking at the TAS1R cluster"). MOOP adds who, when, the pair (sorted,
  so A×B = B×A), and a suggested method from the taxonomy (minimap2 close, jcvi distant).
- A request for a pair already queued **adds to its count** ("4 people want this") instead of a new entry.
  The count is the demand signal for what to precompute next.

### 6.3 Who may request
- Logged-in users and the trusted network (IP_IN_RANGE). Anonymous public visitors see the
  `admin_email` link instead (no spam surface).

### 6.4 What the admin sees
- **Dashboard card**: "Synteny requests: 3 new (most wanted: Myotis × Eptesicus, 4 people)", in the
  shared `adm-card` style, like the other health cards.
- **Requests page** behind it: pair, who asked, notes, count, suggested method, status. Buttons:
  **Approve**, **Decline** (with a reason the requester sees), **Done**.

### 6.5 Getting approved pairs to the cluster
MOOP cannot reach the compute side, but the compute side already copies to MOOP. So:
- MOOP keeps an **approved-pairs file in the `pairs.tsv` format** (`config/synteny/pairs.tsv`).
- **Now:** a download button; the admin adds the lines to `pairs.tsv` on the cluster.
- **Later:** `submit_all.sh` fetches the file from MOOP itself, so approving on the dashboard is all it takes.

### 6.6 Closing the loop
- When results are copied to MOOP, the request **marks itself done** (MOOP finds the files; no manual step).
- The requester's **Request** becomes **Open**; a small **My requests** list shows Waiting / Ready / Declined.
- E-mail to the admin (new request) and the requester (ready): only once a mail relay exists.

### 6.7 Storage
- A small JSON file in `metadata/` (backed up with site data, written under a lock).
- Whatever path it uses **must be added to the writable allowlist** (`moop_permission_check_mode()`) and to
  `scripts/fix_moop_selinux.sh`. A missing entry is exactly how earlier features failed silently (CLAUDE.md §11).

### 6.8 Open questions (to decide before building)
1. One general queue for all missing data, or synteny only? *(leaning general, synteny first)*
2. Approve every request, or **auto-approve cheap ones** (close relatives, public genomes)?
3. Who may request: logged-in only, or the trusted network too?
4. Can requesters see each other's requests? The count ("4 people want this") helps; names may not.

## 7. Phases (each one usable on its own)

1. **Pilot, by hand (half a day of yours + one of mine)**: 3 bat pairs with minimap2 on the cluster
   (e.g. *M. myotis*–*P. kuhlii*, *M. myotis*–*M. lucifugus*, *R. aegyptiacus*–*P. alecto*), `make-pif` on MOOP,
   files into `data/tracks/synteny/`, one hand-made registry entry. Verify: track in both browsers, right-click
   launch, dual view, access rules, load speed. **This tells us file sizes and run times for everything below.**
2. **Registry + generated tracks + Manage Synteny page + file check.**
3. **Batch runner** (`moop-synteny run`) and the bat precompute list (~95 pairs).
4. **Request button + queue + notifications.**
5. **Gene-anchor (MCScan) synteny** for distant groups (cnidarians etc.), using MOOP's proteins/coords.
6. *(optional)* Galaxy route for public pairs.

## 8. Decisions for you

1. Reference genome(s) for bats (suggested: *Myotis myotis* + *Rousettus aegyptiacus*).
2. Cluster: is minimap2 there, and is JCVI installable (pip) for the gene-based route? Which partition/limits?
3. Who may request: logged-in only, or the trusted network too? Auto-approve close pairs?
4. Unpublished genomes (e.g. the *Leptonycteris*/*Macrotus*/*Musonycteris* consensus genomes, *Myotis septentrionalis*):
   cluster only, never Galaxy — agreed?
5. Which groups after bats.
6. Keep everything in MOOP's admin page, or do you also want a read-only "Synteny" tab exported to the spreadsheet for overview?

## 9. Lessons from the earlier runs (2024 chameleon jcvi run, bat minimap2 loop)

From the user's notes (`cerebro:~/sciproj/SBCHAMELEO/.../CCA2C/analysis/synteny/notes` and a minimap2 loop):

- **jcvi (MCScan-python) worked** on CDS nucleotides with `--no_strip_names --cscore=.99`, then
  `screen --minspan=30 --simple`. Those stay the defaults.
- **Most of the old script was per-source fix-up**: Ensembl header trimming + `blastdbcmd` subsetting,
  `fix_refseq_cds_fasta.pl` + the RefSeq feature table, `--key=ID` vs `--key=transcript_id` per GFF.
  MOOP's copies remove this: one GFF layout, CDS → mRNA → gene links for every source. One rule
  (drop `:cds`; header = mRNA ID, or a CDS ID whose Parent is the mRNA) matched 100% of the CDS
  headers in all five test gene sets (RefSeq Myotis, Helixer Bradypodion ×2 and Furcifer, MENDER Chamaeleo).
- **Names**: the old run showed bare Helixer IDs. MOOP's GFF carries assigned names (`Name=GPN1`), so
  the browser can show `GPN1|<ID>`: 89% of Bradypodion genes, 85% of Chamaeleo, all of Myotis.
  jcvi still runs on IDs (unique, no spaces); names go in only at the end, in BEDs and anchors together.
- **`shortenRef.pl` shortened chromosome names** for the karyotype plot. That is fine for plots, but never
  for files JBrowse loads: names must equal the assembly's, or the track loads and draws nothing.
- **Both directions were run** (A→B and B→A: 30 runs for 6 genomes). One direction per pair is enough.
- **Chamaeleo is now CCA3 (MENDER)**; the 2024 run used CCA2C. The old anchors do not fit and must be rerun.
- **minimap2 loop**: ran `asm20`, `asm10` and `asm5` for every pair (3× the cost) with `--cs` and the
  default 3 threads. Now: run all three presets in the pilot only, then choose by distance; drop `--cs`
  (`-c` already gives the CIGAR the browser draws); set `-t`. One direction is enough here too.
- **For minimap2 pairs, the PAF has no gene names.** Names come from each genome's gene track in the
  linear synteny view. Confirm in the pilot.

## 10. The pipeline — first draft: `config/synteny/` (2026-10-23)

Runs on the cluster; reads the **MOOP copies** of gene sets (not `$GENOMES`: the browser uses MOOP's IDs).
Analysts edit two TSVs: `genomes.tsv` (code → organism/assembly/gene_set) and `pairs.tsv` (A, B,
`genes`|`genome`, preset). `bash submit_all.sh` submits every pair that is not done. Results go to
`$SYNTENY_OUT/published/<OrgA>__<OrgB>/`. See its README.

Tested here on the real MOOP gene sets with jcvi and minimap2 stubbed out: prep (3–16 s per genome),
labelling, the refuse-to-publish check, caching, skip-when-done, and rerun-when-a-gene-set-was-rebuilt.

**Order (user, 2026-10-23):** annotation pipeline rerun → MOOP pipeline → synteny. All data is queued for
the annotation rerun, so the pilot waits for it. Nothing from synteny should be run on pre-rerun gene sets. **Not yet run for real.**

To settle in the pilot:
- **The label format.** `GPN1|ID` is the guess. Check how JBrowse's MCScan display shows a BED name. If
  it shows only the name, maybe drop the ID; if the right-click/search needs the bare ID, use `--style id`.
- **Whether gene-level `.anchors` or block-level `.anchors.simple` reads better** in the in-browser
  synteny track. Probably blocks in the genome browser and gene pairs in the synteny view.
- **CDS nucleotides vs proteins for very distant pairs** (chameleon vs chicken worked on CDS).
- **Where it lives:** `config/synteny/`, beside `config/build_and_load_db/`, because both are compute
  pipelines. It is a sibling, not inside build_and_load_db, so ownership stays clean. **Owned by the
  compute side** (run and maintained there); the web side does not edit it. **Later (user, 2026-10-23):**
  build_and_load_db and synteny should both move out of the MOOP app repo, perhaps into their own repos.
  Keep them free of MOOP-app paths so that move stays a plain `git mv` / split.

## 11. Size / effort estimates (to confirm in the pilot)

- minimap2 asm20, two ~2 Gb bat genomes: ~30–60 min, ~20–30 GB RAM (cluster node).
- PAF/PIF per bat pair: tens to a few hundred MB → ~95 pairs ≈ 5–20 GB on MOOP (643 GB free).
- MOOP-side code: registry + track generation + admin page ≈ 2–3 days; request flow ≈ 1–2 days; batch runner ≈ 1 day.
