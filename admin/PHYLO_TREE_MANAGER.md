# Phylogenetic Tree Manager

## Overview

The Phylogenetic Tree Manager (`manage_phylo_tree.php`) is an admin tool that automatically generates and manages the phylogenetic tree displayed on the MOOP homepage.

## How It Works

### Automatic Generation Method

1. **Reads Organism Metadata**
   - Scans all organism directories in `/organisms/` (symlinks to actual data)
   - Extracts `taxon_id` from each organism's `organism.json` file

2. **Fetches Taxonomic Lineage**
   - Queries NCBI Taxonomy Database API for each taxon_id
   - Retrieves complete taxonomic lineage: Kingdom → Phylum → Class → Order → Family → Genus → Species
   - Rate-limited to ~3 requests/second (NCBI requirement)

3. **Builds Hierarchical Tree**
   - Constructs nested JSON tree structure from lineage data
   - Merges organisms that share taxonomic branches
   - Preserves common names and organism identifiers

4. **Saves Configuration**
   - Writes to `/phylo_tree_config.json`
   - Used by `index.php` to render interactive tree view

### API Details

**NCBI E-utilities API Endpoint:**
```
https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=taxonomy&id={taxon_id}&retmode=xml
```

**Rate Limiting:**
- 3 requests per second without API key
- 10 requests per second with API key (not currently implemented)

**Taxonomic Ranks Extracted:**
- superkingdom (e.g., Eukaryota)
- kingdom (e.g., Metazoa/Animalia)
- phylum (e.g., Chordata, Cnidaria)
- class (e.g., Mammalia, Anthozoa)
- order (e.g., Chiroptera, Scleractinia)
- family (e.g., Phyllostomidae, Acroporidae)
- genus (e.g., Anoura, Montipora)
- species (e.g., Anoura caudifer, Montipora capitata)

## Usage

### Auto-Generate Tree

1. Navigate to **Admin Tools** → **Manage Phylogenetic Tree**
2. Review the list of detected organisms
3. Click **"Generate Tree from NCBI"**
4. Wait for generation to complete (~1 second per organism)

**Note:** This will overwrite the existing tree configuration.

### Manual Editing

After auto-generation, you can manually edit the JSON to:

1. **Remove taxonomic levels** - Simplify the tree by removing intermediate ranks
2. **Reorganize hierarchy** - Group organisms differently
3. **Add custom metadata** - Include additional display information

**Example: Simplifying the Tree**

Generated tree might include all levels:
```json
{
  "name": "Eukaryota",
  "children": [
    {
      "name": "Opisthokonta",
      "children": [
        {
          "name": "Metazoa",
          "children": [ ... ]
        }
      ]
    }
  ]
}
```

You can remove intermediate "clade" levels to show only standard ranks:
```json
{
  "name": "Animalia",
  "children": [
    {
      "name": "Chordata",
      "children": [ ... ]
    }
  ]
}
```

## Requirements

### Organism Setup

Each organism must have:
1. Directory in `/organisms/` (typically a symlink)
2. `organism.json` file with required fields:
   ```json
   {
     "genus": "Anoura",
     "species": "caudifer",
     "taxon_id": "27642",
     "common_name": "Tailed Tailless Bat"
   }
   ```

### Getting Taxon IDs

1. Search organism on [NCBI Taxonomy Browser](https://www.ncbi.nlm.nih.gov/taxonomy)
2. Copy the numeric taxon ID from the URL or page
3. Add to `organism.json`

**Example:**
- URL: `https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=27642`
- Taxon ID: `27642`

## Tree Configuration Format

The generated `phylo_tree_config.json` structure:

```json
{
  "tree": {
    "name": "Life",
    "children": [
      {
        "name": "Eukaryota",
        "children": [
          {
            "name": "Animalia",
            "children": [
              {
                "name": "Chordata",
                "children": [
                  {
                    "name": "Mammalia",
                    "children": [
                      {
                        "name": "Chiroptera",
                        "children": [
                          {
                            "name": "Phyllostomidae",
                            "children": [
                              {
                                "name": "Anoura",
                                "children": [
                                  {
                                    "name": "Anoura caudifer",
                                    "organism": "Anoura_caudifer",
                                    "common_name": "Tailed Tailless Bat"
                                  }
                                ]
                              }
                            ]
                          }
                        ]
                      }
                    ]
                  }
                ]
              }
            ]
          }
        ]
      }
    ]
  }
}
```

### Field Definitions

- **name**: Scientific name of the taxonomic level
- **children**: Array of child nodes (omit for leaf nodes)
- **organism**: Organism directory name (only on leaf nodes)
- **common_name**: Common/vernacular name (only on leaf nodes)

## Access Control Integration

The tree respects MOOP's access control system:

1. Admin users see all organisms in the tree
2. Regular users see only organisms they have access to
3. Public users see only public organisms
4. Inaccessible branches are automatically hidden

This filtering happens in `index.php` when rendering the tree, not in the configuration file.

## Troubleshooting

### "No organisms found"
- Check that organism directories exist in `/organisms/`
- Verify symlinks point to valid locations
- Ensure `organism.json` files exist in each directory

### "Failed to fetch from NCBI"
- Check internet connectivity
- Verify taxon_id is valid
- Wait and retry (NCBI may temporarily block rapid requests)

### "Invalid JSON"
- Use a JSON validator when manually editing
- Check for missing commas, brackets, or quotes
- Restore from backup if necessary

## Performance Notes

- Generation time: ~350ms per organism (NCBI rate limit)
- 3 organisms ≈ 1 second
- 10 organisms ≈ 3.5 seconds
- 50 organisms ≈ 17.5 seconds

Consider running generation during off-peak hours for large organism counts.

## Future Enhancements

Potential improvements:
- Cache taxonomy lookups to speed up regeneration
- Support NCBI API key for faster queries (10 req/sec)
- Batch API requests where possible
- Tree comparison/diff before overwriting
- Automatic regeneration when new organisms added
