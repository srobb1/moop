# MOOP: Many Organisms One Platform

__M__ any · __O__ rganisms · __O__ ne · __P__ latform

*In Scottish English `moop` is a verb meaning to keep company or associate closely.*

A web platform for browsing genome assemblies, genes, and functional annotations across
many organisms at once. Each organism gets its own SQLite database, so adding one is quick
and touches nothing else. Searches can be scoped to a group of organisms, a single
organism, or one assembly. It integrates [JBrowse2](https://jbrowse.org/jb2/) as a genome
browser and Galaxy for workflows, with per-assembly access control so not everything has
to be public.

---

## Contents

- [Quick Start](#quick-start)
- [Requirements](#requirements)
- [After Installing](#after-installing)
- [Common Tasks](#common-tasks)
- [Genome Browser (JBrowse2)](#genome-browser-jbrowse2)
- [Running a Second MOOP on One Host](#running-a-second-moop-on-one-host)
- [Troubleshooting](#troubleshooting)
- [Going Deeper](#going-deeper)

---

## Quick Start

You need PHP and a web server already installed — see
[Requirements](#requirements) if you do not have them yet. Everything else the installer
handles.

**1. Clone it into your document root.**

```bash
git clone https://github.com/srobb1/moop.git /var/www/html/moop
cd /var/www/html/moop
```

The directory name is yours to choose — MOOP derives both its filesystem paths and its URL
prefix from where the code lives, so cloning to `/var/www/html/cuttingclass` gives you a
site at `/cuttingclass/` with no configuration at all. It does need to sit **inside the
document root** (`/var/www/html` here); anywhere else and the web server will not serve it
without a vhost of its own.

**2. Check what is missing.**

```bash
php setup-check.php
```

This names every unmet requirement and prints the exact command to fix it. Install what it
asks for ([details per distro](docs/INSTALL.md)) and re-run until it is happy. It runs from
a shell, so it still works when the site is too broken to load.

**3. Run the installer in your browser.**

Open `http://your-host/moop/setup.php`. It will ask for a one-time token:

```bash
cat .setup-token
```

The installer creates the directories, copies the config templates, generates the JBrowse2
JWT keys, runs `composer install`, and creates your admin account.

> It **self-disables** once `config/config_editable.json` exists, so it cannot be run
> again on a configured site. Prefer to do it by hand?
> See [manual setup](docs/INSTALL.md#manual-setup-without-the-installer).

**4. Deploy the two web-server files.** Not optional — see
[why](#why-the-web-server-files-matter).

```bash
# nginx
sudo cp docs/nginx/moop-security.conf /etc/nginx/default.d/moop-security.conf
sed 's#<site>#moop#g' docs/nginx/moop-site.conf.example \
  | sudo tee /etc/nginx/default.d/moop-site.conf
sudo nginx -t && sudo systemctl reload nginx
```

For Apache, and for a site not called `moop`, see
[Web server configuration](#web-server-configuration).

**5. On RHEL/CentOS/Rocky with SELinux enforcing, label the writable directories.**

```bash
getenforce                            # if this says Enforcing:
sudo scripts/fix_moop_selinux.sh      # idempotent, safe to re-run
```

Skip this on Debian/Ubuntu. Skipping it *on an enforcing host* gives you a site that loads
perfectly and then silently cannot write anything — [see below](#troubleshooting).

**6. Log in** at `http://your-host/moop/` with the admin account you just created.

Then run `php setup-check.php` once more; it should come back clean.

---

## Requirements

Only the first two are needed to reach the installer. The rest can come later — MOOP loses
individual features rather than failing to start.

| | Needed for |
|---|---|
| **PHP 7.4+** with `posix` `json` `sqlite3` `openssl` `curl` | everything |
| **Apache** (`mod_rewrite`, `mod_headers`) or **nginx** + php-fpm | everything |
| **Composer** | JBrowse2 track authentication |
| **samtools**, **bgzip**, **tabix** | genome and GFF indexing |
| **Node.js 18+** and `@jbrowse/cli` | feature-name search (`jbrowse text-index`) |
| **BLAST+** | the BLAST tool |
| **bigWigSummary** (UCSC kent) | Expression Explorer |
| **Linux/Unix**, 50GB+ disk | POSIX functions; storage scales with organism count |

**Install commands for every distro: [docs/INSTALL.md](docs/INSTALL.md).**
Hardware sizing and capacity planning:
[system requirements](tools/pages/help/system-requirements.php).

---

## After Installing

**Set your site identity** — Admin → Manage Site Configuration. Site title, admin email,
logo, sequence types. Saved to `config/config_editable.json`, effective immediately.

**Add your first organism** — Admin → Organism Checklist walks you through it step by step,
linking to the right management tool at each stage. The short version:

```
organisms/
└── Genus_species/
    ├── organism.json          organism metadata
    ├── organism.sqlite        the database
    └── assembly_name/
        ├── genome.fa          reference genome
        ├── genome.fa.fai      index — samtools faidx genome.fa
        ├── cds.nt.fa
        ├── protein.aa.fa
        └── transcript.nt.fa
```

Build the `.fai` index once per assembly (`samtools faidx genome.fa`) — the SVG gene-model
sequence viewer needs it. Creating the SQLite database is a separate toolkit:
[moop-dbtools](https://github.com/MOOPGDB/moop-dbtools).

**Decide what is public.** Access is per assembly, via
`metadata/organism_assembly_groups.json`, managed in Admin → Manage Groups. The group name
`PUBLIC` is what makes an assembly visible to visitors who are not logged in. **Nothing is
public until you say so** — a fresh install shows an anonymous visitor an empty site, which
looks like a broken one.

To check what a visitor actually sees, use **View as public** in the navbar. On a host
reached from a trusted IP range you are auto-logged-in on every request, so this is the
only way to see the unauthenticated view.

**Backups happen on their own.** Config, metadata, and user accounts are copied to
`site_data_path` on the housekeeping interval; the directory is created on first admin
login. It contains credentials — keep it private. MOOP never commits; if you make it a git
repo, committing stays manual by design.

---

## Common Tasks

| Task | Where |
|---|---|
| Add an organism | Admin → Organism Checklist |
| Make an assembly public | Admin → Manage Groups (add the `PUBLIC` group) |
| Add a user or change access | Admin → Manage Users |
| Change site title / logo / favicon | Admin → Manage Site Configuration |
| Rebuild BLAST indexes | Admin → Organism Checklist → Build BLAST Index |
| Add tracks to the genome browser | [JBrowse2 Admin Guide](docs/JBrowse2/ADMIN_GUIDE.md) |
| Link BLAST hits to JBrowse or an external site | Admin → Manage BLAST Linkouts |
| See what a public visitor sees | "View as public" in the navbar |
| Check the health of an install | `php setup-check.php` |
| Warm the organism cache | `php scripts/warm_organism_cache.php` (`--force` to rescan) |
| Upgrade JBrowse2 | `cd jbrowse2 && npx @jbrowse/cli upgrade` |

**Organism cache** — Manage Organisms validates every database, FASTA, BLAST index and
metadata file. Past ~50 organisms that scan can exceed the web server timeout, so results
are cached in `organisms/.organism_cache.json` and invalidated automatically when data
changes. Warm it from the CLI after a bulk import; the page's **Rescan** button is fine for
smaller sites.

---

## Running a Second MOOP on One Host

Supported, and it needs less than you would expect. MOOP derives its filesystem paths and
URL prefix from wherever the code lives, so a second clone at
`/var/www/html/cuttingclass` is served at `/cuttingclass/` — **no new vhost, no
`server_name` change, no TLS work.** The existing server block's PHP handler already covers
it.

Two things are per-site:

1. **The web-server files** — deploy both under a *per-site filename*
   (`cuttingclass-security.conf`, `cuttingclass-site.conf`). The templates carry the exact
   `sed` commands. Reusing the first site's filename overwrites its guard and leaves both
   sites protected by only one of them.
2. **SELinux labels**, if enforcing — `scripts/fix_moop_selinux.sh` derives its paths from
   its own location, so run the *second* site's copy.

`setup.php` refuses to run if the config it loads describes a different directory than the
one it is running in, which stops a second install writing into the first one's files.

> ⚠️ For a site whose auth-gateway rewrite is already inline in `nginx.conf`, do **not**
> also deploy `moop-site.conf` — duplicate `location` blocks fail `nginx -t` and a reload
> takes down the site that was working. Each template opens with the check for this.

---

## Genome Browser (JBrowse2)

**Nothing to install — a pre-built JBrowse2 ships in `jbrowse2/`.** MOOP generates its
configuration on the fly per organism and assembly, so there is no `config.json` to
hand-edit.

What you *do* have to do:

1. **Deploy the auth-gateway rewrite** ([step 4](#quick-start)). Without it JBrowse is
   served as plain static files and its access checks never run.
2. **Register each assembly** — Admin → Manage JBrowse, or the checklist. This builds the
   derived files under `data/genomes/{organism}/{assembly}/`, which nothing else creates.
3. **Keep `jbrowse2/` read-only** and update it only from the CLI. It is JavaScript that
   every visitor's browser executes, and the no-exec guard blocks `.php`, **not** `.js` —
   this is the one tree where filesystem permissions are the real defence.

### Where tracks live

| | |
|---|---|
| `data/genomes/{organism}/{assembly}/` | reference genome + annotations, generated at registration |
| `data/tracks/` | local track files — BigWig, BAM, VCF, CRAM |
| `metadata/jbrowse2-configs/` | per-assembly and per-track JSON that MOOP generates |
| `certs/` | the JWT keypair signing track URLs |

Tracks can be **local or remote**, per track — a remote entry is just a URL in the track
sheet, so a large collection can live on a separate host without copying anything. That
host needs a CORS allowlist and its own token-checking endpoint; the setup is in
[Tracks server setup](docs/JBrowse2/technical/TRACKS_SERVER_IT_SETUP.md), and local vs
remote path handling in
[Setting up a new organism](docs/JBrowse2/SETUP_NEW_ORGANISM.md#remote-track-server-support).

Bulk track definitions are usually managed from a spreadsheet —
[Google Sheets workflow](docs/JBrowse2/workflows/GOOGLE_SHEETS_WORKFLOW.md).

### How track access is enforced

Every track URL MOOP emits carries a JWT bound to that specific file.
`api/jbrowse2/tracks.php` validates it before serving a byte, and direct access to
`data/tracks/` is denied outright. So a copied track URL stops working, and a user cannot
reach data their account is not entitled to.
Details: [Security](docs/JBrowse2/technical/SECURITY.md).

### Linking BLAST hits into the browser

BLAST results can carry configurable linkouts — to the gene page, straight into JBrowse at
the hit coordinates, or to an external database. Configure them in **Admin → Manage BLAST
Linkouts**; they are per-registration and take effect immediately, with the coordinate
index built at registration time.

### Updating JBrowse2

```bash
cd jbrowse2
npx @jbrowse/cli upgrade
cat version.txt          # confirm the new version
```

This replaces the web app in place and preserves MOOP's configuration. **Run it from the
CLI, never through the web server** — see the read-only note above. Afterwards, load a
browser view and confirm tracks still render before considering it done.

### JBrowse2 documentation

- [Documentation index](docs/JBrowse2/_DOCUMENTATION_INDEX.md) — start here; there is a lot
- [Admin guide](docs/JBrowse2/ADMIN_GUIDE.md) — tracks, assemblies, permissions
- [User guide](docs/JBrowse2/USER_GUIDE.md) — for the people using the browser
- [Setting up a new organism](docs/JBrowse2/SETUP_NEW_ORGANISM.md) — files in, files out
- [Track formats reference](docs/JBrowse2/reference/TRACK_FORMATS_REFERENCE.md)
- [Synteny and comparative tracks](docs/JBrowse2/reference/SYNTENY_TRACKS_GUIDE.md)
- [Developer guide](docs/JBrowse2/DEVELOPER_GUIDE.md) · [API reference](docs/JBrowse2/reference/API_REFERENCE.md)

---

## Troubleshooting

**Start here.** It checks everything below and prints the fix for whatever is wrong:

```bash
php setup-check.php
```

**A page 500s right after you edited a file.** Editors save as mode `640` owned by you, and
php-fpm cannot read that. `chmod 644` the file. The real error is in the php-fpm error log
(usually root-only) — it is not an opcache problem.

**The site loads but nothing saves, with no error anywhere.** SELinux. On an enforcing host
the label decides, not the Unix mode, so a directory can look perfectly writable at `2775`
and not be. `chmod` will not fix it: run `sudo scripts/fix_moop_selinux.sh`. Three MOOP
features were dead for three days this way in July 2026 — nothing reported it, because a
denied write is not an error the app sees.

**JBrowse2 shows a cryptic load error instead of a login prompt.** The auth-gateway rewrite
is not active. On Apache, check `apachectl -M | grep rewrite` first — a missing
`mod_rewrite` makes the rule do nothing, silently.

**An anonymous visitor sees an empty site.** Expected until you put an assembly in the
`PUBLIC` group. Confirm with "View as public" rather than guessing.

**A logged-out visitor is immediately logged back in.** Auto-login by IP range. That is
`auto_login_ip_ranges` doing its job; use "View as public" to see the visitor view.

**Verifying the no-exec guard.** A 404 proves nothing — files the web user cannot read 404
by accident. Use a file that *would* execute:

```bash
echo '<?php echo "EXECTEST"; ?>' > images/wikimedia/_exectest.php
curl -s http://your-host/moop/images/wikimedia/_exectest.php   # want 404, NOT "EXECTEST"
rm -f images/wikimedia/_exectest.php
```

---

## Web server configuration

MOOP ships both files it needs; deploy them rather than copying snippets, so there is one
source of truth.

| File | Purpose |
|---|---|
| `docs/nginx/moop-security.conf` · `docs/apache/moop-security.conf` | the no-exec guard |
| `docs/nginx/moop-site.conf.example` · `docs/apache/moop-site.conf.example` | JBrowse2 auth gateway |

```bash
# Apache — RHEL/CentOS/Rocky
sudo cp docs/apache/moop-security.conf /etc/httpd/conf.d/moop-security.conf
sudo apachectl configtest && sudo systemctl reload httpd

# Apache — Debian/Ubuntu
sudo cp docs/apache/moop-security.conf /etc/apache2/conf-available/moop-security.conf
sudo a2enconf moop-security && sudo systemctl reload apache2
```

⚠ The **Apache** files pass a syntax check but have never been exercised on a host actually
serving MOOP. The directives are standard; run the VERIFY block in each before trusting
them, and please report corrections.

### Why the web server files matter

**The guard.** MOOP writes into directories it also serves over HTTP — caches, generated
indexes, uploaded images. That is safe *only* while the web server refuses to execute
`.php` inside them. Without it, one file-write bug becomes a persistent webshell. This
applies to every install, on every OS and web server. File permissions are not a substitute:
a data file that happens to be unreadable is protected by accident, not design.

**The auth gateway.** JBrowse2 is a static JavaScript app. Without the rewrite the web
server hands out `jbrowse2/index.html` directly, MOOP never sees the request, the session
is never checked — and **non-public assemblies are exposed to anyone with the URL.** It
also means a user following a saved JBrowse link with an expired session gets a login page
and is returned to where they were going, instead of a load error.

---

## Going Deeper

**Setup and operations**
- [Installing prerequisites](docs/INSTALL.md) — per-distro commands, manual setup
- [SELinux and hardening](docs/SELINUX_AND_HARDENING.md) — required on enforcing hosts
- [PHP version safety](docs/SETUP/PHP_VERSION_SAFETY.md)

**Organism data**
- [Organism setup and searches](tools/pages/help/organism-setup-and-searches.php)
- [Data organization](tools/pages/help/organism-data-organization.php) — layout and formats
- [moop-dbtools](https://github.com/MOOPGDB/moop-dbtools) — building the SQLite databases

**Development**
- [CLAUDE.md](CLAUDE.md) — architecture, conventions, and the traps worth knowing
- [System overview](docs/overview/SYSTEM_OVERVIEW.md)
- [Galaxy integration](docs/Galaxy/GALAXY_INTEGRATION.md)
