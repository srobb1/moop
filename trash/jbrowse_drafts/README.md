# JBrowse Drafts Archive

This directory contains early draft/experimental versions of JBrowse track generation tools that were superseded by the final implementation.

## Files

### generate_tracks_cli.php (191 lines)
**Created:** Feb 12, 2026 (19:23)  
**Status:** OBSOLETE - Superseded by `tools/jbrowse/generate_tracks_from_sheet.php`

**Why Archived:**
- Early draft version with different CLI argument style (`--sheet-id=` vs positional)
- Missing key features:
  - No `--list-colors` flag
  - No `--suggest-colors` flag
  - No `--list-track-ids` flag
  - No `--list-existing` flag
  - No ColorSchemes integration
  - Less comprehensive documentation
- Only 191 lines vs 536 lines in final version

**Final Version:**
Use `tools/jbrowse/generate_tracks_from_sheet.php` which has:
- 100% Python script feature parity
- All 4 information flags
- 28 color schemes with cycling
- Comprehensive documentation (80+ lines)
- Support for exact= and indexed color notation
- Grep-friendly output
- Complete access level support (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)

---

*These files are kept for reference only. Do not use in production.*
