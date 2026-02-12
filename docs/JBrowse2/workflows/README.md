# JBrowse2 Workflows

Step-by-step workflows and examples for common tasks.

## Google Sheets Integration

- **[GOOGLE_SHEETS_WORKFLOW.md](GOOGLE_SHEETS_WORKFLOW.md)** - ⭐ Complete workflow for managing tracks via Google Sheets
- **[EXAMPLE_GOOGLE_SHEET.md](EXAMPLE_GOOGLE_SHEET.md)** - Google Sheet template and column specifications

## Workflow Overview

The Google Sheets integration allows you to manage all JBrowse2 tracks from a centralized spreadsheet:

1. **Prepare** - Organize your track files with full absolute paths
2. **Fill Sheet** - Add track metadata to Google Sheet
3. **Generate** - Run automation script to create tracks
4. **Verify** - Check tracks in JBrowse2

### Key Features

- ✅ Automatic assembly setup (downloads genome + annotations)
- ✅ No file copying (tracks used in-place)
- ✅ Multi-BigWig combo tracks
- ✅ Synteny track support
- ✅ Color group management
- ✅ Access control integration

### Requirements

- Track files must use **absolute paths**: `/data/moop/data/tracks/Organism/Assembly/type/file.ext`
- Reference genome and annotations can use `AUTO` keyword
- Remote URLs supported for all track types

## Quick Links

- [Back to Main Documentation](../README.md)
- [Admin Guide](../ADMIN_GUIDE.md)
- [Setup New Organism](../SETUP_NEW_ORGANISM.md)
