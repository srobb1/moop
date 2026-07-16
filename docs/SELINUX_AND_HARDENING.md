# SELinux, Firewall, and Host Hardening

Operational reference for running MOOP on a hardened RHEL host. Written after the
2026-07-13 incident; read this before debugging "the site suddenly can't write" or
"the site is unreachable after a reboot/patch window."

---

## What happened on 2026-07-13

An automated security/patch run was applied to the host (`simrbasenew`) between roughly
15:30 and 15:55. From `dnf history`:

- a ~346-package update
- installation of **`openscap-scanner`** and **`scap-security-guide`**
- application of a SCAP/OpenSCAP hardening baseline
- a mass `systemd` service restart

Three things broke immediately, all of them expected consequences of the baseline:

1. **Firewall.** `firewalld` was reset to a hardened default — `firewall-cmd --list-all`
   showed only `ssh`, `cockpit`, `dhcpv6-client`. **`http`/`https` were gone**, so the
   site was unreachable.
2. **Web server.** nginx was stopped by the mass restart and did not come back, because
   **nginx on this host has no systemd unit** and therefore nothing restarts it.
3. **SELinux.** Set to **Enforcing**, and the document root was relabelled by
   `restorecon` to the policy default `httpd_sys_content_t` (**read-only**). Every
   directory MOOP writes into became unwritable by php-fpm (running as `apache`).

### Why the hardening is necessary — and why it will happen again

This is not a misconfiguration to be undone. Newly built VMs at this site are required
to be hardened, and **the hardening run is expected to recur.** Do not "fix" MOOP by
setting SELinux to Permissive or by disabling the baseline; it will simply be re-applied,
and the site will break again in exactly the same way.

The correct posture is to make MOOP's requirements *part of the policy*, so that a future
hardening run **re-applies** them instead of destroying them. That is what the rules below do.

---

## Why MOOP is affected at all

`httpd_sys_content_t` — the read-only label — is the SELinux **default for a web document
root**, and it is a sensible one. The built-in assumption is that a web application does
**not write into the directory it serves from**: content is read-only, and anything the app
generates lives elsewhere.

MOOP violates that assumption. It writes caches, regenerated genome indexes, and its own
configuration *inside its own document root*. That is legitimate but non-default, so each
writable directory needs an explicit exception.

---

## The rules MOOP requires

Every directory below contains files created by the `apache` user (verified empirically with
`find /var/www/html/moop -user apache`). Each needs the read-write type
**`httpd_sys_rw_content_t`**, applied **recursively**:

| Path | Why |
|------|-----|
| `/var/www/html/moop/logs` | `error.log`, `login_attempts.json` |
| `/var/www/html/moop/config` | `config_editable.json` (written by the admin UI) |
| `/var/www/html/moop/metadata` | JBrowse track/assembly configs, groups, taxonomy |
| `/var/www/html/moop/data/genomes` | `annotations.gff3.gz` + tabix indexes, regenerated on re-prep |
| `/var/www/html/moop/images` | the **whole tree** is web-written: organism images upload to the top level (edit-organism modal → `handle_image_upload.php`), `banners/` via Site Configuration, `wikimedia/` + `ncbi_taxonomy/` are remote-image caches php downloads into |
| `/var/www/html/moop/archived_gene_sets` | gene-set archive output |
| `/var/www/moop-site-data` | site-data backup (config, secrets, users.json) |
| `/var/www/moop-cache` | generated caches — see the `cache_path` section below |
| `/var/www/html/moop/organisms` | organism data + the BLAST/`.fai` indexes the web builds in-tree — see below |

### organisms/ is writable — and why that is safe (settled 2026-07-16)

`organisms/` is the largest and most valuable tree (genomes, SQLite databases, FASTA,
BLAST indexes), so its presence in the writable table above deserves an explanation.
**This is the intended end state, not a pending TODO.**

The obvious instinct is to make this tree read-only to the web server, and we tried
exactly that in July 2026. It failed on contact with reality, in two ways:

1. It **blocked the app's own index building** — the admin "Build BLAST Index" button and
   the `samtools faidx` step of assembly registration both write next to the FASTA they
   index. `makeblastdb` creates *new* files, which needs write on the **directory**, not
   just the file, so no file-level exception can rescue it.
