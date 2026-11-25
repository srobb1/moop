# MOOP — system overview

**Last updated:** 2026-11-25. A short map, kept short on purpose: the detail lives in the docs
it points to, so this page has little to go stale. The 2,658-line version it replaced (January
2025) is `docs/archive/SYSTEM_OVERVIEW_2025.md`.

## What it is

MOOP (Multi-Organism Omics Platform) is a PHP web application for genome assemblies, gene sets,
genes and their annotations across many organisms, with a genome browser (JBrowse 2), BLAST,
primer design and checking, expression and image data, and admin-written content pages. One code
base runs several sites on this host (SIMRbase, Cutting Class, Planosphere), each with its own
data, settings and look.

## The pieces

| Piece | Where | Read |
|---|---|---|
| Pages: controller + template | `tools/*.php` -> `tools/pages/*.php`, `admin/*.php` -> `admin/pages/*.php`, drawn by `render_display_page()` (`includes/layout.php`) | `CLAUDE.md` section 2 |
| Settings | `config/site_config.php` (defaults) + `config_editable.json` (Admin -> Site Configuration), read through `ConfigManager` | `current/admin/CONFIG_GUIDE.md` |
| Access | public / inside the institute (by address) / collaborator / admin; gene-set visibility in `metadata/organism_assembly_groups.json` | `current/admin/PERMISSIONS_GUIDE.md`, `current/admin/SECURITY_GUIDE.md` |
| Organism data | `organisms/<organism>/<assembly>/<gene set>/` + one read-only SQLite database per organism | `ORGANISM_SIDE_FILES.md`, `CROSSWALK_FORMAT.md`, `CLADE_HOMOLOGS_FORMAT.md` |
| The database load | the pipeline in `config/build_and_load_db/` (compute side; the web never writes the databases) | that folder's README |
| Genome browser | JBrowse 2 (read-only app in `jbrowse2/`), track configs built per assembly, files on the tracks server behind signed tokens | `JBrowse2/README.md` |
| BLAST and primers | queued BLAST (`lib/blast_queue.php`), Primer Maker (Primer3), Primer BLAST | `current/features/BLAST_FLOW_CHART.md`, `BLAST_LINKOUTS.md` |
| Ontologies | PLANA, BSPO, ECO, GO built into cache databases | `ONTOLOGIES.md` |
| Caches, housekeeping | `cache_path` outside the document root; hourly maintenance from admin page loads | `CLAUDE.md` section 10 |
| API for programs and AI assistants | `api/v1/`, `llms.php` (served as `/llms.txt`) | Help -> AI assistants |
| Translation | `t()` for visitor text; Cutting Class runs in English and Spanish | `CLAUDE.md` section 7b |

## Setting up, securing, upgrading

- New host or site: `INSTALL.md`, then `php setup-check.php` (it checks nearly everything below).
- Hardening (SELinux, permissions, the web-server guard): `SELINUX_AND_HARDENING.md`.
- PHP versions: `SETUP/PHP_VERSION_SAFETY.md`. JBrowse upgrades: `JBrowse2/UPGRADING.md`.
- Updating every site after a push: `scripts/update_sites.sh`.

## Principles

The working rules -- prepared statements, `escapeshellarg()`, CSRF on every form, no inline
JavaScript, palette variables instead of colour literals, visitor text through `t()`, tests
before every commit -- are in `CLAUDE.md`. The original goals list (clear code, easy to
maintain, secure, clean CSS, no duplicated code) is `docs/archive/GOALS_2025.md`.
