# Unused-Functions Cleanup Plan (future)

Status: **not started — VERIFY BEFORE DELETING** (2026-07-10).

Source: `docs/function_registry.json` "unused functions" report (32 functions after the
2026-07-10 regeneration). The registry finds these by static scan, so it **cannot see
dynamic dispatch** — functions invoked via a `$tasks` array, `call_user_func`, variable
function names, or string references. Several entries below are near-certainly false
positives for exactly that reason. **Do not bulk-delete.** Verify each individually, then
remove in small, revertible commits (grouped by file). Recoverable from git if wrong.

Do this AFTER [PAGE_BY_PAGE_AUDIT_PLAN.md](PAGE_BY_PAGE_AUDIT_PLAN.md).

## Verification method (per function)

1. `grep -rn "\bfuncName\b" --include=*.php .` — direct callers (registry already did this).
2. Check **dynamic dispatch**: `grep -rn "funcName" .` as a bare string (array values,
   `call_user_func`, `is_callable`, hook/task registries).
3. Check it isn't a **public entry point** (an admin/tool endpoint's top-level handler,
   or a function called only from JS via an AJAX URL).
4. Only if all three are clean: delete the function (and its now-dead private helpers).
5. Run `php tests/smoke_tests.php` and regenerate the registry to confirm the count drops.

## Pre-triage of the 32 (from a quick first look — still verify)

### Almost-certainly FALSE POSITIVES — likely live via dynamic dispatch (verify, probably keep)
- `housekeeping_clean_temp_files` (housekeeping.php:95)
- `housekeeping_snapshot_site_data` (housekeeping.php:96)
- `housekeeping_environment_check` (housekeeping.php:97)
- `housekeeping_refresh_annotation_caches` (housekeeping.php:98)
- `housekeeping_refresh_organism_cache_if_stale` (housekeeping.php:99)
- `housekeeping_check_ncbi_taxonomy_update` (housekeeping.php:19)
  → CLAUDE.md documents housekeeping tasks are dispatched from a `$tasks` array in
    `run_housekeeping()`; the static scan misses array-of-callables dispatch. Expect these
    to be live. If any are genuinely not in the `$tasks` array, that's the real finding.

### Confirmed DEAD this session (safe to remove)
- `getAnnotationSources` (database_queries.php:607) — its only caller was
  `tools/get_annotation_sources.php`, deleted 2026-07-10 (audit finding #11). Remove.

### Needs verification (unknown — check each)
- `filterDatabasesByProgram` (blast_functions.php:81)
- `generateBlastGraphicalView` (blast_results_visualizer.php:361)
- `generateBlastStatisticsSummary` (blast_results_visualizer.php:710)
- `getColorStyle` (blast_results_visualizer.php:1510)
- `generateQueryScale` (blast_results_visualizer.php:1364)
- `getParentFeature` (database_queries.php:134)
- `getFeaturesByType` (database_queries.php:161)
- `searchFeaturesByUniquename` (database_queries.php:191)
- `getAnnotationsByFeature` (database_queries.php:223)
- `getOrganismInfo` (database_queries.php:244)
- `buildFilteredSourcesList` (extract_search_helpers.php:445)
- `assignGroupColors` (extract_search_helpers.php:495)
- `resolveSourceSelection` (functions_access.php:83)
- `formatIndexOrganismName` (functions_data.php:395)
- `getDetailedOrganismsInfo` (functions_data.php:589)
- `buildLikeConditions` (functions_database.php:283)
- `fetch_organism_image` (functions_display.php:353)
- `getNewestSqliteModTime` (functions_filesystem.php:690)
- `consolidateSynonym` (functions_json.php:256)
- `getAnnotationDisplayLabel` (functions_json.php:307)
- `shouldUpdateAnnotationCounts` (functions_json.php:345)
- `validate_search_term` (functions_validation.php:45)  ← validation helpers are often
  called indirectly; verify carefully
- `is_quoted_search` (functions_validation.php:74)
- `getAncestorsByFeatureId` (parent_functions.php:54)
- `build_tree_from_organisms` (taxonomy_functions.php:351)

## Note on the registry itself
The count also depends on the registry being current. It now regenerates cleanly (the
group-write permission bug on `docs/function_registry.json` was fixed 2026-07-10). Always
regenerate before trusting the count.
