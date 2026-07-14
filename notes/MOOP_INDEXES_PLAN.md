# Writable index/ subdir — protect FASTAs, keep the web build buttons working

**Status:** planned 2026-07-14, not started. Execute in a fresh session — touches BLAST
search correctness; a wrong `-db` path returns wrong/no hits silently.

## Goal (MOOP's north star: easy to manage, automatic)

Web-triggered index building (the admin "Build BLAST Index" button, assembly registration's
`samtools faidx`) must keep working, **and** the source FASTA/GFF/DB files must stay read-only
to the web server. The admin must never have to think about it — no pre-building off-box, no
manual unlock. This is why the file-permission manager exists: so the web buttons Just Work.

## The constraint that drives the design

You cannot make *only the index files* writable while the FASTA beside them is read-only, if
they share a directory: creating a new file (`makeblastdb` writes `protein.aa.fa.phr`, which
did not exist) needs **write on the directory** (SELinux `add_name` on the dir type), not on
the file. A read-only directory blocks creating *any* file in it. So the indexes MUST live in
a **separate writable directory** from the FASTA. There is no SELinux-only shortcut.

## Design: a writable `index/` subdir per unit, with FASTA symlinks

- Build indexes into a subdir the app owns, e.g. `organisms/{org}/{asm}/{gs}/index/`
  (gene-set BLAST dbs: protein/transcript/cds) and `organisms/{org}/{asm}/index/`
  (assembly: genome.fa BLAST db + `.fai`). Confirm the exact levels during build.
- That subdir is the ONLY writable thing under `organisms/` (besides `organism.json`), via a
  narrow persistent SELinux rule, e.g.
  `semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/moop/organisms/(.*/)?index(/.*)?"`
  (pin the exact regex to the chosen levels). FASTA/GFF/DB stay `httpd_sys_content_t`.
- **Symlink the FASTA into the `index/` subdir**, so the subdir looks like a complete,
  blast-able assembly dir. Existing code that assumes "FASTA and its indexes share a folder"
  then works by just pointing it at the subdir — shrinking the change from "thread a separate
  index path everywhere" to "compute the subdir path + drop a FASTA symlink." Precedent already
  in the codebase: `gene_set_functions.php` self-heals the `annotations.gff3` symlink.
- Fully automatic: the app auto-creates the subdir + symlinks (like it does cache dirs and the
  annotations.gff3 symlink), `scripts/fix_moop_selinux.sh` sets the label, housekeeping keeps it
  healthy. The admin never touches it.

## What changes (all in `lib/blast_functions.php` unless noted)

- `getBlastDatabases()` (:23), `-db` construction (:176–183), `.phr/.nhr/.pdb` existence checks
  (:136, :350), `validateBlastIndexFiles()` (:625; used by organism_cache.php:242 for the
  checklist) → resolve to the `index/` subdir.
- `makeblastdb` (:853) `-out` into the subdir; keep `-in` reading the read-only FASTA.
- `.fai`: MOOP's own reader (:939, fseek) reads from the subdir; `samtools faidx` (assembly
  registration, jbrowse_register_assembly.php:97) writes there.
- `organism_checklist.php:395` — fix the `is_writable()` false-green: test the writable `index/`
  subdir (which IS web-writable), so "can build" is truthful.
- New helper `lib/index_paths.php` (mirror cache_paths.php): compute the subdir, ensure it
  exists, create/repoint the FASTA symlink (self-healing).
- Migration: move existing `*.phr/.pin/.psq/.nhr/.nin/.nsq/.ndb/.pdb/.p*/.n*` and `*.fai` from
  the assembly/gene-set dirs into the `index/` subdirs; create the FASTA symlinks.
- SELinux: add the narrow `index/` rule to `fix_moop_selinux.sh`; keep organisms/ otherwise
  read-only + the `organism.json` rule.

NOT affected: JBrowse `.fai` (AutoTrack.php, on `data/genomes`, already writable, browser-fetched)
and GFF bgzip+tabix (writes `data/genomes`).

## Verify hard

- Same BLAST search before/after → identical hits (protein + nucleotide, and the blastdbcmd
  "fetch sequence by id" path at :495/:535).
- A **web-triggered** "Build BLAST Index" succeeds against otherwise-read-only organisms/.
- Symlink self-heals on assembly rename/reload.

## Fallbacks (if this is ever deprioritized)

- On-demand builds already work from the CLI on-box (`smr` is unconfined) — no change needed for that.
- `organisms/` fully writable + an nginx no-exec rule (`location ^~ /moop/organisms/ {return 404;}`,
  `location ~ ^/moop/data/.*\.php$ {return 404;}`) closes the webshell risk without protecting
  FASTA integrity. Simplest, less protection. User found this less appealing than keeping FASTAs
  genuinely read-only.

Related: [[project_cache_path_and_readonly_organisms]] (the cache move + read-only organisms/
this builds on), audit §O.
