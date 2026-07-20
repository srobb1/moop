# Error Log Viewer — Improvements Plan

Status: **item 9 DONE (2026-07-20, commit ddc293d); items 1–8 not started.** Based on a live
admin review of `admin/manage_error_log.php` + `admin/pages/error_log.php` +
`lib/functions_errorlog.php`.

## Why (assessment)

The viewer is a clean structured-log UI, but it's undercut by its data feed and lack of triage:

- **It only sees ~15% of the app's errors.** 12 `logError()` calls vs **69** plain `error_log()`
  calls; everything logged the plain way goes to php-fpm's log and never appears here. The real
  fatals aren't in it.
- **Signal drowns in noise.** 58 of 63 live entries are two cosmetic messages
  (`getOrganismImagePath received invalid organism_info` ×31, `Failed to download Wikimedia image`
  ×27), each rendered as a full-height card → a ~26,000px scroll wall. Serious ones
  (`Database not found`, `FASTA file not found`) are buried and styled identically.
- **No severity, no category, no grouping.** Every entry looks equally alarming.
- **Unbounded growth / whole-file read.** `logError()` appends forever; `getErrorLog()` `file()`-reads
  the entire log into memory each page load. No rotation.
- **Two polish bugs:** header says "Last 100 logged errors" but the controller fetches **500**
  (`getErrorLog(500)`); **Clear Log is a state-changing GET** (`?action=clear&confirm=1`) that a
  browser prefetch/link-follower could trigger.

## Design: severity + category (with PERMISSIONS pinned first)

**Severity:** `info | warning | error | critical` — color + sort by it.

**Category (user requirement):** first-class category field, and **`permissions` always sorts to the
very top, above everything, regardless of recency or severity.**
> Rationale (user): file-permission errors are the cheapest to fix but cause the biggest user-facing
> pain, so they must be the first thing an admin ever sees.

- Pin a distinct **loud banner** at the very top of the viewer whenever any `permissions` error
  exists ("N file-permission problems — these are usually a 2-minute fix"), with a **"Fix in
  Filesystem Permissions"** button linking to `admin/manage_filesystem_permissions.php`.
- **Auto-classify** so call sites don't each have to tag manually. Heuristic on message+details:
  `/permission denied|not writable|read-only|failed to open stream.*(write|permission)|chmod|is not writable|could not write/i`
  → category `permissions`, severity `critical`. (This also catches the plain `error_log()` sites
  once they're routed through `logError()` — see item 6.)
- Other categories fall out naturally: `database`, `filesystem`, `blast`, `search`, `auth`,
  `config`, `general` (default).

## Items

- [ ] **1. Extend `logError($msg, $context, $info, $severity='error', $category=null)`** — backward
      compatible (new optional args). If `$category` is null, run the auto-classifier on
      message+details. Persist `severity` + `category` in the JSON entry.
- [ ] **2. Auto-classifier helper** (`classifyError()`) with the permissions regex above; unit-test it
      in `tests/smoke_tests.php`.
- [ ] **3. Viewer: pin `permissions` at the top** with the loud banner + "Fix in Filesystem
      Permissions" button; then sort remaining by severity, then recency.
- [ ] **4. Group duplicates** by (category + normalized message): one row with a count and
      first/last-seen instead of N identical cards. Collapses the noise wall.
- [ ] **5. Severity styling + filter** — add a Severity dropdown alongside the existing
      type/organism/search filters; color the left border by severity (not always red).
- [ ] **6. Fix the data feed** — route the important `error_log()` write/permission/db call sites
      through `logError()` (start with the file-write ones, which are the permission-relevant ones),
      so they actually appear. Decide separately whether to also tail php-fpm's log.
- [ ] **7. Rotation + bounded read** — cap `logs/error.log` (rotate at ~1–2 MB, keep one `.1`
      backup); make `getErrorLog()` read only the tail rather than the whole file.
- [ ] **8. Dashboard data-health card** — bubble counts to the admin dashboard so an admin sees
      problems without visiting: "N errors (M critical)", with **permission problems called out
      first**. Ties into the pending dashboard-bubbling request (organism checklist + filesystem
      perms → data-health card).
- [x] **9. Quick polish fixes (do first): DONE 2026-07-20 (ddc293d).**
  - [x] header "Last 100 logged errors" → now reflects the real count ("Showing the N most
        recent logged errors", "No errors logged" when empty).
  - [x] Clear Log → POST form with `csrf_input_field()`; controller clears only on POST with
        `action=clear`. The CSRF-able GET was verified fixed live (old GET no-ops, tokenless
        POST 403s, valid POST clears).

## Suggested order

1. Item 9 (quick, safe, independent).
2. Items 1 + 2 + 5 (severity/category foundation + classifier + filter).
3. Items 3 + 8 (permissions pinning in the viewer AND on the dashboard — the user's top ask).
4. Item 4 (grouping).
5. Items 6 + 7 (feed + rotation — larger, scope each on its own).

Related: [PAGE_BY_PAGE_AUDIT_PLAN.md](PAGE_BY_PAGE_AUDIT_PLAN.md) (#13 registry perm bug is the same
"apache can't write a file" class the permissions category is meant to surface loudly).
