# cuttingclass on MOOP — plan

Started 2026-11-03. A second MOOP site, **cuttingclass**, to replace
https://cuttingclass.stowers.org (Drupal 7 + Tripal on CentOS 7 / Apache 2.4.6 / PHP 5.4 — all
long unsupported). It runs on **another machine**, from the **same codebase** as simrbase.

Audience: high-school students and teachers. The point is that a student does not feel lost.

---

## The rule that governs everything here

> "i want any changes we make to create cutting class to not be defaults. i want to make sure
> all of our changes allows us to keep simrbase the way it is." — Sofia, 2026-11-03

- Every cuttingclass feature is a **per-site setting whose absence means today's behaviour**.
  simrbase never sets any of them; it must not have to add anything to stay the same.
- A feature's code runs only when its setting is on. With it off, output is identical (e.g.
  the translation lookup returns its input unchanged when there is no catalog).
- One codebase, two configurations. No fork, no branch.
- **Proof, not assumption:** step 0 below is built first and run before every commit in
  this work.

Bug fixes are different: a fix that corrects simrbase's output (like the primer checker on
transcript assemblies, below) is decided on its own merits, with Sofia's go-ahead.

---

## Build order

### 0. simrbase render-comparison script — FIRST
Save ~30 real simrbase pages (home, search results, organism, assembly, gene set, gene page,
BLAST form + a result, Primer Maker, Primer BLAST, Exporter, help, glossary) as PUBLIC and as
admin, then compare byte-for-byte after each change. Things that legitimately vary per request
(CSRF token, asset `?v=` stamps, timestamps) are normalised by name, never by "ignore the diff".
Puppeteer for rendered DOM where JS builds the page; curl for server HTML. Must be shown to go
RED on a deliberate one-character change before it is trusted.

### 1. Tools on/off (admin page)
- `config/tools_config.php` stays the **shipped defaults** and is never written by the web.
  Letting the web server rewrite a `.php` file turns any write bug into code execution; MOOP's
  hardening rests on the web never writing PHP it runs (CLAUDE.md §11).
- Overrides (enabled, order, label) live in `config_editable.json` under one key; the admin
  page edits only that. No key → every tool exactly as today.
- Off means off: hidden from navbar + toolbox, AND the tool's own pages answer 404. A hidden
  tool still runnable at its URL is not off.
- cuttingclass keeps: **BLAST, gene search, Primer Maker** (and probably Primer BLAST).

### 2. Content pages
- Admin creates a page: title, address (slug), English body, Spanish body, images.
- Stored as site data (files under metadata/ or the site-data tree), not in organism.sqlite.
- Pages needed: **About** (today's cuttingclass front-page text), **Protocols** (Planarian
  Maintenance, Observing Regeneration, RNAi, …), **FAQ**, **Primers** table, Useful Links,
  Contact, Anatomy, About Our Planarians, Glossary of Terms.
- Text already exists in both languages on the Drupal site — reuse it.
- OPEN: editor — plain formatted text (simple, safe) vs a WYSIWYG editor (friendlier, more
  to secure). Body HTML must be sanitised even though only admins author it.
- Content pages become searchable from the site search (opt-in).

### 3. Home page choice + menu builder
- Home page: the built-in MOOP home (default) or any content page.
- Sofia leans to **keeping a MOOP-like home**: search (genes, organisms, protocols) + tools.
- Menu builder: navbar drop-downs made of pages / tools / links (e.g. "Protocols ▾",
  "About ▾"), replacing Drupal's sidebar. No menu config → today's navbar.
- Sofia, 2026-11-03: add items to the MAIN menu — either a single link ("Protocols" → the
  Protocols page) or a drop-down ("Protocols ▾" → one entry per protocol page). Today's About
  and Help are hand-written in includes/toolbar.php, like the Tools menu was: build the main bar
  the same way (registry/config defaults that reproduce today's bar byte-for-byte, the site's own
  layout on top), reusing the Tools-menu machinery (ConfigManager, ordering UI, dividers).

### Done so far
- Step 0 render comparison — bd5a2954. Step 1 tools on/off + cross-links — 3b0b10f3.
  Menu + toolbox from the registry, ordered/labelled per site — 22998a58.
