# Retire an organism, assembly or gene set — plan

Status: **BUILT, all three levels.** Organism 2026-10-29 (1855c8a1, a7601e23); assembly and gene
set 2026-10-30. Prompted by retiring *Schmidtea nova*: removing an organism by hand took six
steps across four admin pages plus two shell commands, and nothing said what was left behind.

## As built (2026-10-30) — what differs from the plan below

- One code path for all levels: `moop_retire($level, …)` / `moop_restore()` in `lib/retire.php`,
  `admin/api/retire.php`, `js/modules/retire.js` (renamed from `*_organism`). Bundles are named
  `<ts>_<Org>[__<Asm>[__<GS>]]`.
- Buttons: organism on the Manage Organisms row; **assembly** in the assembly dialog's footer
  (and for leftover directories under "Retire a directory"); **gene set** on each gene-set card in
  that dialog. The last assembly / last gene set is refused — retire the level above instead.
- Gene set: its built JBrowse track (`data/genomes/{Org}/{Asm}/{GS}/`, incl. trix) and track JSON
  MOVE into the bundle, and its `primaryGeneTracks` entry is recorded and removed — so a restore
  needs no rebuild. Assembly: tracks/sheets move, the registration is removed (re-register).
- **Hidden while the DB still has it** because every scope is built from group entries plus
  directories — and the search endpoint now is too: `moop_search_permitted_scope()` /
  `moop_search_narrow_scope()` (`lib/functions_access.php`). Before, `annotation_search_ajax.php`
  authorised the ORGANISM and searched whatever pairs the browser sent, or the whole DB when it
  sent none (the default) — so a collaborator with one assembly could search all of them.
  Still unfiltered (fast path) whenever everything in the DB is open: all 90 organisms for admin.
- Health: `validateAssemblyDirectories()` returns retired ones in a separate `retired` list, not
  `mismatches` (`moop_retired_paths()` in `lib/functions_filesystem.php`). The Retired card says
  "waiting for a database rebuild without it" while `organism.sqlite` still holds it.
- **Replaced** `archiveGeneSet()` (Manage Groups → orphaned gene set → Archive now retires) and
  the assembly dialog's **Delete Directory / Delete Assembly Directory** buttons (`rrmdir` of a
  whole assembly from the web, no undo). `archiveGeneSet()` wrote to `archived_gene_sets/` in the
  docroot with no nginx deny: its 7 restricted gene sets were downloadable over HTTP until
  2026-10-30 (renamed `.archived_gene_sets`; `scripts/adopt_archived_gene_sets.php` turns them
  into bundles — run as apache).
- Not done: Data Health dashboard card for retired items (the Retired card covers it); a
  hermetic temp-tree test of retire/restore (verified end to end through php-fpm instead).

## Goal

One **Retire** button that takes an organism, an assembly or a gene set off the site and cleans up
everything MOOP created for it — and can be undone. **Nothing is deleted by the web**: data is
moved aside; final deletion stays a manual command the page shows.

## Facts this rests on (checked 2026-10-29)

- `organisms/` is on its own volume (`/dev/mapper/datavg-datalv`, mounted at `/var/www/html`);
  `/var/www/*` and `/home` are on other volumes. Only a move **within that volume** is instant
  (rename). Moving gigabytes elsewhere is a copy — not something a button should do.
- nginx denies `/moop/organisms/` wholesale (`docs/nginx/moop-security.conf`), `organisms/` is
  `httpd_sys_rw_content_t` and `smr:apache 2775`. So **`organisms/.retired/`** is instant,
  unreachable over HTTP, and writable by the web server with no new setup.
- What already exists and can be reused:
  - `unregisterAssembly()` (`lib/jbrowse/gene_set_functions.php`) — removes the assembly JSON,
    `data/genomes/{org}/{asm}/` (incl. trix), the sheet source; **keeps** track JSONs on purpose.
    Its API endpoint only accepts orphans (`getOrphanedJBrowseRegistrations()`), so retire calls
    the function directly, after its own checks.
  - Manage Groups' stale-entry removal (`organism_assembly_groups.json`).
  - Manage Annotations drops organisms that are gone on its next load
    (`update_annotation_config_modular()`, `lib/functions_json.php:436`).
  - Organism cache refresh (Manage Organisms → Refresh Cache).
  - Per-organism cache dir `{cache_path}/{Organism}/` (`lib/cache_paths.php`).

## Step 0 — one shared "list the organisms" function (do first)

