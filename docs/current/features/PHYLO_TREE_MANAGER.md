# Phylogenetic Tree Manager - Technical Notes

## Overview
The phylogenetic tree manager organizes organisms in MOOP into a hierarchical taxonomy structure based on their metadata. This structure is used for:
- Multi-organism search filtering and selection
- Displaying organisms in a visual tree hierarchy
- Organizing the organism selection interface

## Data Source
The tree is built from organism metadata stored in individual JSON files in the `organism_data/` directory. Each organism's metadata includes:
- `genus`: Organism genus
- `species`: Organism species  
- `taxon_id`: NCBI Taxonomy ID (required for automatic generation)
- `common_name`: Common name
- `assembly_version`: Assembly or version identifier
- Other metadata fields

Example organism.json:
```json
{
  "genus": "Drosophila",
  "species": "melanogaster",
  "taxon_id": "7227",
  "common_name": "Fruit Fly",
  "assembly_version": "R6.32",
  "data_source": "FlyBase"
}
```

## Configuration
The tree configuration is stored in:
```
metadata/taxonomy_tree_config.json
```

This JSON file contains the hierarchical structure that drives the tree display on the front end.

## Tree Structure and Format
The tree is hierarchically organized following standard taxonomic ranks (Kingdom → Phylum → Class → Order → Family → Genus → Species). 

JSON structure uses these properties:
- `label`: Display name for the node
- `expanded`: Whether the branch is expanded by default (true/false)
- `organism`: Marks this as a selectable organism (true for leaf nodes only)
- `name`: Internal identifier matching the organism database name
- `children`: Array of child nodes

Example JSON structure:
```json
{
  "label": "Root",
  "expanded": true,
  "children": [
    {
      "label": "Animalia",
      "expanded": true,
      "children": [
        {
          "label": "Insecta",
          "expanded": false,
          "children": [
            {
              "label": "Drosophila melanogaster",
              "organism": true,
              "name": "Drosophila_melanogaster"
            }
          ]
        }
      ]
    }
  ]
}
```

## Generation Process
The tree can be generated in two ways:

### Automatic Generation from NCBI
1. Admin navigates to **Manage Taxonomy Tree** tool
2. Clicks "Generate Tree from NCBI"
3. System reads all organism metadata files in `organism_data/`
4. For each organism, reads the `taxon_id` from `organism.json`
5. Queries NCBI Taxonomy API for complete lineage of each organism
6. Builds hierarchical tree by merging organisms that share taxonomic branches
7. Saves to `taxonomy_tree_config.json`
8. Displays confirmation message with number of organisms processed

### Manual Configuration
Admins can manually edit the JSON structure for custom hierarchies not based on standard taxonomy.

## NCBI Taxonomy Integration
The system integrates with NCBI taxonomy:
- Reads `taxon_id` from each organism's metadata file
- Queries NCBI Taxonomy API to retrieve complete lineage (Kingdom → Phylum → Class → Order → Family → Genus → Species)
- Builds hierarchical tree by merging organisms that share taxonomic branches
- Images stored in `images/ncbi_taxonomy/` directory

## Performance Considerations
- Approximately 350ms per organism for NCBI queries
- Large numbers of organisms (100+) may take several seconds to generate
- Tree generation is a one-time admin task (repeatable when organisms are added/modified)
- Generated tree is cached and reused for all searches
- No performance impact on searches after generation
- NCBI has rate limits (3 requests/second without API key)

## Common Issues & Solutions

**Tree not displaying on index page:**
- Verify `taxonomy_tree_config.json` exists in metadata directory
- Check file permissions (must be readable by web server)
- Verify JSON syntax is valid

**Tree not updating after adding organisms:**
- Regenerate the tree from the Manage Taxonomy Tree tool
- Tree is not automatically updated when organism metadata changes

**Permission errors when generating tree:**
- Check `metadata/` directory write permissions
- Run "Fix Permissions" tool in Manage Permissions
- Ensure web server process user has write access
- Ensure `images/ncbi_taxonomy/` directory exists and is writable

**Organisms not appearing in the tree:**
- Verify organism metadata files have valid `taxon_id` field
- Check that `taxon_id` matches valid NCBI Taxonomy ID
- Ensure organisms are loaded into the database
- Verify organism.json files are properly formatted JSON

**NCBI query failures during tree generation:**
- NCBI has rate limits (3 requests/second without API key)
- If you have many organisms, the process may time out
- Try regenerating again after a delay
- Verify all taxon_ids are valid on NCBI Taxonomy Browser

## File Structure
```
moop/
├── metadata/
│   └── taxonomy_tree_config.json        # Generated tree configuration
├── organism_data/
│   ├── genus_species_1/
│   │   └── organism.yaml               # Contains taxonomy metadata
│   └── genus_species_2/
│       └── organism.yaml
├── admin/
│   └── manage_taxonomy_tree.php         # Admin interface
└── tools/
    └── pages/
        └── help/
            └── taxonomy-tree-management.php  # User help documentation
```

## Integration Points
- **Index page (`index.php`)**: Displays tree for organism selection
- **Multi-organism search**: Uses tree for filtering organisms
- **Manage Taxonomy Tree tool**: Admin interface for generation/editing
- **Help system**: User documentation in `tools/pages/help/`

## Related Configuration
See `config/settings.yaml` for:
- `siteTitle`: Used in tree display
- `organism_data`: Path to organism metadata
- `metadata_path`: Location of taxonomy_tree_config.json
- `absolute_images_path`: Path to organism images/ncbi_taxonomy