2. It made SQLite log a `{ write }` denial on **every database open** — PDO opens
   read-write by default and falls back to read-only, so the site "worked" while emitting
   ~740 AVC denials per minute. (The opens are now genuinely read-only — see below — but
   that alone did not make read-only worth it.)

**The webshell risk is closed at the execution layer instead, which is where it belongs.**
`docs/nginx/moop-security.conf` (deployed to `/etc/nginx/default.d/`) denies HTTP to
`/moop/organisms/` wholesale and denies `.php` under `/moop/data/`. A `.php` file written
anywhere in the writable data trees therefore **cannot execute** — verified by direct test
on 2026-07-16: a real mode-644 `.php` placed in `data/genomes/` returned 404 and did not
run, while the identical file in the document root executed normally. That control matters:
it proves the nginx rule is what blocks execution, not the incidental fact that the tree's
files are mode 660 and unreadable to the nginx user. Do not rely on that accident.

Making the tree read-only would add only **data-tampering** protection, and only against an
attacker who already has app-level arbitrary file write — and it would not even cover the
realistic corruption vector, since a SELinux rule on `httpd_t` does nothing about a CLI
loader or pipeline bug running as an unconfined user. The plan to restructure indexes into
a writable `index/` subdir so the tree could be tightened again was evaluated and
**declined**; see `notes/MOOP_INDEXES_PLAN.md` for the full rationale and the specific
conditions that would justify revisiting it.

**Related, and worth keeping:** the app opens every SQLite database **read-only**
(`PDO::SQLITE_OPEN_READONLY`), because the web workload is queries only. That is correct on
its own merits regardless of the tree's label, and it is what ended the AVC flood.

Two things that used to be true here and are **no longer**:

- The narrow `organisms/[^/]+/organism\.json` read-write rule is **retired**;
  `fix_moop_selinux.sh` removes it. The whole tree is writable, so it is redundant.
- The "Generate organism JSON needs a temporary `chcon`" caveat is **moot** for the same
  reason.

### jbrowse2/ stays read-only — do not add it to the table

`jbrowse2/` is the browser application's own JS/CSS/HTML. The web server only reads it, and
it must stay that way: **every user's browser executes that JavaScript**, so a write bug
there would mean injected JS on every page. The nginx no-exec rules do **not** help — they
deny `.php`, not `.js`. This is the one tree where the filesystem layer is the real defense,
which is the opposite of the `organisms/` case above.

