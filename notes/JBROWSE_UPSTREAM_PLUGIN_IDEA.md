# Packaging MOOP's per-user JBrowse config generation as something upstream can use

Idea raised by the user 2026-07-22: *"some of the work we have done with autogenerating
configs for permission alignment could also be of use for him, it would be nice to somehow
package that up as a usable add-on or plugin."* The user knows the JBrowse developer and
intends to make contact.

This note records **what we actually have**, separated from what is site-specific, so that
conversation can start from something concrete rather than a general offer to help.

---

## Why this is worth offering

JBrowse2 is configured by a static `config.json`. That is a good fit for a public browser
and a poor one for any site where **different users may see different data** — which is
every model-organism database with unpublished assemblies, embargoed collaborator data, or
a pre-publication gene set.

MOOP solved that, in production, across 69 registered assemblies and 1,243 tracks with
per-track access levels. The generalisable part is not the code so much as **the shape of
the solution**, which has three pieces that have to agree:

1. **Generate the config per request, filtered by who is asking** — `api/jbrowse2/config.php`
   builds assemblies and tracks from the caller's access level rather than serving a fixed
   file.
2. **Gate the app's entry point** — `auth_gateway.php` sits in front of `jbrowse2/index.html`
   via a one-line web-server rewrite, so a bookmarked or shared URL meets a session check
   instead of loading a browser that will then fail confusingly.
3. **Bind every data URL to a token** — `api/jbrowse2/tracks.php` signs each track URL with a
   JWT scoped **to that specific file**, and validates before serving a byte. A copied URL
   stops working.

Any one of those alone leaks. That coupling is the insight worth writing down for someone
else — more than any individual file.

---

## Hard-won details that belong in whatever we hand over

These are the things that were not obvious and cost us real debugging:

- **Access-level comparison must be normalised, and must fail CLOSED.** Ours did
  `$hierarchy[$level] ?? 1` against uppercase keys while 1,124 tracks stored `'Public'` —
  so any mis-cased or typo'd level silently became public. Two lookups had *opposite*
  fallbacks. The sheet's ACCESS column is free text, so this class is inevitable in any
  spreadsheet-driven setup. (Fixed here in ae07447.)
- **Normalising at one surface breaks every surface still comparing raw.** Normalising a
  filter dropdown would have silently matched 76 of 1,200 rows because the listing endpoint
  compared against the stored value.
- **A token scoped to an organism/assembly is not enough** — scope it to the file, or one
  leaked token opens a whole assembly.
- **JWT clock skew** between the app host and a separate tracks host produces failures that
  look like CORS or network problems. Cost us a day.
- **JBrowse rejects the whole config on one unknown display property.** It does not ignore
  it. So any generator must be validated against the deployed build, not against the docs.
- **Derived artefacts go stale invisibly.** Registration writes files an admin never creates
  by hand (`data/genomes/…`, registry JSON). Rename the source directory and you get a
  dangling reference and a 404 for public users, with every admin page still reporting
  "Complete". Reconciliation — walking the derived artefacts *back* to source — is the only
  check that catches it. Test whether the artefact **resolves**, not whether names match.

---

## What is reusable vs. what is ours

| Piece | Reusable? |
|---|---|
| The three-part pattern above | **Yes** — that is the contribution |
| Per-file JWT minting + validating endpoint | **Yes**, with the site's auth swapped in |
| Auth-gateway rewrite in front of `index.html` | **Yes** — a few lines of nginx/Apache |
| Access-level normalisation + fail-closed comparison | **Yes**, and arguably belongs in core |
| Track/assembly discovery | **No** — bound to MOOP's `organisms/` layout and SQLite schema |
| Google-Sheets-driven track definitions | Partly — the *idea* travels, the parser does not |
| `feature_coords.tsv`, BLAST linkouts | **No** — MOOP-specific |

---

## Possible shapes, roughly in order of effort

1. **A written pattern** — a short document plus the three-piece diagram. Cheapest, and
   possibly the most useful thing to hand a maintainer. Nothing to maintain afterwards.
2. **A reference implementation** — a small standalone example (any auth backend, one fake
   track) showing config generation + gateway + token binding. Runnable, still not a
   dependency.
3. **An actual plugin.** ⚠️ Note the asymmetry: **JBrowse plugins are client-side, and the
   entire value here is server-side.** A plugin could carry the *client* half — an
   `ExternalTokenInternetAccount`-style piece that sends credentials on data requests (see
   `notes/plan_jbrowse_auth_headers.md`, which would also move our JWT out of the URL query
   string). It cannot carry the config generation or the token minting. So "a plugin" is
   probably the wrong package for most of this, and worth saying so early rather than
   promising it.

**Suggested opening:** offer (1), mention (2), and ask whether server-side auth is something
the project wants to take a position on at all. They may already have plans; they may
consider it deliberately out of scope. That answer shapes everything else.

---

## Before any of this

**After launch.** None of it helps simrbase go live, and the JWT-in-URL work
([plan_jbrowse_auth_headers](../notes/TRACKS_PROXY_PLAN.md) and the header variant) is a
prerequisite for offering the client-side half in a form we would want to stand behind.

Related: [JBROWSE_43_OPPORTUNITIES.md](JBROWSE_43_OPPORTUNITIES.md),
[TRACKS_PROXY_PLAN.md](TRACKS_PROXY_PLAN.md),
[JBrowse2 security](../docs/JBrowse2/technical/SECURITY.md)
