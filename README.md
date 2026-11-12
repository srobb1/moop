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
- [How Search Matches Words](#how-search-matches-words)
- [Genome Browser (JBrowse2)](#genome-browser-jbrowse2)
- [Running a Second MOOP on One Host](#running-a-second-moop-on-one-host)
- [A New Site on Another Machine](#a-new-site-on-another-machine)
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
git config core.hooksPath .githooks
.githooks/moop-fix-web-perms
```

The last two lines are not optional. git writes the files it checks out with your umask
(often `640` files in `750` directories), which the web server cannot read, so the site
returns 500s — including `setup.php` in step 3. The hooks in `.githooks/` fix the modes
after every later checkout or pull, but git will not run a repository's hooks until the
clone opts in, and they do not touch the files the clone already wrote: the second line
fixes those once.

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

**6. Turn on the daily security-update report.**

```bash
sudo scripts/install_security_report.sh   # once; safe to re-run
```

The admin dashboard lists pending OS security updates (PHP, the web server, OpenSSL, the
kernel…), but the web server is not allowed to ask the package manager itself — under SELinux
it cannot read the package database. This installs a small systemd timer that, as root, writes
a plain-text report into MOOP's cache directory once a day and 10 minutes after each boot.
MOOP only reads that report; it never runs as root and never installs anything. Re-run the
script if you change `cache_path`. Without it the dashboard card says "Needs a one-time setup".

**7. Protect and watch the server** (nginx; safe to re-run).

```bash
sudo scripts/install_monitor.sh
```

One command for the whole layer that keeps crawlers and AI scrapers from draining the server
and shows what it is doing:

- **Security headers + an enforced Content-Security-Policy** (skipped if your nginx already
  sets them). The tracks server, if you configured one, is allowed automatically.
- **Rate limits per address** (pages, AJAX, genome browser, exports/downloads) and
  **robots.txt**. Your auto-login IP ranges are never limited, and when you change them in
  Site Configuration the limits follow within a minute — nothing to re-run.
- **The System Monitor collector**: a root timer that records CPU, RAM, disk, php-fpm load and
  nginx traffic once a minute for the admin dashboard. It never runs MOOP code as root.

Why and how: `notes/BOT_PROTECTION_PLAN.md`, `notes/SYSTEM_MONITOR_PLAN.md`. Apache hosts: not
covered yet — these are nginx configuration.

**8. Keep one heavy search from taking the site down** (safe to re-run).

```bash
sudo scripts/install_php_fpm_resilience.sh   # php-fpm keeps running when one request runs out of memory
sudo scripts/install_nginx_timeouts.sh       # nginx (only) waits long enough for a queued BLAST
```

Both were found by a capacity test: without the first, ONE BLAST that runs out of memory
stops php-fpm and the whole site stays down until someone restarts it by hand; without the
second, nginx gives up after 60 s and shows a 504 while a queued search is still running.
Background: `notes/CAPACITY_TEST_2026-10-23.md`.

**9. Install the JBrowse CLI** if you will use the genome browser — it builds the gene-name
search index. See [Node.js and the JBrowse CLI](docs/INSTALL.md#nodejs-and-the-jbrowse-cli);
without it the genome browser works but its search box finds nothing.

**10. Turn on HTTPS** once your site has a name with a certificate (nginx). Until then, logins
and session cookies cross the network in cleartext. When `https://your-name/` answers with a
valid certificate:

```bash
sudo scripts/install_https_redirect.sh your-name   # http:// visitors -> https://your-name (safe to re-run)
```

This machine itself stays on plain http, because the System Monitor calls `http://127.0.0.1/`.
If you use a tracks server, it must allow `https://your-name` (CORS) before tracks load at
that name.

**11. Log in** at `https://your-name/moop/` (or `http://your-host/moop/` before step 10) with
the admin account you just created.

Then run `php setup-check.php` once more; it should come back clean.

---

## Requirements

Only the first two are needed to reach the installer. The rest can come later — MOOP loses
individual features rather than failing to start.

| | Needed for |
|---|---|
| **PHP 8.0+** with `pdo_sqlite` `sqlite3` `mbstring` `json` `openssl` `curl` `posix` | everything |
| **Apache** (`mod_rewrite`, `mod_headers`) or **nginx** + php-fpm | everything |
| PHP `gd` (`php-gd`) | thumbnails of gene images (optional; without it, full-size images) |
| **Composer** | JBrowse2 track authentication |
| **samtools**, **bgzip**, **tabix** | genome and GFF indexing |
| **Node.js 18+** and `@jbrowse/cli` | feature-name search (`jbrowse text-index`) |
| **BLAST+** | the BLAST tool |
| **bigWigSummary** (UCSC kent) | Expression Explorer |
| **primer3** (`scripts/install_primer3.sh`) | the primer designer |
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
| Link BLAST hits to JBrowse or an external site | Admin → Manage BLAST Linkouts ([guide](docs/BLAST_LINKOUTS.md)) |
| See what a public visitor sees | "View as public" in the navbar |
| Check the health of an install | `php setup-check.php` |
| Warm the organism cache | `php scripts/warm_organism_cache.php` (`--force` to rescan) |
| Upgrade JBrowse2 | [Upgrading JBrowse2](docs/JBrowse2/UPGRADING.md) |
| Add or upgrade a front-end library (JS/CSS) | Add or edit its line in `scripts/vendor_assets.txt`, run `scripts/fetch_vendor_assets.sh --force` — see below |
| Install pending OS security updates | Admin → System Status → OS Security Updates shows the command |

**Third-party libraries** — jQuery, Bootstrap, DataTables, Font Awesome and the rest are
self-hosted, and **every one must have a line in `scripts/vendor_assets.txt`**: its path, the URL
it came from, its sha256, and `ecosystem:package@version` (e.g. `npm:jquery@3.6.0`). That one
file is how `scripts/fetch_vendor_assets.sh` downloads and verifies them, and how the dashboard's
Third-party Libraries card asks [OSV](https://osv.dev) about known vulnerabilities each week. A
file that is not listed is never checked, so CI fails when a third-party file appears without a
line (`tests/smoke_tests.php`), and so does a page that loads a script from a CDN. Composer
packages need nothing — they are read from `vendor/composer/installed.json`.

**Security and upkeep** — Admin → System Status checks, without you asking: pending OS
security updates (step 6 above), known vulnerabilities in the libraries MOOP ships, deprecated
PHP calls the site has made, and — in CI, on every push — whether the code still works on PHP
8.3. All of it is report-only.

**Organism cache** — Manage Organisms validates every database, FASTA, BLAST index and
metadata file. Past ~50 organisms that scan can exceed the web server timeout, so results
are cached in `organisms/.organism_cache.json` and invalidated automatically when data
changes. Warm it from the CLI after a bulk import; the page's **Rescan** button is fine for
smaller sites.

---

## How Search Matches Words

Search and the Data Exporter's text boxes use the same rules: each organism's full-text
index (built by the pipeline into `organism.sqlite`) with a word-ending stemmer.

| Expected behaviour | Example |
|---|---|
| Plurals find singulars | `kinases` finds *protein kinase domain* |
| Singulars find plurals | `kinase` finds *family of kinases* |
| Matches from the start of a word | `wnt` finds *WNT8B* |
| Hyphenated names split into words | `wnt1` finds *Smed-wnt1* |
| Never from the middle | `inase` finds nothing; `transposase` does not find *retrotransposase* |
| Case does not matter | `KINASE` finds *kinase* |
| Several words: all required, any order | `kinase protein` skips a record with only one of them |
| Quotes: side by side, in order | `"ABC transporter"` does not find *transporter of the ABC class* |

The stemmer also makes `transporter` find *transport*; Search lists exact matches first,
while the Exporter, which does not rank, exports every match. Both the stemmer and the
no-mid-word rule are deliberate. Every row above is asserted by
`tests/search_ranking_tests.php`, so a change that breaks one fails CI — change the tests,
this table and the help text together.

---

## Running a Second MOOP on One Host

Supported, and it needs less than you would expect. MOOP derives its filesystem paths and
URL prefix from wherever the code lives, so a second clone at
`/var/www/html/cuttingclass` is served at `/cuttingclass/`: **no new vhost, no
`server_name` change, no TLS work.** The existing server block's PHP handler already covers
it. Its login and language cookies are named after its folder (`MOOPSESS_cuttingclass`), so
being logged in to one site never logs you in to the other.

The steps below are the ones that installing cuttingclass beside simrbase actually needed
(2026-11-05). `cuttingclass` stands for your site's folder name throughout.

1. **Clone and opt in to the hooks**, as in [Quick Start](#quick-start) step 1, into
   `/var/www/html/cuttingclass`.
2. **Create the folders outside the document root, as root**, owned by the web server.
   `/var/www` is root's, so neither you nor the web server can create them:

   ```bash
   sudo install -d -o apache -g apache -m 2770 /var/www/cuttingclass-private /var/www/cuttingclass-site-data
   sudo install -d -o apache -g apache -m 2775 /var/www/cuttingclass-cache
   ```
3. **Make the folders the web server writes into group-writable**, and create the ones a
   fresh clone does not have. git leaves directories `2750`, which the installer reports as
   "Cannot write". Directories only, never `chmod -R`, which would make every data file
   executable; `data/tracks` stays read-only:

   ```bash
   cd /var/www/html/cuttingclass
   mkdir -p logs data/genomes data/tracks metadata/change_log metadata/jbrowse2-configs
   chgrp -R apache config metadata images organisms logs data docs
   chmod 2775 config metadata metadata/change_log metadata/jbrowse2-configs images organisms logs data/genomes docs
   ```
4. **SELinux labels**, if enforcing: `sudo scripts/fix_moop_selinux.sh`, run from the
   *second* site's checkout (it derives every path from its own location). It labels the
   folders from step 2 too.
5. **From the shell, the steps the web server is not allowed to do under SELinux** (the
   installer then reports them as already done):

   ```bash
   composer install --no-dev
   openssl rand -hex 16 > .setup-token && chmod 640 .setup-token && chgrp apache .setup-token
   ```

   Generate the JWT key pair the same way if the installer cannot write `certs/`
   (`setup-check.php` prints the command).
6. **The web-server files, under per-site filenames.** Generate the guard, never copy it with
   `sed`: a second site must leave out the three server-wide rules the first site's guard
   already applies, or `nginx -t` fails on a duplicate `location = /users.json`:

   ```bash
   G=/etc/nginx/default.d/$(scripts/nginx_site_guard.sh --deploy-name cuttingclass)
   sudo bash -c "scripts/nginx_site_guard.sh cuttingclass > $G" && sudo chmod 644 $G
   ```

   **Use that file name, never type your own.** nginx reads these files in name order and the
   first `.php` rule wins, so a guard whose name sorts after `php.conf` protects nothing while
   looking installed. `--deploy-name` gives `<site>-security.conf` when that sorts first and
   `00-<site>-security.conf` otherwise (`planosphere` needs the `00-`). `setup-check.php` tests
   it for real: it puts a `.php` in `images/`, requests it, and fails if it runs.

   From `docs/nginx/moop-site.conf.example` deploy **only the JBrowse auth-gateway rule**
   (`location = /cuttingclass/jbrowse2/index.html { ... }`) as
   `/etc/nginx/default.d/cuttingclass-site.conf`. Its gzip lines repeat what the first site's
   server block already sets, which is a duplicate-directive error. Then
   `sudo nginx -t && sudo systemctl reload nginx`.
7. **Run the installer** at `http://your-host/cuttingclass/setup.php` with the token from
   step 5.
8. **In Site Configuration**, set the cache folder to `/var/www/cuttingclass-cache` and the
   site-data folder to `/var/www/cuttingclass-site-data`. (Left empty, caches go in a hidden
   `organisms/.cache/`, which works but mixes them into the data tree.)
9. **Run `sudo scripts/install_security_report.sh` from the second site too** -- it adds that
   site to the daily OS security report (made once, copied into every site's cache folder), so
   both dashboards have it. **Do not run `install_monitor.sh` or `install_nginx_limits.sh`** for
   the second site: the System Monitor and nginx's rate limits serve one site per server, and
   running them from the second would re-point them, replacing the first site's rate-limit
   exemptions for the whole server. Both scripts now refuse and say so; `--move-here` moves them
   on purpose. The server is already watched from the first site's dashboard.

**Keeping the sites on the same code.** Every site's checkout comes from the same repository,
and none is edited by hand: change one tree, commit and push, then update every site at once:

```bash
scripts/update_sites.sh            # pull, fix permissions, setup-check, every site
scripts/update_sites.sh --check    # report only
```

- **Which sites:** every MOOP checkout under `/var/www/html` — it finds them itself (on this
  host: cuttingclass, planosphere, moop/simrbase). A new site needs no registering; once it is
  cloned there, the next run includes it.
- **Run it from any one site's folder, as the checkouts' owner** (not root, not `sudo`): git
  and the permissions hook need the owner.
- **Order:** the other sites first, simrbase (`moop`) last, so a bad update shows up on a
  smaller site first. It stops at the first problem.
- **Per site:** refuses if there are local changes (so a hand edit cannot be silently kept or
  lost), `git pull --ff-only`, restores web-readable permissions, runs `setup-check.php` and
  reports its FAIL count.
- **Success** ends with `All N sites at the same commit.` and exit 0.

A new script in `scripts/` (such as `reset_password.php`) reaches every site this way; run
each site's own copy from that site's folder.

`setup.php` refuses to run if the config it loads describes a different directory than the
one it is running in, which stops a second install writing into the first one's files.

> ⚠️ For a site whose auth-gateway rewrite is already inline in `nginx.conf`, do **not**
> also deploy `cuttingclass-site.conf`: duplicate `location` blocks fail `nginx -t`, and a
> reload takes down the site that was working. The template opens with the check for this.

---

## A New Site on Another Machine

The [Quick Start](#quick-start) installs the application. A new machine also needs what the
first host got by hand over time. Go through this list as well, in roughly this order:

1. **A hostname and a certificate first.** Ask IT for the DNS name before you start, and get
   a certificate for it. Configure HTTPS from day one: retrofitting it means changing URLs
   people have already bookmarked, and the session cookie is only marked `Secure` on HTTPS
   requests (`includes/session_init.php`).
2. **Both nginx server blocks (80 and 443) need `root /var/www/html;` and
   `include /etc/nginx/default.d/*.conf;`.** Stock RHEL nginx puts both only in the port-80
   block. A 443 block without the include misses the security guard, the auth gateway, the
   timeouts and the rate limits; one without `root` serves nothing (on the first host that
   broke the genome browser with "Failed to fetch").
3. **The Quick Start, steps 1–10.** `php setup-check.php` checks each one, and its
   `Inspecting install:` line must name the directory you are setting up.
4. **The genome-browser tracks server, if this site will use one.** It verifies every track
   token with a *single* public key (`certs/jwt_public_key.pem` on that server), so a second
   site's tokens fail there until you choose one of:
   - **its own tracks server, or its own directory on the shared server with its own copy of
     the server files** (the clean separation), or
   - **sharing the first site's key pair** (copy `certs/*.pem`). This is simpler, but either site
     can then mint tokens for the other's tracks, so a compromise of one exposes both.

   Either way, add the new site's origin (`https://newhost`) to the tracks server's CORS
   allow-list. The CSP side follows automatically from `tracks_server.url` when you run
   `install_monitor.sh`.
5. **Organism data.** `organisms/` is filled by the build pipeline, not by MOOP. Arrange
   with whoever runs the pipeline which organisms go to the new site and where they are
   copied. MOOP only needs them under `organisms/` (or `organism_data`).
6. **Cache directory outside the document root**: set `cache_path` (Admin → Site
   Configuration) to e.g. `/var/www/<site>-cache`. `scripts/fix_moop_selinux.sh` creates
   and labels it. Re-run `install_security_report.sh` and `install_monitor.sh` afterwards;
   both write into the cache directory. The cache grows with use, so it belongs on the big disk;
   if `/var/www` is on a small disk, see docs/INSTALL.md, "Cache on the big disk".
7. **Site-data backup.** Housekeeping copies config, metadata and the users file into
   `site_data_path` on its own. Make that directory a git repository with a private remote
   if you want history off the machine. MOOP never commits; the dashboard shows the command.
8. **Who sees what.** A new install has no auto-login IP ranges and no public assemblies.
   An address range in `auto_login_ip_ranges` gets *full data access with no login*, so add
   only ranges you would trust with every restricted assembly.
9. **Memory.** RAM, not CPU, runs out first under load: one tblastn against a 3 GB genome
   uses ~2.9 GB. Size the machine for your biggest genomes × the number of searches you
   expect at once (`notes/CAPACITY_TEST_2026-10-23.md`).

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
the hit coordinates, or out to an external database with the hit ID substituted into a URL
template. Configure them in **Admin → Manage BLAST Linkouts**.

Gene-page and browser links need a per-gene-set coordinate index (`feature_coords.tsv`),
built when the assembly is registered — there is no lazy fallback, deliberately, so a BLAST
search never triggers a file scan. Full guide: **[BLAST linkouts](docs/BLAST_LINKOUTS.md)**.

### Updating JBrowse2

```bash
cat jbrowse2/version.txt                                    # where you are starting
npx --yes @jbrowse/cli@latest upgrade /var/www/html/moop/jbrowse2
chmod -R a+rX jbrowse2/                                     # the zip unpacks as 640
cat jbrowse2/version.txt                                    # confirm it moved
```

Pass the path explicitly — the `cd jbrowse2 && jbrowse upgrade` form fails under `npx`. And
do **not** pass `--clean`: it deletes `.js` files, and MOOP keeps its own inside that tree.

This replaces the web app in place. MOOP's own configuration is safe because it lives
outside `jbrowse2/` — the plugin list is `config/jbrowse2_plugins.json`, and assemblies and
tracks are generated per request.

**Run it from the CLI, never through the web server** — see the read-only note above.
Afterwards check `git status jbrowse2/` for anything of ours that was replaced, confirm
tracks still render, and confirm the auth gateway still redirects a logged-out user.
Full checklist: **[Upgrading JBrowse2](docs/JBrowse2/UPGRADING.md)**.

### JBrowse2 documentation

- [Documentation index](docs/JBrowse2/_DOCUMENTATION_INDEX.md) — start here; there is a lot
- [Admin guide](docs/JBrowse2/ADMIN_GUIDE.md) — tracks, assemblies, permissions
- [Upgrading JBrowse2](docs/JBrowse2/UPGRADING.md) — what survives an upgrade, and what to check after
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

**Locked out of the web admin, or forgot the password.** Recover from a shell on the server,
using the copy of the script in that site's folder (it finds the site's own users file):

```bash
sudo -u apache php scripts/reset_password.php --list             # usernames + roles
sudo -u apache php scripts/reset_password.php <username>         # new password, asked twice, not echoed
sudo -u apache php scripts/reset_password.php --unlock           # clear "too many attempts" lockouts
```

A lockout (10 failed attempts) also lifts by itself after 15 minutes. If a password that
works on one address fails on another (the IP vs the site's name), check the browser's saved
password first — to the browser those are two different sites.

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