- Step 2 content pages — Manage Pages (Markdown, EN/ES tabs, live server-side preview),
  page.php?p=<slug>, metadata/pages/<slug>.json (snapshot + permission rule). Parsedown 1.8
  (Composer) in safe mode — 1304e636.
- Step 3 main menu (links, drop-downs, pages placed from their editor) — 8627a299; home page
  (MOOP's / a page above it / a page instead) — 3f532293.
- Languages as a site setting, RETIRED like organisms (text never deleted) — a6963190.
- Step 5 part 1, the visitor language switch: ?lang= > moop_lang cookie > Accept-Language >
  English; only on sites with 2+ languages, never on admin pages; Vary + Content-Language.
  Switches content pages, menu labels and the home now. NEXT: interface strings (t()).
- Still open from this list: tool labels per language (tools_layout labels are one string today),
  image upload for pages, simple mode, old-link redirects, optional taxonomy tabs.

### 4. Old-link redirects
The published manuscript PDF and teachers' bookmarks point at `/en/node/121412`,
`/es/node/121411`, `/en/faq`, `/en/planarians` … Map each to its new page and 301. Collect the
full list from the Drupal site before it is switched off.

### 5. Spanish interface (the largest piece)
- **Visitor pages only.** Admin pages stay English.
- Language: browser `Accept-Language` on first visit, an EN | ES switch that overrides and is
  remembered (cookie), `<html lang>` set, `Vary`/cache handled so a cached English page is
  never served to a Spanish browser.
- Catalog: English → Spanish pairs, managed on an admin page built like Manage Glossary. A
  string with no Spanish shows English — nothing ever renders blank.
- JS needs its own catalog (text built in ~50 JS files).
- Data stays as it is: gene names, annotations, descriptions are not translated.
- Size (2026-11-03 estimate, public pages only): ~3,500 text runs in 69 templates (~1,700 of
  them in sysadmin help pages a teaching site may not show), ~680 strings in PHP arrays, plus
  the JS. The Drupal translations cover the CONTENT; MOOP's own buttons/messages need Spanish
  written (machine draft + human review is the usual route).

### 5b. Translation workflow: a spreadsheet, agents, then human review (Sofia, 2026-11-03)
1. Wrap interface text in t() page by page (navbar + home first, BLAST help last). A script WRITES
   the spreadsheet from what is actually wrapped (never a hand-kept list): an .xlsx (CSV also
   accepted) with columns English | Spanish | Status | Where it appears | Notes. Status is
   `needs translation` / `machine draft` / `reviewed`. Spanish is PRE-FILLED from the current
   cuttingclass site where the English matches.
2. Another agent fills the gaps -> `machine draft`.
3. A human reviewer (Sofia's Spanish-speaking friends) corrects -> `reviewed`.
4. Upload on the catalog admin page. MOOP checks before saving: nothing lost, %d/%s placeholders
   match between English and Spanish; shows counts per status. Re-upload updates only changes.
- Visitors NEVER see "needs translation": untranslated text shows in English. An admin-only
  "show gaps" view highlights untranslated strings on the real pages.
- Catalog is site data (snapshot to the private repo), like pages and the glossary.
- Next session: t() + catalog + export/import with checks + show-gaps view + wrap navbar & home.
- The (i) popovers, help panels and glossary go through field_help() / help_modal() / gloss():
  t() goes INSIDE those once, not at each (i). Glossary definitions get their own Spanish column
  (Manage Glossary). Text with numbers built in becomes a template ("Showing %d of %d hits");
  the export flags those.
- Shrink the spreadsheet: record which strings THIS site actually displays and export only those,
  so text for switched-off tools never reaches the translators.
- Bigger picture: MOOP replaces old simrbase, then cuttingclass, then planosphere.stowers.org —
  keep everything generic and opt-in so planosphere reuses it.

### 6. Simple mode (maybe)
One setting that folds advanced options away (e.g. BLAST parameters) and uses plainer labels.

### 7. Home-page organism selector: taxonomy tabs optional
The home page's "1 · Select one or more organisms" card has three tabs: Organisms Select,
Taxon Select (lineage filter) and Tree Select (taxonomy tree). Right for simrbase (~85
organisms); too much for cuttingclass's four planarians (Sofia, 2026-11-03). A per-site
setting hides the Taxon and Tree tabs; absent → all three, as today.

---

## Where site content is saved

Custom pages AND the English/Spanish text catalog are **site data**, not app code (Sofia,
2026-11-03: "we need to save custom pages and the bilingual text"). They live beside the
glossary under the metadata tree, and housekeeping's `snapshot_site_data` copies them into
`site_data_path` — the private site-data git repo. When each is built:
- add its files to the snapshot list in `lib/housekeeping.php`;
- add the directory to the writable allowlist (`moop_permission_check_mode()`) and to
  `scripts/fix_moop_selinux.sh` (CLAUDE.md §11 — a missing entry fails silently).

## simrbase will use some of this too — by choice

Sofia wants simrbase's About menu to have **About MOOP** and, if she chooses, **About
SIMRbase**. That is content pages (step 2) + the menu builder (step 3), switched on
deliberately for simrbase — the opt-in rule working as intended, not a changed default.
The render comparison shows exactly what that changes on simrbase, and nothing else.

## The render comparison (step 0) — BUILT 2026-11-03

`scripts/render_compare.php` + `tests/render_pages.json` (52 pages: 21 each as PUBLIC from
127.0.0.1 and as IP_IN_RANGE from the host IP, 2 primer-tool POSTs, 8 as admin).

    php scripts/render_compare.php capture ~/render-baseline     # BEFORE a change
    php scripts/render_compare.php check   ~/render-baseline     # after; exit 0 = identical

Blanked by named rule only: CSRF tokens, asset `?v=` stamps, JWT track tokens, the random
header banner, the favicon `?t=` stamp, Site Configuration's clock. Two back-to-back captures
are identical on all 52; a one-character change to the About heading showed on exactly the 2
About pages and exited 1. Keep the baseline OUT of the repo (admin pages list users).
Not covered yet: a BLAST run (queue ticket), CSS (only the stylesheet's stamp is seen) and
JS-built content — use computed styles in a browser for those (CLAUDE.md §12b).

---

## Decided

- **Logins:** none except admin. Everything on cuttingclass is public.
- **Tools kept:** BLAST, gene search, Primer Maker.
- **Data:** the planarians on the current site ("four species"); some may have only
  transcript assemblies, some genomes — the tools must handle both (see primer checker below).

## Tracks server: two sites, one server

The tracks server verifies every track token against ONE public key
(`lib/jbrowse/track_token.php::verifyTrackToken()`), so a second site's tokens fail there.
No new port is needed:

- Each site signs with its own key and puts a `kid` (key id = site name) in the JWT header.
- The tracks server holds one public key per site and picks by `kid` — firebase/php-jwt
  supports a key array keyed by kid natively.
- Each key may be limited to a path prefix (e.g. the cuttingclass key only under
  `cuttingclass/`), so neither site can mint tokens for the other's restricted tracks — the
  weakness of simply sharing one key pair.
- **Opt-in:** a token with no `kid` is checked against the single existing key, exactly as
  today. Deploy = copy the two files to the tracks server once, as originally.
- Also: add the new origin to the tracks server's CORS allow-list. CSP follows
  `tracks_server.url` via install_monitor.sh.

## Open questions

- Content-page editor: plain text vs WYSIWYG.
- Which "start here" path does a student follow first?
- Primer BLAST on cuttingclass too, or Primer Maker only?
- Theme: colours/logo for cuttingclass — how far beyond Site Configuration's title/logo?
- MOOP's glossary popovers vs the "Glossary of Terms" page — merge?
- ~~Who reviews the Spanish for MOOP's interface strings?~~ Sofia's Spanish-speaking friends
  (2026-11-03). So: machine-draft each batch, reviewers correct it on the catalog admin page
  (English and Spanish side by side, no code), one batch at a time — navbar + home first.

## Related

- Install path for the new machine: README "A New Site on Another Machine"; the 2026-11-03
  test install (commit 177e5ac2) fixed site_data_path defaulting into the docroot and the
  umask-027 clone being unreadable.
- Memory: plan_i18n_cuttingclass, feedback_cuttingclass_opt_in_only.
