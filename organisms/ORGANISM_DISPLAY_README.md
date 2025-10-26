# Organism Display System

## Overview
The organism display system reads from `organism.json` files in each organism directory and generates formatted display pages.

## Files
- **`/tools/display/organism_display.php`** - Main display script that renders organism information
- **`/organisms/convert_organism_json.php`** - Utility script to convert organism.json to structured format

## Usage

### Viewing an Organism
Navigate to: `/tools/display/organism_display.php?organism=<organism_name>`

Example: `/tools/display/organism_display.php?organism=Lasiurus_cinereus`

Organisms are also linked from the group display pages.

### Converting JSON Format

The converter script helps migrate organism.json files from HTML format to structured format matching group descriptions.

**Convert a single organism:**
```bash
php /var/www/html/moop/organisms/convert_organism_json.php Lasiurus_cinereus
```

**Convert all organisms:**
```bash
php /var/www/html/moop/organisms/convert_organism_json.php --all
```

The script will:
1. Create a backup file (`.backup_YYYY-MM-DD_HHMMSS`)
2. Convert `text` field from HTML to `text_html.p` array
3. Convert `image` and `image_src` to `images` array format
4. Preserve old fields with `_old` suffix for reference

## JSON Format

### Current Format (Supported)
```json
{
  "genus": "Lasiurus",
  "species": "cinereus",
  "taxon_id": "257879",
  "common_name": "Hoary bat",
  "image": "Lasiurus_cinereus.jpg",
  "image_src": "image of a Hoary bat",
  "text": "<p>Diet: ...</p><p>Fun Fact: ...</p>",
  "genome_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.genome.fa",
  "protein_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.protein.aa.fa",
  "cds_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.cds.nt.fa",
  "transcript_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.transcript.nt.fa"
}
```

### Recommended Format (After Conversion)
```json
{
  "genus": "Lasiurus",
  "species": "cinereus",
  "taxon_id": "257879",
  "common_name": "Hoary bat",
  "images": [
    {
      "file": "Lasiurus_cinereus.jpg",
      "caption": "Image of a Hoary bat"
    }
  ],
  "text_html": {
    "p": [
      "Diet: Hoary bats feed primarily on moths...",
      "Fun Fact: In addition to their usual echolocation calls..."
    ]
  },
  "genome_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.genome.fa",
  "protein_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.protein.aa.fa",
  "cds_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.cds.nt.fa",
  "transcript_fasta": "Lasiurus_cinereus/Lasiurus_cinereus.transcript.nt.fa"
}
```

## Display Features

The organism display page includes:
- **Header Section**: Image, common name, scientific name, taxon ID
- **Description**: Formatted text about the organism
- **Resources**: Links to available genome data files
- Responsive Bootstrap 5 design
- Consistent styling with group pages
- Back navigation button

## Notes

- Images should be placed in `/moop/images/`
- The display script currently supports both old and new JSON formats
- After converting JSON files, you may want to update organism_display.php to use the structured format
- The converter keeps backups and old fields for safety
