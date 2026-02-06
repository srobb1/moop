# JBrowse2 Documentation Archive

**Date Archived:** February 6, 2026  
**Reason:** Documentation consolidation

---

## What Happened

The JBrowse2 documentation was reorganized for better maintainability:

- **Before:** 22+ separate documentation files
- **After:** 7 consolidated guides

## New Documentation Structure

See the parent directory for the new documentation:

- `README.md` - Overview and quick start
- `USER_GUIDE.md` - For end users
- `ADMIN_GUIDE.md` - For administrators
- `DEVELOPER_GUIDE.md` - For developers
- `API_REFERENCE.md` - API documentation
- `SECURITY.md` - Security architecture
- `IMPLEMENTATION_REVIEW.md` - Code review and recommendations

## Archived Files

These files are preserved for historical reference:

### Setup and Planning
- `NEXT_STEPS.md` - Original next steps plan
- `NEXT_STEPS_PLAN.md` - Detailed planning
- `jbrowse2_integration_plan.md` - Integration planning
- `JBROWSE2_ASSEMBLY_STRATEGY.md` - Assembly strategy decisions

### Implementation Documentation
- `JBROWSE2_DYNAMIC_CONFIG.md` - Dynamic config implementation (→ now in DEVELOPER_GUIDE.md)
- `JBROWSE2_CONFIG.md` - Static config documentation (→ now in ADMIN_GUIDE.md)
- `JBROWSE2_MOOP_INTEGRATION.md` - Integration details (→ now in README.md)
- `jbrowse2_track_access_security.md` - Security details (→ now in SECURITY.md)

### Setup Guides
- `jbrowse2_SETUP.md` - Original setup guide
- `jbrowse2_SETUP_COMPLETE.md` - Setup completion notes
- `jbrowse2_GENOME_SETUP.md` - Genome setup guide (→ now in ADMIN_GUIDE.md)
- `ASSEMBLY_BULK_LOAD_GUIDE.md` - Bulk loading guide (→ now in ADMIN_GUIDE.md)

### Configuration Guides
- `jbrowse2_track_config_guide.md` - Track configuration (→ now in ADMIN_GUIDE.md)
- `jbrowse2_quick_reference.md` - Quick reference (→ now in USER_GUIDE.md)
- `jbrowse2_SYNC_STRATEGY.md` - Sync strategy
- `JBROWSE2_DOCS_INDEX.md` - Old documentation index

### Status and Results
- `IMPLEMENTATION_COMPLETE.md` - Implementation status
- `ASSEMBLY_TESTING_RESULTS.md` - Test results
- `jbrowse2_TEST_RESULTS.md` - More test results
- `HANDOFF_NEW_MACHINE.md` - Machine handoff notes
- `DELIVERABLES.txt` - Project deliverables list

### Miscellaneous
- `README_JBROWSE2.md` - Old README (→ now replaced by README.md)

## Why Consolidate?

### Problems with Old Structure

1. **Information Scattered** - Related information in multiple files
2. **Duplication** - Same information repeated in different files
3. **No Clear Entry Point** - Which file to read first?
4. **Hard to Maintain** - Updates needed in multiple places
5. **Confusing Naming** - `NEXT_STEPS.md` vs `NEXT_STEPS_PLAN.md`

### Benefits of New Structure

1. **Clear Hierarchy** - README → USER/ADMIN/DEVELOPER guides
2. **Role-Based** - Readers go straight to their guide
3. **No Duplication** - Each topic covered once
4. **Easy to Update** - Change in one place
5. **Better Navigation** - Clear links between documents

## Need Historical Information?

These archived files are still available and can be referenced if needed. However, the new documentation consolidates and updates all relevant information.

## Mapping: Old → New

| Old File | New Location |
|----------|--------------|
| `jbrowse2_quick_reference.md` | `USER_GUIDE.md` |
| `ASSEMBLY_BULK_LOAD_GUIDE.md` | `ADMIN_GUIDE.md` |
| `jbrowse2_GENOME_SETUP.md` | `ADMIN_GUIDE.md` |
| `jbrowse2_track_config_guide.md` | `ADMIN_GUIDE.md` |
| `JBROWSE2_DYNAMIC_CONFIG.md` | `DEVELOPER_GUIDE.md` |
| `JBROWSE2_CONFIG.md` | `DEVELOPER_GUIDE.md` |
| `jbrowse2_track_access_security.md` | `SECURITY.md` |
| `JBROWSE2_MOOP_INTEGRATION.md` | `README.md` + `DEVELOPER_GUIDE.md` |
| All others | Referenced where relevant |

## Questions?

If you can't find something from the old documentation in the new structure, check:

1. The relevant guide (USER/ADMIN/DEVELOPER)
2. The IMPLEMENTATION_REVIEW.md for recommendations
3. These archived files

---

**Note:** These files are preserved for reference only. For current documentation, use the guides in the parent directory.
