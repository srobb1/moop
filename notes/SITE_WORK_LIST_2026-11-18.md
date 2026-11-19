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
- [ ] IT: outside access to simrbase.stowers.org (firewall NAT) — the firewall contact is working on it
- [ ] Phase B when outside access works — `/home/smr/move_day/PHASE_B.txt`, then I walk the 3 names
      and grep for leftover `/moop/` links
- [ ] Keep `172.16.2.31 tracks.stowers.org` in `/etc/hosts` through IT's DNS changes

## 1. Wrong or surprising answers — fix first
- [x] **ID search is case-sensitive, and the lowercase search returns 2 rows.** — DONE 11-19: menu bar ignores case; search shows the typed ID's row (gene OR transcript), one row. `SMED30003001` works;
      `smed30003001` returns both the transcript `SMED30003001` and the gene `gene-SMED30003001`.
      Expected: case shouldn't matter, and one ID should give one result (or a clear "gene + its
      transcript"). Investigate the exact-ID path vs the text path. **M**
- [~] **Anatomy search "gut" lists transcripts that have no gene.** — 11-19: 157 fixed (d5657132, dd_v6 gene ids); rest = ids not in crosswalk -> questions for compute; open: show "no current gene" instead of "–"? Why — data (13 transcriptome
      "genomes" have no gene level) or a join that drops the gene? Then decide how to show them. **M**
- [ ] **Anatomy records: a `Contig…` ID can show a gene from the WRONG assembly.** Same name in up to 4
      crosswalk sources mapping to different genes (Contig1038). Needs a paper → source table from compute;
      then look each record up in its own source. `notes/ANATOMY_IDS_FOR_COMPUTE_2026-11-19.md` §5. **M**
- [x] **Downloads (menu → Download):** clicking the chevron and clicking the name do different things.
      Both should open the whole group of files. **S** — DONE 11-19 (name was the checkbox label; now opens, checkbox selects)
- [x] **Ontology Browser:** remove ECO from the drop-down (GO + PLANA only); a link from Planosphere
      opens with PLANA already selected. **S** — DONE 11-18 3b4d43de (Planosphere links already passed ?o=PLANA)
- [ ] **Old Planosphere video tutorials:** remove the ones that teach the OLD website. Keep the PLANA
      and general PAGE ones. **S** (Sofia picks which)
- [ ] **Question: the UCNE BLAT track** — are those the other species' regions, aligned to this genome?
      Answer it, then say so in the track's description. **S**
- [ ] **Empty gene names** (from the AI test): S. mediterranea Ena/VASP has an empty `gene_name`
      though its description says "ENAH/VASPs family member"; many bat RefSeq VASP genes have no name
      while EVL/ENAH in the same species do. Pipeline side → write a note for compute (web doesn't
      touch the naming code). **S** (note) / compute fixes
- [ ] **Gene card "copy" — does it copy too much?** Look at what it puts on the clipboard; decide. **S**

## 2. Showing the site to someone — TALK
- [ ] **A demo route.** One linear path through the site, written down: e.g. home → search a gene →
      gene page cards → JBrowse → BLAST → MOOPmart → Planosphere link → AI guide. Each stop: what to
      click, the one sentence to say. Something to follow (or hand someone) instead of improvising.
      Becomes the skeleton of the tutorials in §7. **TALK, then M**

## 3. First impressions
- [ ] **Welcome text on the SIMRbase home page:** "Welcome to the new SIMRbase. SIMRbase MOOP —
      *moop* is a Scots word meaning to associate closely; SIMRbase MOOP does that with all of our
      organisms. Many organisms, one platform." (Wording to settle; goes through `t()`.) **S**
- [ ] **Way back to Planosphere from SIMRbase:** Planosphere's favicon/link in the SIMRbase menu bar,
      for someone whose Planosphere click landed them on SIMRbase and whose tab is gone. **S–M**
- [ ] **Tools menu is too long and confusing.** Regroup, wider, a line of text per tool.
      Ideas already in `notes/TOOLS_MENU_AND_ATLAS_IDEAS_2026-11-18.md`. **TALK, then M**
- [ ] **Links that open a new tab:** list every one on SIMRbase (we likely do it too often), then
      decide which keep it — usually only links that leave the site. **S** (list) / **M** (fix)

## 4. Visual fixes — batch them
SIMRbase
- [ ] Gene page — **Pathways card:** make the tree and the link-out stand out; one line is easy to miss. **S**
- [ ] Gene page — **Feature Hierarchy** icon doesn't match the other cards. **S**
- [ ] Ontology Browser — **chevrons too small;** a reviewer didn't know they were clickable. **S**
- [ ] Term pages — **buttons don't stand out;** give them colour. **S**
- [ ] Ontology term page — **stage terms** have old/ugly formatting; bring to the new style. **M**

Planosphere
- [x] Index — **bold** the sentence saying some links go to SIMRbase. **S** — DONE 11-18 (site data)
- [ ] **Tools page** → the square-tile layout of the welcome/index page. **M**
- [ ] **Molecular fate mapping** page — items not centred like the other pages. **S**
- [ ] **Movie page** — items not centred. **S**
- [ ] **Image gallery** — larger section headers + a side menu to jump to a section (like the SIMRbase
      gene page). **M**

## 5. Help text and examples
- [ ] **Example search terms** for the Rosetta Stone, and Anatomy Search's publication and ID searches. **S**
- [ ] **Neoblast cluster page (Planosphere)** — the "translate IDs" text is confusing; rewrite. **S**
- [ ] **Ontology Browser help:** an (i) with more text — it's about genes and the terms they're tagged
      with; maybe a clearer name; a picture of the microscope icon explaining what it means. Written
      for someone new to the site and to tools like this. **M** (name = TALK)
- [ ] **MOOPmart:** the two annotation selectors (gene-list builder, output builder) are too long to
      scroll — needs a search/grouped picker; and new text explaining the page. **TALK, then L**
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
- 
