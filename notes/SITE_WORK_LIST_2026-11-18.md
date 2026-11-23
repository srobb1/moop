# Site work list — 2026-11-18

The site is open to people. This is everything on the table, in the order to work it.
Size: **S** = minutes, **M** = an hour or two, **L** = a session or more. **TALK** = decide together first.
Tick items off here; add new ones at the bottom under "Inbox" and they get sorted in later.

Order of work, and why:
1. **Finish the move.** Nothing else matters if the sites don't load from outside.
2. **Wrong answers.** People are using it now; a search that gives a wrong result costs trust.
3. **The demo path.** Showing the site in a straight line is what makes the rest feel manageable.
4. **First impressions:** welcome text, the way back to Planosphere, the Tools menu.
5. **Small visual fixes,** done in one batch per site, so each one is one check, not twenty.
6. **Help text and examples.**
7. **The AI guide and API.**
8. **Tutorials.** Last, because they take pictures of the pages, and the pages are still changing above.

---

## 0. The move (in progress)
- [x] IT: outside access to simrbase.stowers.org + cuttingclass.stowers.org (161.129.22.133) — works 11-19
- [x] Phase B for simrbase + cuttingclass (11-19): links have no folder name, public_url set, simrbase-dev
      forwards, rate limits republished. No folder links on either home page; no-exec guard probed on both
      new names (`.php` in images/ blocked, control ran). Old internal `172.16.2.52/moop/` now loads unstyled.
- [x] IT: planosphere public DNS -> 161.129.22.133 (11-19 evening); Planosphere Phase B run: 0 folder links
      (was 38), tool links -> simrbase.stowers.org, guard identical + probe blocked. Internal DNS: Sofia says fine.
- [x] smedgd.stowers.org (alias of planosphere) showed the Red Hat test page -> 302 to Planosphere's SmedGD
      page (/etc/nginx/conf.d/moop-smedgd.conf; staged in /home/smr/move_day/nginx/). Make it 301 once settled.
- [x] CI red since 53162efc: also_paths test assumed ontology data; fixed in tests/smoke_tests.php.
- [ ] Keep `172.16.2.31 tracks.stowers.org` in `/etc/hosts` through IT's DNS changes

## 1. Wrong or surprising answers — fix first
- [x] **ID search is case-sensitive, and the lowercase search returns 2 rows.** — DONE 11-19: menu bar ignores case; search shows the typed ID's row (gene OR transcript), one row. `SMED30003001` works;
      `smed30003001` returns both the transcript `SMED30003001` and the gene `gene-SMED30003001`.
      Expected: case shouldn't matter, and one ID should give one result (or a clear "gene + its
      transcript"). Investigate the exact-ID path vs the text path. **M**
- [~] **Anatomy search "gut" lists transcripts that have no gene.** — 11-19: 157 fixed (d5657132, dd_v6 gene ids); rest = ids not in crosswalk -> questions for compute; open: show "no current gene" instead of "–"? Why — data (13 transcriptome
      "genomes" have no gene level) or a join that drops the gene? Then decide how to show them. **M**
- [x] DONE 11-20 (A): records looked up in their paper's assembly (site_config crosswalk 'ambiguous_ids': 5 papers placed from the data; lib/crosswalk.php moop_crosswalk_record_*/exclusions; records table, download, gene card, ID search, filter box, Data Exporter). Compute's per-paper check (B) on hold. **Anatomy records: a `Contig…` ID can show a gene from the WRONG assembly.** 11-20: compute ANSWERED (`notes/ANATOMY_IDS_FROM_COMPUTE_2026-11-19.md` §5: look up by source+ID; Contig >7727 = newmark_ests; ≤7727 needs their per-paper check) — web not done yet. Same name in up to 4
      crosswalk sources mapping to different genes (Contig1038). Needs a paper → source table from compute;
      then look each record up in its own source. `notes/ANATOMY_IDS_FOR_COMPUTE_2026-11-19.md` §5. **M**
- [x] **Anatomy records with no gene: say why, link to where it aligns.** DONE 11-19 — "no gene ⓘ" + reason
      + genome link, on the anatomy table AND the Rosetta Stone page (lib/crosswalk.php moop_crosswalk_unlinked /
      _reason / _no_gene_html), from compute's `unlinked` table.
