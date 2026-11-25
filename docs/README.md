# MOOP documentation

**Last updated:** 2026-11-25 (review: 22 outdated or duplicate docs moved to `archive/`, the
security docs merged, the system overview cut to a short map). Every current doc is listed
below; if a doc is not here, it is in `archive/` and is history, not instructions.

Start with [overview/SYSTEM_OVERVIEW.md](overview/SYSTEM_OVERVIEW.md) -- one page on what the
pieces are and which doc covers each. The working rules for changing the code are in
`CLAUDE.md` at the repository root.

## Set up, secure, maintain

| Doc | What it covers |
|---|---|
| [INSTALL.md](INSTALL.md) | A new host or site, step by step (then run `setup-check.php`) |
| [SELINUX_AND_HARDENING.md](SELINUX_AND_HARDENING.md) | SELinux labels, permissions, the web-server no-exec guard |
| [SETUP/PHP_VERSION_SAFETY.md](SETUP/PHP_VERSION_SAFETY.md) | Running on PHP 8.0 and getting ready for 8.3 |
| [current/admin/CONFIG_GUIDE.md](current/admin/CONFIG_GUIDE.md) | How settings work (defaults + Site Configuration) |
| [current/admin/PERMISSIONS_GUIDE.md](current/admin/PERMISSIONS_GUIDE.md) | Who can see which organisms and gene sets |
| [current/admin/SECURITY_GUIDE.md](current/admin/SECURITY_GUIDE.md) | Logins, sessions, CSRF, the Content-Security-Policy, the guard, bot protection |

## Data formats and sources

| Doc | What it covers |
|---|---|
| [ORGANISM_SIDE_FILES.md](ORGANISM_SIDE_FILES.md) | The files beside each organism's database |
| [CROSSWALK_FORMAT.md](CROSSWALK_FORMAT.md) | The Rosetta Stone ID translator's file |
| [CLADE_HOMOLOGS_FORMAT.md](CLADE_HOMOLOGS_FORMAT.md) | Orthologs and gene families in related species |
| [ONTOLOGIES.md](ONTOLOGIES.md) | PLANA, BSPO, ECO, GO: where they come from and how to refresh them |
| [BLAST_LINKOUTS.md](BLAST_LINKOUTS.md) | Links from BLAST hits to other sites |
| [current/features/BLAST_FLOW_CHART.md](current/features/BLAST_FLOW_CHART.md) | How a BLAST search runs |
| [current/features/PHYLO_TREE_MANAGER.md](current/features/PHYLO_TREE_MANAGER.md) | The taxonomy tree (page parked) |

## Genome browser (JBrowse 2)

| Doc | What it covers |
|---|---|
| [JBrowse2/README.md](JBrowse2/README.md) | Start here |
| [JBrowse2/ADMIN_GUIDE.md](JBrowse2/ADMIN_GUIDE.md) | Managing assemblies and tracks |
| [JBrowse2/SETUP_NEW_ORGANISM.md](JBrowse2/SETUP_NEW_ORGANISM.md) | Adding an organism to the browser |
| [JBrowse2/workflows/GOOGLE_SHEETS_WORKFLOW.md](JBrowse2/workflows/GOOGLE_SHEETS_WORKFLOW.md) | Registering tracks from the track sheet |
| [JBrowse2/GENE_TRACK_INDEXES.md](JBrowse2/GENE_TRACK_INDEXES.md) | The gene track, coordinate files and search index (read before touching them) |
| [JBrowse2/UPGRADING.md](JBrowse2/UPGRADING.md) | Upgrading the JBrowse app |
| [JBrowse2/USER_GUIDE.md](JBrowse2/USER_GUIDE.md) | Using the browser |
| [JBrowse2/DEVELOPER_GUIDE.md](JBrowse2/DEVELOPER_GUIDE.md) | How the browser integration is built |
| [JBrowse2/technical/SECURITY.md](JBrowse2/technical/SECURITY.md) | Track access: signed tokens, the tracks server |
| [JBrowse2/technical/TRACKS_SERVER_IT_SETUP.md](JBrowse2/technical/TRACKS_SERVER_IT_SETUP.md) | Setting up the tracks server (for IT) |
| [JBrowse2/SYNTENY_AND_COMPARATIVE.md](JBrowse2/SYNTENY_AND_COMPARATIVE.md), [JBrowse2/reference/SYNTENY_TRACKS_GUIDE.md](JBrowse2/reference/SYNTENY_TRACKS_GUIDE.md) | Synteny tracks (plan: `notes/SYNTENY_PLAN.md`) |
| [JBrowse2/reference/TRACK_FORMATS_REFERENCE.md](JBrowse2/reference/TRACK_FORMATS_REFERENCE.md), [JBrowse2/reference/API_REFERENCE.md](JBrowse2/reference/API_REFERENCE.md) | Track formats; the browser API endpoints |

## For visitors

- [current/user/USER_GUIDE.md](current/user/USER_GUIDE.md) -- using the site. (Most visitor help is
  on the site itself: Help, and the info buttons on each page.)

## Galaxy (backend built, page not yet)

- [Galaxy/GALAXY_INTEGRATION.md](Galaxy/GALAXY_INTEGRATION.md) -- start here;
  [Galaxy/GALAXY_INTEGRATION_STATUS.md](Galaxy/GALAXY_INTEGRATION_STATUS.md) -- where it stopped;
  [Galaxy/GALAXY_INTEGRATION_PLAN.md](Galaxy/GALAXY_INTEGRATION_PLAN.md),
  [Galaxy/GALAXY_MAFFT_TEST.md](Galaxy/GALAXY_MAFFT_TEST.md),
  [planning/SEQUENCE_ALIGNER.md](planning/SEQUENCE_ALIGNER.md) -- the alignment page it was for.

## History

- [CLAUDE_HISTORY.md](CLAUDE_HISTORY.md) -- the longer stories behind rules in `CLAUDE.md`.
- [archive/](archive/README.md) -- outdated docs kept for the record.
- Planning notes (work list, plans, notes for compute) are in `notes/`, not here.
