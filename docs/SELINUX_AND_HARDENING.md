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
| `/var/www/html/moop/organisms` | scattered caches: `chr_names_cache.json`, `annotated_feature_types.json`, `.organism_cache.json` |
| `/var/www/html/moop/images/wikimedia` | cached remote images |
| `/var/www/html/moop/images/ncbi_taxonomy` | cached remote images |
| `/var/www/html/moop/archived_gene_sets` | gene-set archive output |
| `/var/www/moop-site-data` | site-data backup (config, secrets, users.json) |

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

Applies everything above in one idempotent, root-only pass: removes a typo'd rule, adds all
nine recursive rules, relabels, sets the boolean, and restores `apache` ownership under the
JBrowse track configs.

**This script may well be unnecessary.** As of 2026-07-14, IT applied these rules directly
to the host, and because they are persistent policy they should survive future hardening
runs on their own. The script is kept for three reasons:

- **standing up a new MOOP deployment** on a hardened host,
- **recovery** if the local policy store is ever wiped or the host is rebuilt,
- **documentation** — it is the executable form of the table above.

Check before assuming you need it:

```bash
getsebool httpd_can_network_connect                                  # expect: on
matchpathcon /var/www/html/moop/metadata/jbrowse2-configs/tracks     # expect: httpd_sys_rw_content_t
```

---

## Still outstanding

- **nginx has no systemd unit.** It will not come back on its own after a reboot or a mass
  service restart. This is why the site stayed down on 2026-07-13 rather than
  self-recovering. Worth fixing — it is the single highest-value item here.
- **Reducing the writable surface.** MOOP currently needs nine exceptions because it caches
  and regenerates data inside its own document root. Consolidating the scattered caches into
  one directory would let `organisms/` — the largest tree, holding the SQLite databases and
  all genome data — go back to being fully read-only.
