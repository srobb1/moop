# Scalability Improvements for Many Organisms (85+)

Identified March 2026 after deploying to a machine with 85 organisms.

## Completed

- [x] **manage_organisms.php** — Added fingerprint-based JSON cache + Rescan button. Page loads instantly from cache.
- [x] **CLI cache warming script** — `php scripts/warm_organism_cache.php` pre-builds organism cache and annotation config cache. Shows per-organism progress.
- [x] **manage_organisms.php DataTables** — Added filter/sort/pagination to organisms table.
- [x] **manage_groups.php DataTables** — Replaced broken `sortTable()` onclick handlers with DataTables initialization on both group tables.
- [x] **manage_annotations.php cache warming** — Added annotation DB scanning to `warm_organism_cache.php`. The page already self-caches via `sqlite_mod_time` after first load; the CLI script ensures first load doesn't timeout.
- [x] **manage_taxonomy_tree.php metadata** — Reads organism metadata from organism cache file instead of scanning 85 organism.json files.
- [x] **getAccessibleAssemblies() session cache** — Results cached in `$_SESSION` with groups file mtime + access level invalidation. Tool pages (BLAST, retrieve sequences, etc.) no longer re-scan on every page load.
- [x] **Tool page dropdowns** — Already had built-in text filter via `source-list.php` + `source-list-manager.js`. No change needed.

## Remaining

- [ ] **index.php (homepage)** — Loads full taxonomy tree JSON and computes user access on every visit. Tree file grows with organism count. Could cache computed tree access in session per user.
  - File: `index.php` (lines 36-37)
  - Severity: Medium — not slow yet but grows linearly

- [ ] **JBrowse2 config generation** — `glob()` on all assembly JSON files per JBrowse2 page load. Relatively fast but grows linearly.
  - File: `api/jbrowse2/config.php` (line 138)
  - Severity: Low
