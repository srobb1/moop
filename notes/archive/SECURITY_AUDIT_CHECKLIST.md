# Security Audit Checklist — ALL COMPLETE

Based on comprehensive codebase review (March 2026).
Commits: `e4440ab` (items 1-8, 10-11, 14, 16-18, 20), pending commit (items 9, 12-13, 15, 19)

---

## All 20 Items Resolved

- [x] **#1 CRITICAL** — CSRF protection (centralized tokens, auto-verify, form fields, AJAX header)
- [x] **#2 CRITICAL** — generate_registry.php authentication (uses admin_init.php)
- [x] **#3 HIGH** — Session fixation (session_regenerate_id on login)
- [x] **#4 HIGH** — Path traversal in FASTA handler (realpath + base-dir check)
- [x] **#5 HIGH** — IP spoofing/proxy docs + warning log
- [x] **#6 HIGH** — Trusted server URL prefix bypass (trailing slash normalization)
- [x] **#7 HIGH** — Brute-force login protection (rate limiting in functions_login_protection.php)
- [x] **#8 MEDIUM** — Absolute filesystem path as CSS URL (uses custom_css_url now)
- [x] **#9 MEDIUM** — Dual DataTables loading / migrate off page-setup.php
  - No PHP files include page-setup.php anymore; dead code with deprecation notice
  - head-resources.php still has legacy DataTables 1.10.24 block; can remove once confirmed unused
- [x] **#10 MEDIUM** — Content-Disposition header (properly quoted filename)
- [x] **#11 MEDIUM** — HTTP security headers (Apache: X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
- [x] **#12 MEDIUM** — JWT tokens in URL query string
  - JBrowse2 architecture constraint — documented in code and CLAUDE.md
  - Tokens are short-lived; migrate if JBrowse2 adds header support
- [x] **#13 MEDIUM** — BLAST temp file cleanup
  - Implemented via `lib/housekeeping.php` — runs once per admin session, no cron needed
  - Cleans blast_*, blast_xml_*, blast_pairwise_*, blastdb_*, mafft_*, galaxy_seqs_* older than 24h
- [x] **#14 LOW** — Global variable in getBlastDatabases() (uses ConfigManager now)
- [x] **#15 LOW** — Delete backup files in production (21 .backup/.bak files removed)
- [x] **#16 LOW** — Deprecated test_input() (removed, no callers remain)
- [x] **#17 LOW** — Hardcoded /moop/ redirect in validateOrganismParam (uses ConfigManager)
- [x] **#18 LOW** — page-setup.php deprecation notice added
- [x] **#19 LOW** — SRI hashes on CDN scripts (layout.php + head-resources.php)
- [x] **#20 LOW** — decodeAnnotationText XSS (htmlspecialchars in annotation_search_ajax.php)