It briefly looked like it needed write, because `admin/api/jbrowse_text_index.php` creates
`jbrowse2/{organism}/{assembly}/trix/` at runtime. Resolved 2026-07-16 by checking what was
actually there: the only trix files on the box were two orphaned sets from April, and **no
track config referenced them** — a leftover from working out dynamic config generation, not
a live feature. They were deleted, and `setup.php` no longer creates a `jbrowse2/trix`
directory (it used to `mkdir` it `02775` on every fresh install — writable space carved into
the app's code tree that nothing used).

**Consequence:** the admin "text index" button is dormant. Nothing depends on it — no track
uses `TrixTextSearchAdapter`. If JBrowse name-search is wanted later, put the trix output
somewhere already writable and browser-fetchable (`data/genomes/` is the natural home) rather
than making the app tree writable.

### The cache directory (`cache_path`)

The app writes regenerable caches (organism scan, annotation counts, chromosome-name
lists, annotated feature types) to the directory named by the `cache_path` config value
(Admin → Site Configuration). Point it **outside the document root** — the shipped
default on this deployment is `/var/www/moop-cache`. This keeps `organisms/` to
shipped-in data only, which is a clean separation worth having on its own: regenerable
caches do not belong in the data tree, whatever that tree's SELinux label is.
It needs `apache:apache`, mode `2775` (SGID, so both php-fpm and CLI scripts can write),
and a persistent `httpd_sys_rw_content_t` rule. `scripts/fix_moop_selinux.sh` creates and
labels it. Leaving `cache_path` empty is also valid — caches then fall back into the
`organisms/` tree (legacy behaviour).

Plus one boolean:

```
setsebool -P httpd_can_network_connect on
```

php-fpm makes **outbound** connections (it fetches the JBrowse track sheet from Google
Sheets). With this boolean off, SELinux blocks *every* outbound connection from the web
server, and the sync fails with errors that look nothing like a permissions problem.

### Two traps to avoid

**1. Use `semanage`, not `chcon`.** `chcon` sets a label *now*; the next `restorecon` —
which every hardening run performs — resets it to the policy default and the site breaks
again. `semanage fcontext` writes a **persistent policy rule**, so future hardening runs
re-apply your labels. This is the difference between a fix that holds and one that lasts
until the next patch window.

**2. `semanage fcontext` takes a REGEX — remember the `(/.*)?` suffix.** A bare path matches
only that directory's own inode, **not its contents**. On 2026-07-13 the initial rules were
added without the suffix, so the top-level directories were read-write while every
subdirectory beneath them stayed read-only — which looked, confusingly, like a partial fix.

```bash
# right — directory AND everything inside it
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/moop/metadata(/.*)?"

# wrong — the directory inode only
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/moop/metadata"
```

---

## `scripts/fix_moop_selinux.sh`

Applies everything above in one idempotent, root-only pass: creates and labels the cache
directory, adds the recursive read-write rules (including `organisms/`), removes the retired
narrow `organism.json` rule, relabels, sets the `httpd_can_network_connect` boolean, and
restores `apache` ownership under the JBrowse track configs. **You run it yourself with `sudo`** —
no IT round-trip is needed for SELinux rules or labels; only the central hardening *job*
(which profile, when it runs) is IT's.

The script is the canonical setup and is kept for:

- **standing up a new MOOP deployment** on a hardened host,
- **recovery** after a hardening run, a rebuilt host, or a wiped policy store,
- **documentation** — it is the executable form of this document.

Check before assuming you need it:

```bash
getsebool httpd_can_network_connect                    # expect: on
ls -ldZ /var/www/moop-cache                            # expect: httpd_sys_rw_content_t
ls -ldZ /var/www/html/moop/organisms                   # expect: httpd_sys_rw_content_t (writable — see above)
```

### The nginx rules are not optional

The script does **not** deploy them, and the writable data trees are only safe because they
exist. On a new deployment or a rebuilt host, copy `docs/nginx/moop-security.conf` to
`/etc/nginx/default.d/moop-security.conf`, then `nginx -t && systemctl reload nginx`.

Verify it is actually in force — do not just check that the file exists, and do not trust a
404 on a path that does not exist (`organisms/` also 404s for an unrelated reason):

```bash
# The real test: a file that WOULD execute, in a place it must not.
echo '<?php echo "EXECTEST"; ?>' > /var/www/html/moop/data/genomes/_exectest.php
chmod 644 /var/www/html/moop/data/genomes/_exectest.php
curl -s -o /dev/null -w '%{http_code}\n' http://<host>/moop/data/genomes/_exectest.php  # expect: 404
rm -f /var/www/html/moop/data/genomes/_exectest.php
```

**Gotcha:** the deployed `/etc/nginx/default.d/moop-security.conf` is mode `640 root:root`, so
it greps as *absent* from an unprivileged shell — the config looks missing when it is fine.
nginx reads its config as root. Check with `sudo`.

---

## Resolved

- **nginx now has a systemd unit** (2026-07-14). Installed the vendor `nginx` package (its
  unit was missing — only `nginx-core` was present), enabled it, and added a
  `Restart=on-failure` drop-in. nginx is now supervised, confined (`httpd_t`, was
  `unconfined_t`), and comes back after reboots and mass service restarts. The stale TLS key
  label (`user_tmp_t` → `cert_t`) was fixed first so confined nginx could read it.
- **Caches moved out of the data tree** (2026-07-14). The regenerable caches now live in
  `cache_path` (`/var/www/moop-cache`) instead of being scattered through `organisms/`, so
  that tree holds shipped-in data only. Worth keeping for the separation alone.
- **Webshell risk closed at the execution layer** (2026-07-14, re-verified 2026-07-16). The
  attempt to make `organisms/` read-only was reverted — it blocked the app's own index
  building and flooded the audit log. The nginx no-exec rules on the data trees close the
  serious threat (persistent RCE) without breaking anything; see the `organisms/` section
  above. The follow-on plan to restructure indexes so the tree could be tightened again was
  evaluated and declined (`notes/MOOP_INDEXES_PLAN.md`).
- **SQLite opens are read-only** (2026-07-15). `PDO::SQLITE_OPEN_READONLY` on every file
  database; the web workload is queries only. Ended the ~740 AVC denials/minute.