- [ ] **Sofia: load the transcript-alignment tracks** (the sources' transcripts aligned to S3h1 — dd_Smed_v4,
      SmedASXL, ox_Smed_v2, ddv6, ... — so a "no gene" ID can be SEEN in the browser).
- [ ] **THEN: the "no gene" genome links open with that ID's source track turned on** (today they open at the
      right position with the default tracks only). Needs: source -> track name mapping (track sheet /
      config); pass it via jbrowse2.php's track parameter. Note: `sessionTrackId=track_<md5 of path>` breaks
      silently when a path changes (see todo_research_menu memory) -- prefer a `tracks=` param by management
      track name. **M**
- [x] **WHEN compute's `unlinked` table arrives: list every curated ID missing from all our FASTAs** — DONE 11-19: 688 IDs, `notes/CURATED_IDS_MISSING_FROM_ALL_FASTAS_2026-11-19.md` (+ .tsv, `scripts/curated_ids_missing_report.py`) —
      in neither `map` nor `unlinked`; grouped by paper and by ID set (dd_Smed_v4, OX_Smed, SmedASXL,
      Contig...), with counts, so Sofia knows which files to find. (Sofia asked 11-19.) **S**
- [ ] **Sofia: find the missing ox_Smed_v2 sequences** — Kao 2013's `OX_Smed_1.0.N` IDs whose numbers
      are not in the ox_Smed_v2 FASTA compute ran (21,411 of up to 23,545), e.g. `OX_Smed_1.0.00005`.
- [x] **Downloads (menu → Download):** clicking the chevron and clicking the name do different things.
      Both should open the whole group of files. **S** — DONE 11-19 (name was the checkbox label; now opens, checkbox selects)
- [x] **Ontology Browser:** remove ECO from the drop-down (GO + PLANA only); a link from Planosphere
      opens with PLANA already selected. **S** — DONE 11-18 3b4d43de (Planosphere links already passed ?o=PLANA)
