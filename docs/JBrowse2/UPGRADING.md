# Upgrading JBrowse2

MOOP ships a pre-built JBrowse2 in `jbrowse2/`. Upgrading replaces that build with a newer
one from the JBrowse project.

```bash
cat jbrowse2/version.txt                                   # note where you are starting
npx --yes @jbrowse/cli@latest upgrade /var/www/html/moop/jbrowse2
cat jbrowse2/version.txt                                   # confirm it moved
```

**Pass the path explicitly.** The documented `cd jbrowse2 && jbrowse upgrade` form relies on
the CLI defaulting to the current directory, and under `npx` it does not — it fails with
`Error: No directory supplied` (seen on the 4.1.3 → 4.3.0 upgrade, 2026-07-22). Giving it
the absolute path always works.

**Do not use `--clean`.** It deletes old `.js`, `.map` and `LICENSE` files from the
installation — and MOOP keeps its own `.js` inside this directory (see below), so a clean
upgrade can silently remove ours. Without it, superseded chunks linger harmlessly; the
build's own `index.html` only references the current ones.

---

## Run it from a shell, never through the web server

`jbrowse2/` is the one tree in MOOP where **filesystem permissions are the real defence**.

Everywhere else, MOOP relies on the web server refusing to execute `.php` inside writable
directories ([the no-exec guard](../nginx/moop-security.conf)). That guard blocks `.php` —
**not `.js`**. And `jbrowse2/` is nothing but JavaScript that every visitor's browser
executes. A writable `jbrowse2/` means anyone who can write a file there can run code in
your users' browsers, and no web-server rule will stop it.

So: keep the directory read-only to the web server, and upgrade over SSH as a user who owns
the files. `scripts/fix_moop_selinux.sh` deliberately does **not** make `jbrowse2/` writable.

---

## What survives an upgrade, and what does not

**Safe — MOOP's real configuration lives outside `jbrowse2/`:**

| What | Where | Why it is safe |
|---|---|---|
| Plugin list | `config/jbrowse2_plugins.json` | outside the upgraded tree |
| Assemblies and tracks | generated per request by `api/jbrowse2/config.php` | never stored in `jbrowse2/` |
| Per-assembly configs | `metadata/jbrowse2-configs/` | outside the upgraded tree |
| JWT keys | `certs/` | outside the upgraded tree |

MOOP does **not** use `jbrowse2/config.json` as its configuration — nothing in the codebase
reads that file. It ships with an empty assemblies/tracks list and a duplicate copy of the
MafViewer plugin declaration. The live plugin list is `config/jbrowse2_plugins.json`; the
copy in `jbrowse2/config.json` is stale and unread, so losing it in an upgrade costs nothing.

**At risk — anything MOOP added inside `jbrowse2/`.** Check before upgrading:

```bash
# files in jbrowse2/ that are not part of a stock JBrowse build
ls jbrowse2/
```

A stock build contains `asset-manifest.json`, `config.json`, `favicon.ico`, `index.html`,
`manifest.json`, `robots.txt`, `static/`, `test_data/`, `umd_plugin.js`, `version.txt`.
Anything else is ours. This deployment currently also has
`jbrowse2/jbrowse2-view-loader.js`, which is a MOOP file (an older, diverged copy of
`js/jbrowse2-view-loader.js`); nothing references either, so both are orphans — but do not
assume that stays true. `git status` after an upgrade will show you exactly what changed.

---

## Afterwards

The upgrade is not done when the command exits. Check:

1. **`cat version.txt`** — confirm it actually moved.
2. **`git status jbrowse2/`** — see what was replaced, added, and deleted. This is the
   cheapest way to spot a MOOP file that got removed.
3. **Open a browser view and confirm tracks render.** A config the previous version
   tolerated can become fatal: JBrowse does not ignore an unknown display property, it
   rejects the whole config. If a view goes blank, that is the first thing to suspect.
4. **Confirm the auth gateway still works** — log out, then open a JBrowse URL for a
   non-public assembly. You should land on the login page. The gateway is a web-server
   rewrite onto `index.html`, so an upgrade that changes how the entry point is served can
   break it. See the [per-site config template](../nginx/moop-site.conf.example).
5. **Fix permissions — this WILL bite you.** The release zip unpacks as mode `640`, and the
   browser then 403s on every new asset while `index.html` itself still loads, so the page
   comes up blank rather than obviously broken.

```bash
chmod -R a+rX jbrowse2/          # readable by any web server user, still not writable
```

> **World-read, not group-read — and this is the part that wastes an afternoon.** On a
> typical RHEL/nginx host there are **two different web users**: php-fpm runs as `apache`,
> but **static files are served by nginx itself, running as `nginx`**. So `chgrp apache` +
> `chmod g+rX` looks correct, passes every "can the web server read it" check you think to
> write, and still 403s — because the user actually opening the file is `nginx`, which is not
> in the `apache` group. That is exactly why the rest of this tree is `644`. Confirm your own
> host with `ps -o user=,comm= -C nginx` and `grep ^user /etc/nginx/nginx.conf`; do not
> assume "the web server user" is one user.

---

## Plugins

Plugins are declared in `config/jbrowse2_plugins.json`:

```json
[
  {
    "name": "MafViewer",
    "url": "https://jbrowse.org/plugins/jbrowse-plugin-mafviewer/dist/jbrowse-plugin-mafviewer.umd.production.min.js",
    "description": "Multiple Alignment Format (MAF) viewer for comparative genomics",
    "enabled": true
  }
]
```

Only entries with `"enabled": true` are emitted into the generated config. A plugin built
against an older JBrowse can stop working after an upgrade — if a view breaks immediately
after upgrading, disabling plugins one at a time is the fastest way to isolate it.

> ⚠️ **These URLs are external.** The MafViewer plugin above is fetched from `jbrowse.org` at
> runtime, which means an air-gapped host cannot load it, and enforcing a
> `script-src 'self'` Content-Security-Policy will block it. MOOP's CSP is currently
> Report-Only, so this works today — but it is a live dependency to remember when the CSP is
> enforced. Self-hosting the plugin file and pointing the URL at your own path avoids both
> problems.

---

## Related

- [Documentation index](_DOCUMENTATION_INDEX.md)
- [Admin guide](ADMIN_GUIDE.md) — tracks, assemblies, permissions
- [Setting up a new organism](SETUP_NEW_ORGANISM.md)
- [JBrowse2 release notes](https://jbrowse.org/jb2/docs/) — upstream
