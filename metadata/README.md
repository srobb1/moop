# Configuration Files - Metadata Directory

This directory contains JSON configuration files that define the application's data structure, display settings, and assembly mappings.

## Configuration Files

### `phylo_tree_config.json`
Defines the phylogenetic tree structure and layout configuration. Used to display evolutionary relationships between organisms in the phylogenetic tree view.

### `group_descriptions.json`
Contains descriptions and metadata for organism groupings. Provides display information for organizing and categorizing organisms by taxonomic or functional groups.

### `organism_assembly_groups.json`
Maps organisms to their available genome assemblies. Defines which assemblies are available for each organism and how they are organized in the UI.

### `annotation_config.json`
Specifies annotation types and their properties. Defines how different types of annotations (genes, transcripts, etc.) are configured and displayed in the annotation system.

## Backups

The `backups/` subdirectory contains:
- Previous versions of configuration files
- Historical change logs tracking modifications to configurations

These backups are preserved for version control and recovery purposes.

## Usage

Configuration files are loaded by the PHP application during initialization. Path references are defined in `site_config.php` via the `$metadata_path` variable.

To modify configurations:
1. Update the appropriate JSON file
2. Ensure valid JSON syntax
3. Back up the previous version to the `backups/` directory
4. Restart or reload the application cache