- [x] **Old Planosphere video tutorials:** DONE 11-19 — 6 removed (Sofia's list), 7 kept; tutorials/page-database/tutorial pages (site data, backed up).
- [ ] **UCNE track is drawn in the WRONG place (Nvec; check Scolanthus too).** UCNE 80 is at chr1:1,918,153-1,918,259
      (paper table on the nematostella-ucne page, 107/107) = where UCNE BLAT draws it; the UCNE track draws
      ucne.chr1.1917354.1917461, 799 bp early. Check UCNE 58 (chr1:2,674,734): constant shift or not.
      Fix: remake the UCNE GFF from the page's table (Nvec200 coords) or drop it. Also add track
      descriptions + fix "Elemements" typo (Manage JBrowse / track sheet). Sofia made the files (cerebro
      .../aligned/ucne/). **M**
- [x] NOT A BUG (Sofia 11-20): a short name is given only for a very confident hit; otherwise the best hit's description is kept ("…-like"), and there is no short name to give when the hit has none. **Empty gene names** (from the AI test): S. mediterranea Ena/VASP has an empty `gene_name`
      though its description says "ENAH/VASPs family member"; many bat RefSeq VASP genes have no name
      while EVL/ENAH in the same species do. Pipeline side → write a note for compute (web doesn't
      touch the naming code). **S** (note) / compute fixes
- [x] **Gene card "copy" — does it copy too much?** — DONE 11-19: sections + indented evidence + link (Sofia chose). Look at what it puts on the clipboard; decide. **S**

## 2. Showing the site to someone — TALK
- [ ] **A demo route.** One linear path through the site, written down: e.g. home → search a gene →
      gene page cards → JBrowse → BLAST → MOOPmart → Planosphere link → AI guide. Each stop: what to
      click, the one sentence to say. Something to follow (or hand someone) instead of improvising.
      Becomes the skeleton of the tutorials in §7. **TALK, then M**

## 3. First impressions
- [x] **Welcome text on the SIMRbase home page:** DONE 11-19: content page `welcome` shown above the home (Manage Pages -> home = intro), + "Email us for a walkthrough" via {admin_email}. Edit it any time in Manage Pages; a pop-up was considered and NOT chosen (Turnstile already interrupts once). "Welcome to the new SIMRbase. SIMRbase MOOP —
      *moop* is a Scots word meaning to associate closely; SIMRbase MOOP does that with all of our
      organisms. Many organisms, one platform." (Wording to settle; goes through `t()`.) **S**
- [x] DONE 11-20: "Planosphere" link after Help in SIMRbase's main menu (config main_menu; opens a new tab like every off-site link). Favicon NOT added (needs an icon field in Manage Menu + a CSP img-src entry).
      **Way back to Planosphere from SIMRbase:** Planosphere's favicon/link in the SIMRbase menu bar,
      for someone whose Planosphere click landed them on SIMRbase and whose tab is gone. **S–M**
- [x] **Tools menu is too long and confusing.** Regroup, wider, a line of text per tool.
      DONE 11-19: headings (Browse & search / Expression / Sequences / Export) + one line per tool,
      opt-in per site (Manage Tools: "Use the suggested groups", headings, "Show a line of text");
      ON for simrbase only. Renamed: RNA-seq Expression Explorer, Expression by Anatomy. Endo-Atlas
      stays in Research only (decided). Ideas: `notes/TOOLS_MENU_AND_ATLAS_IDEAS_2026-11-18.md`.
      + the same line in a band under each tool page's header (tool_page_blurb(); one text, two places).
      Rosetta wording: never "old/older" (some IDs are just not the main ones).
- [x] **Page titles differ from menu names:** DONE 11-19 (Annotation Search, Retrieve Sequences; tab titles too). "Search Features by Annotation" (menu: Annotation Search),
      "Sequence Retrieval" (menu: Retrieve Sequences). Pick one name each. **S**
- [x] DONE 11-19 (all 6 = tests typed "/moop"; now moop_url_base() + shipped defaults): **smoke_tests read the LIVE config:** 4 tests fail on this host since the move to "/" (link prefix,
      main menu hrefs assume "/moop"); CI passes only because it has no config_editable.json. Make them
      hermetic. Also 2 failing on HEAD before 11-19: "gene sets of one organism can share IDs",
      "a paralog with no link pattern links to its gene page". **S**
- [x] 11-20 (F): DONE -- home chips, tool links, organism icon now same tab; LISTED in notes/NEW_TAB_LINKS_2026-11-20.md (140 off-site keep; 62 on-site: change home chips, tool buttons, organism picker; keep results lists) -- waiting for Sofia. **Links that open a new tab:** list every one on SIMRbase (we likely do it too often), then
      decide which keep it — usually only links that leave the site. **S** (list) / **M** (fix)

## 4. Visual fixes — batch them
SIMRbase
- [x] Gene page — **orthologs / gene-family cards: move the method footnote into an (i).** DONE 11-19: "How this was computed (i)", rule one bullet per category. The grey text under
      the card ("Computed with OrthoFinder 3.1.2 on one gene set per species ...; gene family N0.HOG...; Rooted
      with an outgroup ...; Sequence evidence: DIAMOND ... supported = E-value <= 1e-5 ...") is a block of
      technical text on the card. Put it behind an (i) (help_modal / popover). tools/pages/parent.php ~1073
      ($__cl_foot) and ~1124 (compgen-evidence). Also: the rule reads as code (`reciprocal_best =`,
      `no_hit`) -- write it as plain words in the (i). (Sofia, 11-19) **S**
- [x] Gene page — **Pathways card:** DONE 11-20 — tree in a tinted panel, class rows are teal bands with a turning chevron, "View on KEGG" an outlined button (lib/kegg.php, css/parent.css).
- [x] Gene page — **Feature Hierarchy** icon: DONE 11-20 — it (and Expression's) started as a sitemap/chart icon, and the toggle script then added minus/plus ON TOP of it; both now start as the minus like every other open card.
- [x] Ontology Browser — **chevrons too small;** DONE 11-19 (filled teal circle). a reviewer didn't know they were clickable. **S**
- [x] Term pages — **buttons don't stand out;** DONE 11-19 (solid main colour; link-outs outlined). give them colour. **S**
- [x] Ontology term page — **stage terms:** DONE 11-20 (site data, metadata/pages/stage-1..8.json) — fake "▻" table of contents and "back to top" links removed, in situ table + collapsible symbols list styled, Stage 1's empty Overview filled with the PLANA definition, and the raw FASTA dump (up to 170 KB, a 52,000 px page) replaced by "Get these N sequences" -> Retrieve Sequences with the same IDs (smed_20140614).
- [ ] **Sofia: stage pages listed more sequences than in situ genes** (Stage 5: 83 transcripts vs 28 genes in the table; every stage the same pattern, and some IDs' in situ images are from OTHER stages, e.g. SMED30000139 S3-S4 in Stage 5). Was the old page's FASTA deliberately wider, or did the table lose rows in the port? The new text says only what is true ("listed 83 transcripts ... including the 28 genes in the table"). **TALK**

Planosphere
- [x] Index — **bold** the sentence saying some links go to SIMRbase. **S** — DONE 11-18 (site data)
- [x] **Tools page** → home-page tiles: DONE 11-20 (wrapped in ps-home2 + ps-hero). Note: its PLANA Ontology tile still links to obofoundry.org, not SIMRbase's Ontology Browser (?o=PLANA) -- Sofia to decide.
- [x] **Molecular fate mapping** page centred: DONE 11-20 (wrapped in ps-paper; section boxes now match their tables' width).
- [x] **Movie page** centred: DONE 11-20 (ps-paper; buttons now Planosphere's ps-btn, not Bootstrap blue).
- [ ] **Image gallery** — larger section headers + a side menu to jump to a section (like the SIMRbase
      gene page). **M**

## 5. Help text and examples
- [x] **Example search terms** DONE 11-20 — chosen from the data (moop_curated_examples(), lib/home_examples.php, the same system as the home page): the most-curated gene (Smed: EU296635 = sFRP-1) under its other IDs, and the three most-curated papers. Click fills the box (Anatomy also runs it). Cached; never shows crosswalk rows the viewer may not see.
- [x] DONE 11-20: Rosetta Stone page 0.96 s -> 0.01 s and every Smed gene page ~0.5 s faster: `SELECT DISTINCT source FROM map` (3.7M rows) ran on each load; now cached on the file's date (moop_crosswalk_sources_present()).
- [ ] **Neoblast cluster page (Planosphere)** — the "translate IDs" text is confusing; rewrite. **S**
- [x] DONE 11-20 (D): "New to this?" section in the ⓘ (what an ontology is, why here, the 🔬/🧬 icons, PLANA vs GO); a new NAME still to talk about. **Ontology Browser help:** an (i) with more text — it's about genes and the terms they're tagged
      with; maybe a clearer name; a picture of the microscope icon explaining what it means. Written
      for someone new to the site and to tools like this. **M** (name = TALK)
- [~] 11-20 (H): gene-list side DONE (type first, then only the sources in the organisms picked; OMA reference species written out; types renamed BLAST homologs + Reciprocal best hits). LEFT: same for the output side ("Annotation types to include in TSV") + a page explainer. **MOOPmart:** the two annotation selectors (gene-list builder, output builder) are too long to
      scroll — needs a search/grouped picker; and new text explaining the page. **TALK, then L**
- [ ] **Manage Annotations: 13 saves report success even when the file was not written** (found 11-20) --
      every `saveJsonFile()` in `admin/manage_annotations.php` except the display-label one ignores its
      return value (colour, order, synonyms, ...). The silent-write-failure pattern. Check each, say so. **S**
- [x] DONE (home Getting started has "Ask an AI assistant", checked 11-23). **"Use this site with an AI assistant" -- make it more prominent** (Sofia 11-20): also put it in
      the Tools menu, the menu bar, or on the home page; KEEP it where it is now (footer). Pick which. **S**
- [ ] **Planosphere "how to curate" tutorial** — rewrite for the new site. **M**
- [ ] **Text updates Sofia wants** — places on the site (list to add here). **?**

## 6. AI guide (llms.txt) and the API
- [ ] **llms.txt overview:** what SIMRbase is for, and what MOOP means. **S**
- [ ] **Key for the naming tags** in descriptions — `[ISO|1to1|sim+|pthr+|tree+|hog]`,
      `[ISS|rbh|smed]` — so an assistant can tell a strong 1:1 ortholog from a weak similarity hit. **S**
- [ ] **Are we missing good questions?** Review the help page's task list against what people
      actually ask; add tasks. **M**
- [ ] **"All organisms" in one call:** `domain_genes` requires `organism` and takes ≤20, so "all" took
      8 calls. Add `organism=all` (or omit = all) where it's safe, with paging. **M**
- [ ] **Name lookup took >45 s** across 4 batches — measure (cold vs warm), then fix. **M**
- [ ] **More API functions** as the task review turns them up. **L**

## 7. Tutorials (big)
- [ ] **Picture + text tutorials** for the same questions as the AI help page — screenshots of each
      step, one page per question. Built on the demo route (§2). After §3–§5, so the pictures don't go
      stale straight away. **L**

## 8. Technical leftovers (mine)
- [ ] `lib/jbrowse/tabix_index_tracks.sh` lines 131, 180: `sort` spills to /tmp (4 GB) — same fix as
      6b6409c2. **S**
- [ ] Old Claude scratch folders in `/tmp/claude-1002` (2.7 GB of the 4 GB /tmp) — delete old ones. **S**
- [ ] (Parked) Manage JBrowse re-prep as a background job instead of holding the page open — only
      needed if a much larger gene set comes along. **M**

## Ongoing — no end date
- Adding data.
- Adding features.

## Inbox — new items go here
- [ ] **🔴 Old SIMRbase links cited in papers (11-23) — TABLE BUILT, ROOT TO INSTALL:** metadata/legacy_redirects.json
      (73 rules: the Drupal dump url_alias table + the System Monitor outside-404 list; all 2,185 old addresses tested -> 301 -> 17 pages, all 200). Tested on a
      private nginx: every old address -> its page, attack paths 444, MOOP's own paths untouched. Builder gained
      `prefixes` (old folder -> one page here). **Root install:**
      1. `php scripts/build_legacy_redirects.php /var/www/html/moop /tmp/moop-legacy` (as smr), then
         `sudo install -m644 /tmp/moop-legacy/legacy-*.conf /etc/nginx/moop-sites/moop/`
      2. `/etc/nginx/conf.d/moop-sites.conf`: add `include /etc/nginx/moop-sites/moop/legacy-map.conf;` next to the
         other two map includes, and `include /etc/nginx/moop-sites/moop/legacy-server.conf;` in the
         simrbase.stowers.org 443 block right after the add_header lines (before the /moop 308).
      3. Themed 404 for every unknown address (Sofia 11-23): in EACH of the three 443 server blocks add
         `error_page 404 /tools/not_found.php;` (server level). PHP's own 404 pages ("Gene not found") still pass
         through, because php.conf's location sets its own error_page (5xx only). Missing *.php files still show
         php-fpm's "File not found." -- old Drupal links never end in .php, so left alone.
      4. `sudo nginx -t && sudo systemctl reload nginx`; check `curl -sI https://simrbase.stowers.org/starletseaanemone`.
         Then `curl -s https://simrbase.stowers.org/no-such-page | grep -o '<title>[^<]*'` -> Not found — SIMRbase.
      **Still open:** (a) Endo-Atlas was cited as `http://simrbase.stowers.org:8888/` (He et al. 2023 Curr Biol) —
      nothing listens on 8888 now; catching it needs an nginx `listen 8888` block returning 301 to
      /tools/spatial_atlas.php?atlas=endoatlas AND the port opened (firewalld + IT perimeter). (b) Bats (0/48) and
      killifish are NOT public on MOOP but were on old SIMRbase and are cited — Sofia to decide. (c) Old
      NV2g.20240221.* files -> NV2 Downloads, but MOOP's genes.gff carries added names/notes, so it is not the
      cited file byte-for-byte. (d) /analysis/N: only 304/341/342 known; others -> home. Re-check the monitor's
      404 list in a week and add what shows up. **M**
- [ ] (PARKED 11-23 -- Sofia chose: llms.txt tells the assistant to send the person to Claude Code; help page says the same) **SIMRbase connector (MCP server) for the Claude app / other chat apps (Sofia, 11-23):** a visitor using Claude
      desktop (chat, not Code) + Claude in Chrome could not use the API: (1) llms.txt was behind the human check
      (Claude-User 403 + Chrome 302 in the request log, 11-20) -- FIXED 11-23, llms.php exempt like api/v1;
      (2) chat-app web fetch only opens addresses already in the conversation, so it cannot fill in the guide's
      {organism}/{id} templates ("Claude can't write the scripts" without Code). Fix: /mcp endpoint (MCP over
      streamable HTTP, JSON-RPC) exposing the api/v1 functions as tools (search, gene, annotations, orthologs,
      sequences, translate, catalog...); later the polite BLAST. Public data only (no login through it at first).
      Help page + llms.txt: "add the connector" steps. Enterprise plans may need an admin to allow custom
      connectors (IT). Rate limits: api class. **M-L**
- [ ] **Agent API: run BLAST politely (Sofia, 11-23):** api/v1 endpoint so an AI agent can submit a BLAST and
      collect the result -- through the SAME blast_queue.php queue (flock, bounded, never stuck), submit returns a
      job id + "check back in N s", poll endpoint, small per-client limits (one running job, max query length /
      sequences / max_target_seqs), results as JSON + Markdown. Document it in llms.txt + help topic ai-assistants.
      Must sit in rate_limit_heavy (CLAUDE.md bot protection). **M**
- [ ] **Make the bats public (Sofia, 11-23):** 0 of 48 bat assemblies are public on MOOP, but old SIMRbase had them
      open, the Bats group text says "all 50 publicly available bat genomes", and the Camacho et al. 2024 links
      (/bats, /bats/data, /node/2060027) now redirect to the Bats group -- an outside visitor sees an empty list
      until this is done. Admin -> Groups/Manage Organisms: add PUBLIC (or public: true) to each Bats entry. **S**
- [ ] **Sofia: review killifish access (11-23):** Nothobranchius furzeri (GCF_043380555.1, RS_2024_12) is not public;
      old SIMRbase listed killifish as public, and /killifish now redirects to its organism page. Decide. **TALK**
- [ ] **Copy the original published Nvec files in, for history (Sofia, 11-23):** papers cite
      /files/pub/nematostella/Nvec/genomes/Nvec200/... (NV2g.20240221.gff/.gff.gz/.transcripts.fa, Nvec200.fasta,
      kc_isoseq, wien_nvec200_v1, nv2g_blastx_20231129/...). MOOP's NV2 genes.gff has added names/notes, so it is not
      the cited file. Copy the originals (from $GENOMES / the old server) to a served "published files" place, then
      change the redirect `prefixes` rule for that folder to point at them instead of the Downloads page. **M**
- [x] **DONE inside Stowers 11-23 (SELinux http_port_t 8888, firewalld 8888/tcp, /etc/nginx/conf.d/moop-endoatlas-8888.conf; curl -> 301 -> Endo-Atlas 200). LEFT: phone test from outside; if it times out, IT opens 8888 inbound.** **EndoAtlas old address http://simrbase.stowers.org:8888/ (He et al. 2023) (11-23):** a redirect works, but
      something must ANSWER on 8888 first -- a browser connects to port 8888 directly, so nginx on 80/443 never
      sees the request. Needs (root): an nginx block
      `server { listen 8888; listen [::]:8888; server_name simrbase.stowers.org;
                return 301 https://simrbase.stowers.org/tools/spatial_atlas.php?atlas=endoatlas; }`
      + SELinux `semanage port -a -t http_port_t -p tcp 8888` (if 8888 is not already an http port)
      + `firewall-cmd --permanent --add-port=8888/tcp && firewall-cmd --reload`
      + IT: allow 8888 inbound at the Stowers firewall (it worked before, so it may still be open).
      Test: `curl -sI http://simrbase.stowers.org:8888/` -> 301. **S** (root + IT)
- [x] DONE 11-23 (about.php + Admin -> Pages slug "about"; Sukha to confirm the tool list). **About page: "About SIMRbase" + "About MOOP" (Sofia, 11-23):** site section = an editable content page shown at
      the top of the built-in About (each site its own); MOOP section rewritten. SIMRbase: since 2012; easy search,
      sharing with collaborators, published data; one-organism vs related-species searches; pipeline by Sukha Malladi
      (main developer), Eric Ross + Sofia (directors/advisors); naming rule (good RefSeq/Ensembl or curated name kept,
      else from annotations); how to cite; contact. MOOP: built from scratch by Sofia; cite JBrowse 2 (+CLI, MAF
      plugin), BLAST+, Primer3, samtools faidx, bgzip/tabix (htslib), Plotly.js; data credits KEGG, GO, PLANA, NCBI
      Taxonomy, Wikipedia. NOT MAFFT (not used now). Pipeline sources loaded (Nvec DB): InterProScan 5.78, eggNOG-mapper
      / eggNOG 5.0, OMA 2.7, DIAMOND + MMseqs2 RBH vs Ensembl 113 + Swiss-Prot, HGNC, DeepLoc 2.0, DeepTMHMM, SignalP,
      BUSCO, TransDecoder -- Sukha to confirm. **M**
- [x] DONE 11-23 (4 added). **More news items (11-23):** suggested KEGG (11-14), Endo-Atlas (11-18), search View summary/matches (11-23),
      Expression Explorer (10-22). Admin -> News. **S**
- [x] **Home page version 2 (11-23):** Getting started + What's new (Admin -> News), opt-in home mode 'panel'.
- [ ] **Organism page: tidy the "Compare assemblies and gene sets" card (Sofia, 11-23):** the table's
      columns run into each other; the BUSCO "%" reads as part of the BUSCO bar; the "Assemblies:" label is
      too long. Mockups first (tmp/home-mockups/), then build. **M**
- [x] **"Send us a message" form (11-23):** footer link on every page -> tools/feedback.php (question /
      request / problem, optional email, page they came from). Inbox: Admin -> Messages
      (admin/manage_feedback.php); dashboard banner counts new ones. Stored in the private folder next to
      users.json and backed up. Email notice of new messages waits for email (notes/EMAIL_ALERTS_PLAN.md #14).
- [ ] **Cutting Class paper PDF is missing (Sofia to find it, 11-23):** About and FAQ (en + es) link to
      `https://cuttingclass.stowers.org/pub/pdfs/cuttingclass.pdf`, which is not on this server, so the link
      lands on the home page. Once found: put it in the site (or link the published paper) and fix the 4 links
      in cuttingclass metadata/pages/about.json + faq.json. **S**
- [x] **Remote tracks did not load from outside Stowers** (user in Austria, 11-19) -> tracks proxy LIVE
      (notes/TRACKS_PROXY_PLAN.md). Ask the user to hard-reload. Manage JBrowse's save no longer drops it.
- [ ] **smoke_tests.php is not hermetic:** 4 link/menu tests read the LIVE link_prefix and fail on any
      site served at its root (simrbase since 11-19). Pin link_prefix in the test. **S**
- [ ] **setup-check guard check is wrong for a site with its own server block** (found 11-19 after Phase B):
      it compares `default.d/<site>-security.conf` to a ROOT-keyed guard once link_prefix is "/", and says
      "Re-deploy". That file still guards the old `/moop/` address — re-deploying would UNGUARD it. It should
      compare `/etc/nginx/moop-sites/<site>/guard.conf` (the new server block's include) instead, and probe
      over HTTPS on public_url. **S–M**
- [x] DONE 11-20 (G): deleted after checks (same data, built_at only; last read 11-19 10:59, before 53162efc went live; pages, admin, setup-check fine without it). **Double-check, then delete Planosphere's duplicate anatomy file** —
      `/var/www/html/planosphere/organisms/Schmidtea_mediterranea/anatomy_expression.sqlite`. Unused since
      53162efc (Planosphere = static pages on SIMRbase data; its anatomy tool + API are off). Identical to
      SIMRbase's copy (77,432 records, same contents; only the build date differs). Before deleting, check
      nothing on Planosphere still opens it: no page/API reads it, and setup-check/housekeeping/the admin
      Organisms page don't complain once it is gone (move it aside first, look, then delete). **S** (11-19)
- [x] DONE 11-19: **"no gene" hover is wrong for 170 dd_Smed_v6 GENE IDs** — their transcripts are in `unlinked`, but the hover says "not in the reference data"; `moop_crosswalk_unlinked()` should fall back to the gene's transcripts. **S** (found 11-19)
- [x] DONE 11-20: **Long admin jobs locked up every other page for that admin** (found 11-19 ~21:54).
      jbrowse_register_assembly.php + jbrowse_reprep_gff.php now call session_write_close() after
      admin_init (neither writes $_SESSION). Proven: dashboard 0.09 s while a re-prep ran in the same
      session. The minutes-long jobs were ~9 s each of real work queued one behind another via the
      lock, + the 11-19 DNS timeouts (DNS answers in 5-35 ms again on 11-20). Other admin endpoints
      are all under 30 s; release the lock the same way if a new long one appears.
- [x] DONE 11-19: **Taxonomy group pages asked Wikipedia live on EVERY load** (groups.php?taxonomy_rank=;
      8 s each during the DNS outage, mostly crawler hits). Now: our own text from group_descriptions.json
      first (Cnidaria, Mollusca...), else Wikipedia through a saved copy (moop_wikipedia_taxon(), ~30 days).
      Photo was already saved locally (getGroupImagePath).
- [x] DONE 11-20: **Crawlers.** Turnstile ON (11-19 eve). 11-20 by 07:00: 24k requests (was ~1k/day) --
      Meta `meta-webindexer` ~9k (10k KEGG maps, all 403 by our human check, never reached KEGG), rotating
      scrapers 145.223.x.x (1,302 addresses) + 84.75.x.x, Azure 20.245/172.184; 3,924 challenges, 2 passed.
      Server fine (CPU peak 32%, fpm 19/28, 15 x 429). Fixed: meta-webindexer in robots.txt, nofollow on KEGG
      map links (f97a4a30); api/v1 exempt from the human check -- it had 403'd every assistant (a6d5fa16);
      heavy-group advice counts only internet requests. Watch: fpm busy peak 19 of 28.
- [x] NOT A PROBLEM 11-20: **Filesystem Permissions page "106 s"** -- normally ~4 s (live re-check by
      design, CLAUDE.md §10). The 106 s loads (3, all 11-19 21:05) and Manage Organisms' 117 s were the
      session-lock queue above, now fixed.
- [ ] **Old SIMRbase addresses printed in papers need redirects** (Sofia 11-19). Published papers cite
      URLs on the old Drupal SIMRbase -- e.g. `simrbase.stowers.org/bats` may be one -- that now land on
      MOOP and likely 404. Find them: (1) search the old SIMRbase Drupal **Postgres dump** for typed
      paths -- `url_alias` (path aliases), `menu_links`, node bodies with `simrbase.stowers.org/...`;
      (2) ask Sofia for the papers she knows of; (3) check the request log for 404s on old-looking
      paths (`{cache}/metrics/requests-*.tsv`). Then write `metadata/legacy_redirects.json` for simrbase
      and build the nginx rules with `scripts/build_legacy_redirects.php` (as cuttingclass does; simrbase
      has no list yet). ⚠️ Never redirect a path MOOP uses itself (/admin, /tools, ...). **M**

- [x] DONE 11-20 (search was right; the selector hid it): filter-revealed row now = that gene set; counts line offers "Pick single gene sets" (turns Details on). **Annotation Search: cannot limit to ONE gene set of an organism** (Sofia 11-20). Picking an
      organism searches all of its gene sets; there is no way to pick just one (e.g. only S3h1 for Smed).
      Check the scope selector's mode on that page (multi-select at organism level?) vs Retrieve/BLAST,
      which pick a gene set. **S–M**
- [ ] **Sofia: stage pages — more sequences than in situ genes** (see §4 SIMRbase stage-terms entry, 11-20). Decide
      whether the old FASTA was meant to be wider or the tables lost rows in the port. **TALK**
- [x] DONE 11-20 (E): tile -> SIMRbase Ontology Browser. **Sofia: Planosphere Tools page "PLANA Ontology" tile** links to obofoundry.org — switch to SIMRbase's
      Ontology Browser (`{simrbase}/tools/ontology_browser.php?o=PLANA`)? **S** once decided
- [ ] **Sofia (root): SIMRbase site-data backup can't be written by smr** — old copies are 644 apache, so
      `scripts/backup_site_data.php` fails (housekeeping still copies hourly). One-time fix:
      `sudo find '/var/www/moop-site-data' -path '*/.git' -prune -o -type d -exec chmod 2770 {} + -o -type f -exec chmod 660 {} +` **S**
- [ ] **Email from the site** (Sofia, 11-23) -- WHAT should send one: notes/EMAIL_ALERTS_PLAN.md (21 items:
      at once / daily digest / weekly). Do AFTER zero-result searches, referrers, outside 404s, feedback link. This host cannot send mail today (no sendmail/postfix,
      no SMTP settings in MOOP). Worth having for: an alert when a REAL person (passed the human check,
      signed in, or institute network) hits a server error -- for now the System Monitor shows these in
      red; a weekly "who used the site" digest; housekeeping failures; password-reset / account-request
      mail. Needs: IT's SMTP relay host (Stowers), a small mail helper (lib/), an admin address setting,
      and setup-check + INSTALL.md entries. Rate-limit alerts (at most one per hour per kind). **M**
- [ ] **Outside links that 404** (11-23): old Tripal SIMRbase/Planosphere URLs in papers and bookmarks.
      nginx logs the referrer; the PHP log never sees missing files. Needs scripts/moop_monitor.py to keep
      internet 404s with their referrer (then root re-runs install_monitor.sh) + a monitor card listing
      them, so we know which redirects to add. **M**
