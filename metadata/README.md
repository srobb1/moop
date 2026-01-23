# Metadata Directory

This directory contains configuration and metadata files that define the structure and organization of organisms, assemblies, groups, and permissions in MOOP.

## Configuration Files

### `taxonomy_tree_config.json`
Defines the phylogenetic/taxonomic tree structure used for organism navigation and searching:
- Hierarchical tree of life (Kingdom → Phylum → Class → Order → Family → Genus → Species)
- Each leaf node represents an organism with scientific name, common name, and image
- Used by the UI to display evolutionary relationships and enable tree-based organism selection
- Links organisms to the taxonomy hierarchy for search and filtering

**Structure:**
```json
{
    "tree": {
        "name": "Life",
        "children": [{
            "name": "Taxon_Name",
            "children": [...],
            "organism": "organism_identifier",
            "common_name": "Common Name",
            "image": "path/to/image.jpg"
        }]
    }
}
```

### `organism_assembly_groups.json`
Maps organisms to their genome assemblies and group memberships:
- Each organism can have one or more genome assemblies
- Each organism-assembly pair can belong to multiple user groups
- Controls which organisms are visible based on user group permissions
- Enables filtering organisms by group membership in the UI

**Structure:**
```json
[{
    "organism": "Scientific_name",
    "assembly": "Assembly_ID",
    "groups": ["Group1", "Group2", "Public"]
}]
```

### `annotation_config.json`
Defines all available annotation types and their properties:
- Annotation type names and display labels
- Source databases (Ensembl, RefSeq, NCBI, etc.)
- Whether annotations are searchable and viewable
- Display settings and metadata for each annotation type

### `group_descriptions.json`
Provides metadata for user groups:
- Group names and display labels
- Human-readable descriptions of group purposes
- Group-specific settings and access information

## Subdirectories

### `backups/`
Stores backup copies of configuration files:
- `annotation_config.json.backup_*` - Previous versions with timestamps
- `*_changes.log` - Historical change logs for organism/group updates
- Used for version control and recovery

### `change_log/`
Audit trail for configuration changes:
- `manage_groups.log` - Records of changes to group membership and descriptions
- Tracks who made changes and when for compliance and debugging

## How They Work Together

1. **organism_assembly_groups.json** defines which organisms exist and which groups they belong to
2. **taxonomy_tree_config.json** provides the hierarchical structure for displaying organisms in the UI tree
3. **annotation_config.json** defines what annotations are available for those organisms
4. **group_descriptions.json** provides metadata about the groups controlling access

When a user interacts with the application:
- The taxonomy tree is loaded for organism selection and navigation
- organism_assembly_groups.json filters organisms by user's group permissions
- annotation_config.json determines which annotation types are available for selected organisms
- Tools and searches are limited to organisms visible within the user's groups

## Editing Configuration

Configuration files should be edited through:
- **Web UI:** `/admin/manage_site_config.php` - Manage annotations and site configuration
- **Web UI:** `/admin/manage_groups.php` - Manage group memberships and organism assignments
- **Direct JSON editing:** Files can be edited directly if needed (changes auto-reload in most cases)

Always review `change_log/` to understand recent modifications before making manual changes.

## Notes

- Configuration files are loaded by the PHP application during initialization
- Path references are defined in `site_config.php` via the `$metadata_path` variable
- Ensure valid JSON syntax when editing files directly
- Previous versions are automatically backed up to `backups/` when modified through the admin UI
