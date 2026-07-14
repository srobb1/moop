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

## Two layers — BOTH are part of the design (not either/or)

The writable `index/` subdir sits UNDER the docroot, so on its own it re-creates the webshell
condition (writable + web-served → a written `.php` gets executed). The nginx no-exec rule is
what makes the writable subdir safe. Ship both:

- **Execution layer (nginx, in moop-security.conf):** nothing legitimately fetches `organisms/`
  over HTTP (verified — clients use `data/genomes`; downloads stream via PHP), so deny it wholesale;
  and block `.php` under `data/`:
  ```nginx
  location ^~ /moop/organisms/     { return 404; }   # covers the writable index/ subdir too
  location ~ ^/moop/data/.*\.php$  { return 404; }   # data/genomes serves data, never code
  ```
  A webshell written anywhere in the data trees can then never execute.
  NOTE: `data/genomes` is writable + under the docroot RIGHT NOW, so that second rule closes a
  *current* hole independent of this whole plan — worth doing early and cheaply.
- **Integrity layer (SELinux):** FASTA/GFF/DB stay read-only; only `index/` is writable, via the
  narrow `semanage` rule. Source data can't be corrupted.

Neither alone suffices: nginx-only leaves data corruptible; SELinux-only leaves the writable
subdir executable.

## Verify hard

- Same BLAST search before/after → identical hits (protein + nucleotide, and the blastdbcmd
  "fetch sequence by id" path at :495/:535).
- A **web-triggered** "Build BLAST Index" succeeds against otherwise-read-only organisms/.
- Symlink self-heals on assembly rename/reload.

## Rollout (two phases)

**Phase 1 — now, cheap (~15 min, nginx + SELinux, no code):** restore function + close the
serious hole.
1. Add the two nginx no-exec rules to moop-security.conf; `nginx -t`; reload. Closes the
   webshell risk everywhere in the data trees (incl. the current `data/genomes` one).
2. Revert `organisms/` to writable (drop the narrow rule, re-add the recursive rw rule,
   restorecon) so the web "Build BLAST Index" / register-assembly buttons work again.
   Result: buttons work, webshell closed. FASTA *integrity* not yet protected — acceptable,
   because the serious threat (persistent RCE) is closed and FASTAs are rebuildable.

**Phase 2 — fresh focused session (the refactor above):** add FASTA integrity.
- `index/` subdir + symlink + narrow SELinux rule + the blast_functions.php/checklist changes +
  migration, verified against identical BLAST hits. Tightens FASTA/GFF/DB back to read-only while
  the web buttons keep working.

**Interim note:** on-demand builds also work from the CLI on-box today (`smr` is unconfined), so
nothing is truly blocked even before Phase 1.

Related: [[project_cache_path_and_readonly_organisms]] (the cache move + read-only organisms/
this builds on), audit §O.
