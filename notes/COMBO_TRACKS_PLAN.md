# Combo tracks: references, not copies — and a sheet format without `#` blocks

**Status:** PLAN, not started (written 2026-10-20). The current format keeps working
unchanged until a step below is done; nothing here is half-built.

## Why

Combo tracks (JBrowse `MultiQuantitativeTrack`: several BigWigs overlaid in coloured groups)
come from the per-assembly Google track sheets. Two separate problems, worth keeping apart:

### 1. A combo is a snapshot, so a track update never reaches it

`ComboTrack::generate()` copies each member's file URL, display name and colour into the
combo's own JSON (`metadata/jbrowse2-configs/tracks/{org}/{asm}/combo/{id}.json`,
`adapter.subadapters[]`). Fix a member track later — path, name, access — and every combo
that contains it still serves the old copy. On top of that, a sync skips any track whose JSON
already exists, so metadata edits in the sheet do not reach built tracks at all unless the
sync is forced (only ACCESS is carried over, since 11a47dad).

This is independent of how combos are written down: a perfect authoring tool would still
drift, because the output is a copy.

### 2. The `#` / `##` / `### end` layout carries meaning in structure

A combo is a block: `# Name`, then `## colour: group` lines, member rows, `### end`.
Meaning lives in nesting and order, so small slips change what is built, silently. Found in
one afternoon (2026-10-20), all now fixed or reported, none of which the format could show:

- a combo name containing `/` was never written (23 Nematostella circadian combos) — 382f8742;
- a colour group named `0` lost its rows (PHP "0" is falsy; 4 Woods Hole combos) — bbd7e94b;
- the same combo name used twice, the second silently replacing the first (H3K4me3 Vienna;
  circadian blue timepoint 5/6) — user renamed;
- a missing `### end`, so a combo was never built (circadian dark timepoint 10) — user fixed;
- every member row repeats the track's whole row (425 of Nematostella's 1,166 tracks appear
  more than once), so an edit to one copy does not reach the others. e4206a3d made the first
  full row the definition and lets a member row be just the TRACK_ID, which stops the
  drift in the SHEET — but the format is still blocks.

The user's wish (2026-10-20): no combo sections in the sheet; put a track in several groups
without repeating its metadata; other people (not only MOOP admins) able to work on combos.

## Plan

### Step 1 — combos as references, assembled when JBrowse loads (do first)

Fixes problem 1 whatever the authoring choice in step 2.

- A combo's stored definition becomes: id, name, category, and ordered groups
  `{name, colour set, members: [trackId, ...]}` — no URLs, no copied names.
- `api/jbrowse2/config.php` builds the `MultiWiggleAdapter` per request from the members'
  CURRENT track JSONs: their adapter location (tokenised exactly as the tracks themselves,
  `addTokenToAdapterUrls`), display name, and a shade from the group's colour set
  (`ColorSchemes`, assigned in member order as `ComboTrack` does now).
- Access: the strictest of the members' current levels (`strictestAccessLevel`), computed per
  request — and a member the viewer may not see is left out of the overlay; a combo with no
  visible members is not listed. (Today a combo's access is fixed at build time.)
- A member that no longer exists is skipped and reported on the admin page, not served.
- Existing combo JSONs convert by mapping each subadapter URI back to the member track whose
  adapter has that URI (all 180 current Nematostella combos should map; report any that do
  not). Keep the old JSON shape readable until converted.

Checks before switching: for every existing combo, the assembled config equals today's
(same URIs, names, colours, order) for an IP_IN_RANGE viewer; an anonymous viewer of
Nematostella sees the same public combos as now (176); a COLLABORATOR-only member is absent
for a PUBLIC viewer.

### Step 2 — sheet: a Tracks tab and a Combos tab (no `#` lines)

- **Tracks tab:** one row per track, as today, with no `#` / `##` / `###` lines.
- **Combos tab:** one row per membership — `COMBO | GROUP | COLOR | TRACK_ID` (+ optional
  `CATEGORY` / `DESCRIPTION`, read from the first row of a combo). A combo ends where its rows
  end; group and member order = row order; a track in several combos = several short rows.
- TRACK_ID uses a Google Sheets **dropdown from a range** (Data → Data validation → Dropdown
  from a range → `Tracks!A2:A`), so an ID cannot be mistyped and new tracks appear in it.
- Collaborators edit in Sheets (sharing, history, comments) — no MOOP admin account needed.
- Registration stores a second tab id (`COMBO_GID`) next to `GID` in
  `metadata/jbrowse2-configs/sheets/{org}/{asm}/jbrowse_tracks_sheet.txt`; Manage JBrowse →
  register/edit sheet gets a field for it.
- Sync checks carry over and are printed before anything is written: unknown TRACK_ID, a
  group given two colours, an empty combo, a duplicate combo name across tabs.

Migration: a script converts the existing `#` blocks of a sheet into Combos-tab rows (TSV to
paste) — 210 Nematostella combos, 9 Congeria. Compare the combos built from the new tab with
those from the old blocks (same groups, colours, members) before deleting the blocks. During
the switch the reader accepts both, and warns when a sheet has both for the same combo.

### Step 3 — optional: a combo view in Manage JBrowse

Read-only first: each combo with its groups, members (linked to the track), computed access,
and problems (missing member, hidden member, conflicting colour). A builder UI (pick tracks
from a searchable list) only if Sheets editing proves awkward — it would limit editing to
MOOP admins.

## Not decided

- Combo-level fields beyond name: category (default "Combo Tracks") and description seem
  enough; display order of combos in JBrowse?
- Should a sync also refresh existing tracks' metadata (name, description, category) from
  their definitions, not only ACCESS — making the sheet the single source of truth?
  Recommended; small, but changes what a non-forced sync does.
- `scripts/expression_samples_from_sheet.php` also reads track sheets; check it before
  removing the `#` format.

## Touch points

`lib/jbrowse/GoogleSheetsParser.php` (parseTracks, strictestAccessLevel),
`lib/jbrowse/TrackTypes/ComboTrack.php`, `lib/jbrowse/TrackGenerator.php`,
`lib/jbrowse/ColorSchemes.php`, `api/jbrowse2/config.php` (loadFilteredTracks,
addTokensToTrack), `admin/api/jbrowse_list_tracks.php`, `admin/api/jbrowse_delete_tracks.php`,
`js/jbrowse-admin.js`, `scripts/generate_tracks_from_sheet.php`,
`docs/JBrowse2/workflows/GOOGLE_SHEETS_WORKFLOW.md`.
