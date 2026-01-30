# MOOP Issues Backlog

## Design Features (Not yet implemented)

### [DESIGN] Store Custom Column Headers for Annotation Sources
**Status:** Design phase - gathering feedback

Different annotation sources use different column naming conventions (GO, DIAMOND, InterProScan). When we import these, we lose information about what each column represents.

**Proposed Solution:** Add `column_map` JSON column to `annotation_source` table to store header metadata.

**Key Points:**
- Minimal schema change (1 column)
- Each source/version has independent mapping
- SQLite JSON support for queries
- Backward compatible

**Example:** Store GO column names (GO_ID → accession, GO_DESCRIPTION → description, NAMESPACE → score) and use in web UI display.

**Questions for discussion:**
1. Should column_map be required or optional?
2. What are good default titles if NULL?
3. Any other metadata to store per source/version?
4. How to integrate with current loader scripts?

See: `.github/issues/store-custom-column-headers.md` for full design details.

---
