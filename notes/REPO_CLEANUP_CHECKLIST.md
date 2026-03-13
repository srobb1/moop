# Repo Cleanup: Separate App from Site Data — COMPLETE

Goal: The moop repo contains only files needed to set up a new site.
Site-specific data is versioned separately in the site-data repo.

---

## 1. Create site-data repo

- [x] Create site-data directory with `git init`
- [x] Add `.gitignore` (exclude `*.fa`, `*.fasta`, `*.fai`, `*.sqlite`, etc.)
- [x] Initial snapshot commit (config, metadata, users.json)
- [x] Set ownership to web server user for auto-snapshots
- [x] README auto-created on first housekeeping run

## 2. Add .example templates to app repo

- [x] `config/config_editable.json.example`
- [x] `metadata/annotation_config.json.example`
- [x] `metadata/group_descriptions.json.example`
- [x] `metadata/organism_assembly_groups.json.example`
- [x] `metadata/taxonomy_tree_config.json.example`

## 3. Update app repo .gitignore

- [x] Add `*.fa` / `*.fasta` / `*.fai` and genome data patterns
- [x] Add `logs/` (all log and runtime state files)
- [x] Add `config/config_editable.json` and `config/secrets.php`
- [x] Add `metadata/*.json` (keep .example and README)
- [x] Add `admin/backups/`, `api/archive/`
- [x] Add `vendor/`, `composer.phar`
- [x] Verified `*.sqlite` covered

## 4. Untrack site-specific files from app repo

- [x] `git rm --cached` config, metadata, logs, data/genomes, backups, archive, vendor
- [x] Verified files still exist on disk after untracking

## 5. Add snapshot as housekeeping task

- [x] `housekeeping_snapshot_site_data()` in `lib/housekeeping.php`
- [x] `site_data_path` in `config/site_config.php`
- [x] Admin dashboard setup prompt with correct user/group from `getWebServerUser()`

## 6. Documentation

- [x] CLAUDE.md updated with "Repo Structure: App vs. Site Data" section
- [x] Pending: commit all changes

---

## Decision Log

- **users.json**: Safe to snapshot. Passwords are bcrypt hashes (one-way).
  Access lists are valuable for restore. README warns to keep repo private.
- **login_attempts.json**: NOT snapshotted — ephemeral runtime state.
- **secrets.php**: Snapshotted if exists. Repo must stay private.
- **vendor/**: Excluded from app repo. Recreated via `composer install`.
- **JBrowse2 frontend** (`jbrowse2/`): Still tracked in app repo for now.
  Consider excluding in future (1,142 files, installable separately).