Code that lists `organisms/` itself disagrees about hidden directories. Skips them:
`functions_data.php:566, :627`, `organism_cache.php:347`. Does **not**:
`organism_checklist.php:79`, `functions_filesystem.php:745`, `housekeeping.php:1153`,
`permission_check.php:1344`, possibly `functions_data.php:748` — these would report `.retired`
as a broken organism. Add `moop_organism_dirs(string $organism_data): array` (directories only,
no dot-entries, sorted) and use it everywhere. Test: a temp tree with `.retired/` and a file.
(This is the duplicated-logic pattern CLAUDE.md §9b asks to flag; worth doing on its own.)

## What Retire does

| Level | Files moved to `organisms/.retired/<YYYYMMDD-HHMM>_<name>/` | Also |
|---|---|---|
| Organism | the whole `organisms/{Org}/` (its DB lives inside — clean) | every assembly: unregister JBrowse, move track JSONs + sheets into the bundle; remove all its group entries; remove `{cache}/{Org}/`; refresh organism cache |
| Assembly | `organisms/{Org}/{Asm}/` | that assembly: same JBrowse/groups/cache steps |
| Gene set | `organisms/{Org}/{Asm}/{GS}/` | its group entry; its gene track JSON + trix |

Every retire writes `retired.json` into the bundle: who, when, level, the exact group entries
removed (incl. visibility), the track/sheet files moved, and what was not done and why.

**User access (`users.json`) is left alone**, so Restore brings access back. Accounts simply
see nothing there meanwhile (the stale-assembly tool on Manage Users still works if wanted).

### The catch for assembly / gene set: one database per organism

The organism's `organism.sqlite` still holds that assembly's / gene set's rows until the
**pipeline rebuilds without it**. Meanwhile:
- removing its group entries hides it from everyone but admins;
- Data Health would report "DB has a gene set with no directory" — so the health checks must
  read the retired records and show **"Retired YYYY-MM-DD — waiting for a database rebuild"**
  (info, not error) instead;
- `retired.json` says the compute side must leave it out (and copy2moop must not bring the
  directory back — tell the compute agent).

Organism level has none of this, so **build organism level first**.

## Restore

From `retired.json`: move the directory back, re-add the group entries exactly, move track
JSONs/sheets back, refresh the cache. Then the page says: press **Register** in Manage JBrowse
(registration rebuilds `data/genomes` and the trix index). Refuse if something with that name
now exists in `organisms/`.

## UI

- **Retire** on Manage Organisms rows (organism), and on assembly / gene set rows (where those
  are listed — Manage Organisms detail or the gene set page's admin bar).
- A confirm dialog that first lists **what will happen** (dry run from the same code), and asks
  the admin to type the name.
- A **Retired** section (Manage Organisms, or its own small page): each bundle, level, date,
  size, **Restore**, and the exact `rm -rf` command for permanent deletion.
- Progress messages step by step (the user likes step-level feedback).

## Safety rules

- Every path built from the organism cache / DB, never from the request (same rule as
  `api/gene_set_examples.php`); names checked against `[A-Za-z0-9_.-]`.
- `rename()` only — if source and target are not on the same volume, **stop** with a message;
  never fall back to copy+delete.
- Steps run in a fixed order, each recorded in `retired.json` as it completes, so a failure
  half-way leaves a record of exactly what was done and Restore can undo it.
- Admin + CSRF (`admin/api/*.php` via `admin_init.php`). Log to the Groups change log.
- `.retired/` added to `moop_permission_check_mode()` as writable and to
  `scripts/fix_moop_selinux.sh` (CLAUDE.md §11), even though it inherits today's label.

## Tests

`moop_organism_dirs()` skips dot-dirs; dry run lists the right steps per level; a temp-tree
retire + restore round-trip leaves the groups file and directories identical; refusal when the
target exists or the volumes differ.

## Docs to update when built

Manage Organisms guide (`admin/pages/guides/manage_organisms.php`), Setup Checklist guide,
CLAUDE.md (Common Admin Tasks), and a line in the compute-agent handoff about retired names.

## Meanwhile — the manual steps (used for *S. nova*)

1. `mkdir -p ~/retired && mv /var/www/html/moop/organisms/Schmidtea_nova ~/retired/`
2. Manage Organisms → Refresh Cache.
3. Dashboard → Data Health → **Unregister** (orphaned JBrowse registration); then
   `rm -r /var/www/html/moop/metadata/jbrowse2-configs/tracks/Schmidtea_nova`.
4. Manage Groups → Stale Entries → remove.
5. Open Manage Annotations (it drops the organism itself).
6. `rm -r /var/www/moop-cache/Schmidtea_nova`.
Not needed: users.json (no access granted), site-data backup (automatic), change log (history).
