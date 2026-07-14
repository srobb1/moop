# moop-indexes — relocate DB indexes to a writable dir outside the docroot

**Status:** planned 2026-07-14, not started. Execute in a fresh session — this touches BLAST
search correctness; a wrong `-db` path returns wrong/no hits silently.

## Goal

After the cache move + read-only `organisms/` ([[project_cache_path_and_readonly_organisms]]),
**web-triggered** index building (makeblastdb, samtools faidx) is blocked because php-fpm
(`httpd_t`) can't write the read-only tree. We do NOT want to depend on always building indexes
off-box ahead of time. Solution: build indexes into a **writable directory outside the
document root** (`/var/www/moop-indexes`, mirrors `moop-cache`), so:

- source data (FASTA, GFF, SQLite) stays read-only in `organisms/`,
- index building works from the web again (dir is `httpd_sys_rw_content_t`),
- indexes are outside the docroot → writable but NOT web-executable (no webshell surface —
  this was part of why read-only organisms/ mattered).

Indexes are regenerable derived data, like caches — same philosophy, different consumers.

## Why it's more than the cache move

Caches are read only by PHP (look anywhere). Indexes are consumed by external tools with
path expectations. What must change, all in `lib/blast_functions.php` unless noted:

- `getBlastDatabases($assembly_path)` (:23) — build db list from the moop-indexes mirror,
  not the assembly dir.
- `-db` path construction (:176–183) — point `blastp/blastn -db <prefix>` at moop-indexes.
- `.phr/.nhr/.pdb` existence checks (:136, :350) and `validateBlastIndexFiles()` (:625, used
  by organism_cache.php:242 for the checklist) — look in moop-indexes.
- `makeblastdb` build (:853) — `-out` into moop-indexes (keeps `-in` reading organisms/).
- MOOP's own `.fai` reader (:939, fseek sequence extraction) — read `genome.fa.fai` from
  moop-indexes. samtools faidx build → write there.
- `organism_checklist.php:395` — the `is_writable()` false-green (see audit §O): it checks
  DAC, which still passes; switch the "can build" test to the moop-indexes dir (which IS
  web-writable), so the checklist tells the truth.

NOT affected: JBrowse `.fai` (AutoTrack.php:75/176) — that's on `data/genomes` tracks
(already writable, browser fetches by URI). GFF bgzip+tabix — already writes `data/genomes`.

## Implementation sketch

1. New config `index_path` (default `''` = in-tree/legacy, like `cache_path`). Admin-editable,
   mirrors cache_path. `/var/www/moop-indexes` on this box.
2. `lib/index_paths.php` helper: `moop_index_dir_for($assembly_or_geneset_dir)` mirroring the
   organisms/ relative path into index_path (copy cache_paths.php's shape). Empty → falls back
   to the in-tree path (byte-identical legacy behaviour).
3. Route the blast_functions + checklist sites above through the helper.
4. Migrate existing indexes: move `*.phr/.pin/.psq/.nhr/.nin/.nsq/.ndb/.pdb/.p*/.n*` and
   `*.fai` from organisms/ into the mirror under moop-indexes.
5. SELinux: create `/var/www/moop-indexes` (apache:apache 2775) + persistent
   `httpd_sys_rw_content_t` rule; add to `scripts/fix_moop_selinux.sh`.
6. **Verify hard:** run the SAME BLAST search before and after and diff the hits — must be
   identical. Test protein + nucleotide, and the "fetch sequence by id" (blastdbcmd) path
   (:495, :535). Then a web-triggered "Build BLAST Index" must succeed against read-only
   organisms/.

## Meanwhile (nothing is broken in steady state)

Serving, browsing, and BLAST **search** all work today (search reads prebuilt indexes; results
go to a temp dir). Only in-app index BUILDING is deferred — build off-box or via a temporary
`chcon` until this lands.
