# Where we stopped, Monday 2026-10-06

## 1. Code: all on `main`, pushed (a3db4b1 .. c230358)
- Naming decisions table: every step's inputs are columns (Swiss-Prot best hit, Human_hits_ranked,
  Human_tie_family, OMA_checks, Naming_species_hits, PANTHER_family_used, InterPro_domain, TE_Pfam_domain,
  Best_hit_each_search, RBH_each_species, Closest_human_used_for_naming). Names unchanged.
- `scripts/check_naming_decisions.pl naming_decisions.tsv`: replays every name from its row. Fly benchmark,
  Congeria and all end-to-end test tables: every name replays, nothing disagrees.
- RBBH: eross files are no longer made or loaded (MMseqs2 RBH only; `LOAD_EROSS_RBBH=1` brings them back as
  "(DIAMOND RBH)"). They shared their source name with the DIAMOND homologs and were MERGED into them in the
  database (60 organisms on the site, 886 sources). The loader now dies on two types under one source.
- copy2moop_v2.sh: logged "SAME, nothing to do" after updating files (counted rsync '>' not '<'). Fixed.
- DIAMOND homologs: a missing db_version.txt no longer writes a blank source version.
- Scoping audit (Perl::Critic, installed in ~/perl5): two hidden variable names removed; nothing else changed output.
- Methods: 9-step overview, -like cases table, §9 labelled as tests, §10 -like/paralog bullet; quick reference
  `notes/NAMING_QUICK_REFERENCE.md` (print copy: `naming_review/NAMING_QUICK_REFERENCE_print.html`).

## 2. Data
- **Congeria_kusceri is the reference organism on the live site** (rebuilt and copied 13:44): every source matches
  its file (91/91), naming replays (43,768 genes), all 10 statement kinds (5,739 `identical`), DIAMOND and MMseqs2
  human hits separate, no eross. Backups: `organism.sqlite.before_2026-10-02`, `.before_2026-10-06`, `.before_2026-10-06b`.
- Other organisms on the site still have: merged DIAMOND/eross homolog tables (59), version labels with a tab
  (Anoura, Nematostella, Parastichopus) or blank (Notamacropus T2T_SIMR_ANNOT, S. polychroa GCA_044892525.1,
  Phagocata). S. polychroa Spol_FtD1 is active but has no annotations loaded. All fixed by a rebuild.
- No rebuilds until the annotation pipeline is re-run (fixes in progress, then the queue).
- S. mediterranea: the live database (431 MB, 2026-07-31, 5 gene sets) is fine; there is no local database (an
  empty file made by a Claude sqlite3 query on 10-05 was deleted). dd_v6 (active since 09-02) was never built.
- `scripts/audit_files_vs_db.py organism.sqlite GENE_SET_DIR...`: every annotation source against its files. The build
  runs it after loading and before the copy, and stops on a mismatch (report: `<organism>/audit_files_vs_db.tsv`;
  `MOOP_SKIP_AUDIT=1` skips it). Tested in a Congeria build (91/91, 9 s). It will stop rebuilds of organisms whose
  database does not match until they are rebuilt with --reload.

## 3. For the site agent
- Card: label for statement kind `identical` ("Identical proteins"); naming steps 1-9.
- RBBH tables are MMseqs2 only now; design against Congeria, not the organisms still showing merged tables.

## 4. Waiting
- Schmidtea naming plan: published Smed names -> named Smed FASTA -> naming species for the other worms; after the
  Smed annotation run. Compara stays a naming input (decided 10-06).

## 5. Later the same day (copied to the site 16:29)
- InterProScan parser kept only the LAST match per protein and analysis (every organism: Congeria lost 71% of
  InterPro entries, 70%/56% of GO, 32% of Pfam). Fixed (8bb4e84, Hamap direction 182b30a). Found by the site agent.
- Protein Features: SignalP, DeepTMHMM and a new DeepLoc table (all proteins) as their own annotation type (3573ca0).
- New statement kind `protein` (order 5; later kinds +1): the protein a name rests on, its mRNA and lengths (0b8e385).
- "MOOP name" statement without evidence codes (4f38368).
- notes/ANNOTATION_SCORES.md: what every table's Score holds, for the site's score lookup.
- Congeria on the site now has all of this (audit 92/92, naming replays 43,768). The previous site version is
  `organism.sqlite.before_2026-10-06c`. Every other organism needs a --reload rebuild for the InterProScan fix too.
- Stopping a build: kill its process group, not only the parent (a loader survived once; harmless under --reload).
