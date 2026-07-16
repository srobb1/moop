# Writable index/ subdir — Phase 1 shipped, Phase 2 declined

**Status:**

- **Phase 1 — ✅ SHIPPED 2026-07-14** (commit `f5678e2`): nginx no-exec on the data trees +
  `organisms/` writable again. This is the layer that carries the security value, and it is live
  and verified.
- **Phase 2 — ❌ WON'T DO (decided 2026-07-16).** The cost/benefit does not justify it. Rationale
  and evidence below. The design is retained at the bottom in case the threat model changes.
- **SQLite read-only opens — ✅ SHIPPED 2026-07-15** (commit `ae5f69c`). Landed as a Phase 2
  prerequisite, but **stands on its own** — keep it. The web workload is queries only, so
  read-only opens are simply correct, and it ended the ~740 AVC/min `{ write }` audit flood.

**Do not re-open Phase 2 without new information.** See "What would change this decision".

---

## Why Phase 2 was declined

### 1. The serious threat is already closed — at the execution layer

Verified live 2026-07-16 by direct test, not by inference:

- Wrote a real `<?php echo 'MARKER'; ?>` file at mode 644 into the writable `data/genomes/`
  tree, requested it over HTTP → **404, did not execute**.
- Copied the identical file to the docroot, requested it → **200, printed the marker**.
- Same file, same permissions ⇒ it *was* readable and executable. The nginx deny rule is what
  stopped it — not the mode-660 "unreachable by accident" effect that `moop-security.conf`
  explicitly warns not to rely on. Both test files removed.
- Config confirmed deployed at `/etc/nginx/default.d/moop-security.conf` (dated Jul 14 15:34,
  matching `f5678e2`). Note: it is mode `640 root:root`, so it greps as *absent* from a non-root
  shell. That is a red herring — nginx reads its config as root. Don't re-diagnose this.

A webshell written anywhere in the writable data trees cannot execute. **Persistent RCE: closed.**

### 2. Phase 2's marginal benefit is narrower than the original framing suggested

The plan originally argued "neither layer alone suffices: nginx-only leaves data corruptible."
True in the letter, misleading in the weighting — the two layers are not symmetric:

- The **execution** layer (shipped) blocks persistent RCE.
- The **integrity** layer (Phase 2) blocks *data tampering by an attacker who already owns the
  app*. Reaching that state requires an app-level arbitrary-write vulnerability — i.e. an
  already-serious compromise. Phase 2 changes what such an attacker can do at the margin, and
  they still can't run code either way.

And the data is rebuildable from upstream sources + pipelines. That was true when this plan was
written; it's the reason Phase 1 was judged acceptable on its own.

**The decisive point:** Phase 2 only constrains the **web server** — `httpd_sys_content_t` denies
apache/php-fpm. It does nothing about the *realistic* corruption vector, which is a bug in a CLI
loader or pipeline running as `smr` (unconfined), which writes straight through SELinux regardless.
So the integrity layer protects a narrower slice than "FASTA integrity" implies.

### 3. The cost is high, permanent, and lands on the most silent-failure-prone code

- It touches BLAST search correctness, where the failure mode is **silent**: a wrong `-db` path
  returns wrong or zero hits with no error.
- It needs a migration across ~70+ organisms and their gene sets.
- It leaves a permanent structural oddity — FASTA in one directory, its indexes in a symlinked
  subdir beside it — that every future change to that code has to know about, in a codebase whose
  stated north star is "easy to manage, automatic."

The complexity is forever; the benefit is conditional on a compromise that the shipped layer
already defangs.

## What would change this decision

Re-open Phase 2 if any of these become true:

- `organisms/` stops being rebuildable — e.g. it becomes the system of record for something not
  derivable from upstream + pipelines.
- MOOP gets exposed to the public internet **and** an app-level arbitrary-write vulnerability
  class shows up in this codebase.
- An actual incident of data tampering occurs.
- Someone finds a way to get the same integrity guarantee **without** splitting FASTA from its
  indexes (that split is most of the permanent cost).

## Not a live bug

`organism_checklist.php:395` `is_writable()` was listed as a "false-green" to fix. It is only
wrong when `organisms/` is read-only. With `organisms/` writable (current state), `is_writable()`
returns true and "can build" is truthful. Nothing to fix unless Phase 2 is ever revived.

---

# Retained design (NOT implemented — reference only)

Kept so a future revival doesn't have to re-derive it. None of the below is built.

## The constraint that drove the design

You cannot make *only the index files* writable while the FASTA beside them is read-only, if they
share a directory: creating a new file (`makeblastdb` writes `protein.aa.fa.phr`, which did not
exist) needs **write on the directory** (SELinux `add_name` on the dir type), not on the file. A
read-only directory blocks creating *any* file in it. So the indexes MUST live in a **separate
writable directory** from the FASTA. There is no SELinux-only shortcut.

## Design: a writable `index/` subdir per unit, with FASTA symlinks

- Build indexes into a subdir the app owns, e.g. `organisms/{org}/{asm}/{gs}/index/` (gene-set
  BLAST dbs: protein/transcript/cds) and `organisms/{org}/{asm}/index/` (assembly: genome.fa BLAST
  db + `.fai`). Exact levels would need confirming during build.
- That subdir would be the ONLY writable thing under `organisms/`, via a narrow persistent SELinux
  rule, e.g.
  `semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/moop/organisms/(.*/)?index(/.*)?"`.
  FASTA/GFF/DB stay `httpd_sys_content_t`.
- **Symlink the FASTA into the `index/` subdir**, so the subdir looks like a complete, blast-able
  assembly dir. Existing code that assumes "FASTA and its indexes share a folder" then works by
  pointing it at the subdir. Precedent: `gene_set_functions.php` self-heals the `annotations.gff3`
  symlink.

## What would change (all in `lib/blast_functions.php` unless noted)

- `getBlastDatabases()` (:23), `-db` construction (:176–183), `.phr/.nhr/.pdb` existence checks
  (:136, :350), `validateBlastIndexFiles()` (:625; used by `organism_cache.php:242`) → resolve to
  the `index/` subdir.
- `makeblastdb` (:853) `-out` into the subdir; keep `-in` reading the read-only FASTA.
- `.fai`: MOOP's own reader (:939, fseek) reads from the subdir; `samtools faidx` (assembly
  registration, `jbrowse_register_assembly.php:97`) writes there.
- `organism_checklist.php:395` — test the writable `index/` subdir so "can build" is truthful.
- New helper `lib/index_paths.php` (mirroring `cache_paths.php`): compute the subdir, ensure it
  exists, create/repoint the FASTA symlink (self-healing).
- Migration: move existing `*.phr/.pin/.psq/.nhr/.nin/.nsq/.ndb/.pdb` and `*.fai` into the `index/`
  subdirs; create the FASTA symlinks.
- SELinux: add the narrow `index/` rule to `fix_moop_selinux.sh`.

NOT affected: JBrowse `.fai` (`AutoTrack.php`, on `data/genomes`, already writable) and GFF
bgzip+tabix (writes `data/genomes`).

## Verification that would be required

- Same BLAST search before/after → identical hits (protein + nucleotide, and the `blastdbcmd`
  fetch-by-id path at :495/:535).
- A **web-triggered** "Build BLAST Index" succeeds against otherwise-read-only `organisms/`.
- Symlink self-heals on assembly rename/reload.

---

Related: [[project_cache_path_and_readonly_organisms]] (the cache move + read-only organisms/ this
built on), [[project_permissions_alignment]], audit §O.
