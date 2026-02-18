# MOOP: Many Organisms One Platform
## Comprehensive System Overview

**Version:** 1.0  
**Last Updated:** January 2025

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [Core Concepts](#core-concepts)
4. [User Access & Permissions](#user-access--permissions)
5. [Data Organization](#data-organization)
6. [Search Functionality](#search-functionality)
7. [Annotation System](#annotation-system)
8. [Configuration Management System](#configuration-management-system)
9. [Source Selector System](#source-selector-system)
10. [Include System: Related Components](#include-system-related-components)
11. [Tools & Features](#tools--features)
12. [Centralized Function Library & Registry System](#centralized-function-library--registry-system)
13. [Administrative Management](#administrative-management)
14. [Dependencies & Requirements](#dependencies--requirements)
15. [Deployment Architecture](#deployment-architecture)

---

## Executive Summary

### Purpose

**MOOP** is a scalable, multi-organism genome annotation and analysis platform designed to:

- **Manage multiple genome assemblies** across different organisms with centralized access control
- **Enable comparative genomics research** by allowing simultaneous searches across organism groups
- **Provide fine-grained access control** supporting public, private, and collaborative research scenarios
- **Deliver fast, responsive searches** on large genomic datasets using SQLite databases
- **Support distributed research teams** with role-based access and IP-based authentication
- **Maintain data integrity** through comprehensive validation, error logging, and admin oversight

### Key Features

| Feature | Benefit |
|---------|---------|
| **Multi-organism support** | Add new organisms without restarting or restructuring |
| **Per-organism SQLite DBs** | Fast queries, easy backups, independent updates |
| **Group-based organization** | Group organisms by taxonomy, project, or research goal |
| **Fine-grained permissions** | Control access down to individual assemblies |
| **Phylogenetic tree** | Browse organisms by taxonomic relationships |
| **Comparative search** | Search across multiple organisms simultaneously |
| **Sequence extraction** | Download FASTA sequences by feature IDs |
| **BLAST integration** | Run BLAST searches against annotated sequences |
| **Admin tools** | Manage users, organisms, and system health |

---

## System Architecture

### High-Level Data Flow

```
┌──────────────────────────────────────────────────────────────┐
│ User Interface (Web Browser)                                 │
│ - HTML/CSS/Bootstrap for responsive design                   │
│ - JavaScript for interactive tables, searches, collapse      │
└──────────────────┬───────────────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────────────┐
│ Access Control Layer                                         │
│ - Session-based authentication                              │
│ - IP-based auto-login for internal networks                 │
│ - Role-based access (Admin, Collaborator, Visitor, ALL)     │
└──────────────────┬───────────────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────────────┐
│ Application Logic (PHP)                                      │
│ - Page controllers (organism.php, assembly.php, etc.)        │
│ - Search engines (multi-organism, BLAST, sequence search)    │
│ - Tool launchers (pre-load organism context)                 │
│ - Display rendering (layout, templates, exports)            │
└──────────────────┬───────────────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────────────┐
│ Data Access Layer                                            │
│ - Database queries with prepared statements                 │
│ - Permission validation on every access                      │
│ - Error handling and logging                                │
└──────────────────┬───────────────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────────────┐
│ Data Storage                                                 │
│ - SQLite DB per organism (genome data, annotations)          │
│ - FASTA files per assembly (sequences)                       │
│ - BLAST databases (indexed sequences)                        │
│ - JSON config files (metadata, permissions, tree)            │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ Support Systems (Parallel to main flow)                      │
│ - Error logging system                                      │
│ - Session management                                        │
│ - Configuration management                                  │
│ - Admin tools & dashboards                                  │
└──────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
/data/moop/                           # Application root
├── admin/                            # Admin tools (requires login)
│   ├── admin.php                     # Admin dashboard controller
│   ├── admin_init.php                # Admin initialization & setup
│   ├── admin_access_check.php        # Admin permission validation
│   ├── manage_organisms.php          # Organism management controller
│   ├── manage_users.php              # User management controller
│   ├── manage_site_config.php        # Site config management controller
│   ├── manage_annotations.php        # Annotation management controller
│   ├── manage_taxonomy_tree.php      # Phylogenetic tree management controller
│   ├── manage_groups.php             # Group management controller
│   ├── manage_error_log.php          # Error log viewer controller
│   ├── manage_filesystem_permissions.php # Permission management controller
│   ├── manage_registry.php           # Function registry management controller
│   ├── manage_js_registry.php        # JavaScript registry management controller
│   ├── organism_checklist.php        # Organism checklist controller
│   ├── registry-template.php         # Registry template helper
│   ├── pages/                        # Admin display templates (view layer)
│   │   ├── admin.php                # Admin dashboard/home UI
│   │   ├── manage_organisms.php     # Organism management UI (1422 lines)
│   │   ├── manage_users.php         # User account management UI
│   │   ├── manage_site_config.php   # Site configuration UI (50KB)
│   │   ├── manage_annotations.php   # Annotation source management UI
│   │   ├── manage_taxonomy_tree.php # Phylogenetic tree UI
│   │   ├── manage_groups.php        # Group organization UI
│   │   ├── manage_filesystem_permissions.php # Permission management UI
│   │   ├── manage_registry.php      # Function registry UI
│   │   ├── manage_js_registry.php   # JavaScript registry UI
│   │   ├── organism_checklist.php   # Organism setup checklist UI
│   │   └── error_log.php            # Error log viewer UI
│   ├── api/                          # Admin API endpoints (AJAX handlers)
│   └── backups/                      # Backup files directory
│
├── config/                           # Configuration files
│   ├── README.md                     # Configuration guide documentation
│   ├── site_config.php               # Site settings (paths, titles, constants)
│   ├── tools_config.php              # Tool-specific configuration
│   ├── config_editable.json          # Editable site configuration (JSON)
│   └── build_and_load_db/            # Database schema & load scripts
│       ├── create_schema_sqlite.sql  # SQLite database schema creation
│       ├── import_genes_sqlite.pl    # Perl script to import gene data
│       ├── load_annotations_fast.pl  # Perl script to load annotations
│       └── setup_new_db_and_load_data_fast_per_org.sh # Bash setup script
│
├── includes/                         # Shared includes
│   ├── README.md                     # Include system reference documentation
│   ├── layout.php                    # HTML template/layout wrapper
│   ├── ConfigManager.php             # Configuration manager class
│   ├── access_control.php            # Permission helpers & IP validation
│   ├── config_init.php               # Configuration initialization
│   ├── page-setup.php                # Page setup & initialization utilities
│   ├── head-resources.php            # HTML head resources (CSS, JS, fonts)
│   ├── banner.php                    # Site banner/header component
│   ├── navbar.php                    # Navigation bar component
│   ├── footer.php                    # Site footer component
│   ├── toolbar.php                   # Tool toolbar component
│   ├── source-list.php               # Annotation source list component
│   └── source-selector-helpers.php   # Source selection helper functions
│
├── tools/                            # User-facing tools
│   ├── README.md                     # (via DEVELOPER_GUIDE.md)
│   ├── DEVELOPER_GUIDE.md            # Tool development guide
│   ├── BLAST_TOOL_README.md          # BLAST tool documentation
│   ├── BLAST_QUICK_REFERENCE.md      # BLAST quick reference
│   ├── organism.php                  # Organism display controller
│   ├── assembly.php                  # Assembly display controller
│   ├── parent.php                    # Feature detail controller
│   ├── groups.php                    # Group browsing tool
│   ├── multi_organism.php            # Multi-organism search tool
│   ├── blast.php                     # BLAST search tool
│   ├── retrieve_sequences.php        # Sequence extraction by feature ID
│   ├── retrieve_selected_sequences.php # Selected sequences download
│   ├── sequences_display.php         # Sequences display component
│   ├── annotation_search_ajax.php    # AJAX annotation search
│   ├── tool_init.php                 # Tool initialization
│   ├── display-template.php          # Display template wrapper
│   ├── moop_functions.php            # Tool-specific helpers
│   ├── pages/                        # Display page templates
│   │  ├── organism.php              # Organism display
│   │  ├── assembly.php              # Assembly display
│   │  ├── parent.php                # Feature display
│   │  ├── groups.php                # Groups display
│   │  ├── multi_organism.php        # Search results display
│   │  ├── blast.php                 # BLAST results display
│   │  ├── index.php                 # Tool index/home
│   │  ├── login.php                 # Login page
│   │  ├── access_denied.php         # Access denied page
│   │  ├── retrieve_sequences.php    # Sequence download page
│   │  ├── retrieve_selected_sequences.php # Selected sequences page
│   │  └── [other page templates]    # Additional pages
│
├── lib/                              # Library functions (shared, centralized)
│   ├── blast_functions.php           # BLAST searching & database operations
│   ├── blast_results_visualizer.php  # BLAST results formatting
│   ├── common_functions.php          # Common/shared utility functions
│   ├── database_queries.php          # Database query operations
│   ├── display_functions.php         # Display rendering helpers (minimal)
│   ├── extract_search_helpers.php    # Sequence extraction & search
│   ├── fasta_download_handler.php    # FASTA download processing
│   ├── functions_access.php          # Access control & permissions
│   ├── functions_data.php            # Data retrieval & loading
│   ├── functions_database.php        # Database connection & helpers
│   ├── functions_display.php         # Display utilities & formatting
│   ├── functions_errorlog.php        # Error logging & handling
│   ├── functions_filesystem.php      # File system & path operations
│   ├── functions_json.php            # JSON loading & validation
│   ├── functions_system.php          # System utilities & cleanup
│   ├── functions_tools.php           # Tool support & context
│   ├── functions_validation.php      # Input validation & sanitization
│   ├── moop_functions.php            # General-purpose utilities
│   ├── parent_functions.php          # Feature/parent display helpers
│   ├── search_functions.php          # Search & query helpers
│   ├── tool_config.php               # Tool configuration
│   ├── tool_section.php              # Tool section rendering
│   ├── blast_functions.php.backup    # Backup of BLAST functions
│   └── README.md                     # Library documentation
│
├── js/                               # Client-side JavaScript
│   ├── README.md                     # JavaScript organization guide
│   ├── admin-utilities.js            # Admin page helper functions
│   ├── assembly-display.js           # Assembly page interactions
│   ├── blast-manager.js              # BLAST tool functions
│   ├── groups-display.js             # Groups page interactions
│   ├── index.js                      # Site index/home interactions
│   ├── manage-registry.js            # Function registry management
│   ├── multi-organism-search.js      # Multi-organism search tool
│   ├── organism-display.js           # Organism page interactions
│   ├── permission-manager.js         # Permission management
│   ├── registry.js                   # Registry functions (public)
│   ├── sequence-retrieval.js         # Sequence download tool
│   ├── modules/                      # Feature modules
│   │   ├── advanced-search-filter.js # Advanced search filtering
│   │   ├── annotation-search.js      # Annotation searching
│   │   ├── blast-canvas-graph.js     # BLAST result visualization
│   │   ├── collapse-handler.js       # Collapsible UI elements
│   │   ├── copy-to-clipboard.js      # Copy text to clipboard
│   │   ├── datatable-config.js       # DataTables configuration
│   │   ├── download-handler.js       # File download handling
│   │   ├── manage-annotations.js     # Annotation management UI
│   │   ├── manage-groups.js          # Group management UI
│   │   ├── manage-registry.js        # Registry management UI
│   │   ├── manage-site-config.js     # Site config management UI
│   │   ├── manage-taxonomy-tree.js   # Taxonomy tree management UI
│   │   ├── manage-users.js           # User management UI
│   │   ├── organism-management.js    # Organism management UI
│   │   ├── parent-tools.js           # Feature page tools
│   │   ├── shared-results-table.js   # Reusable results table
│   │   ├── source-list-manager.js    # Source/organism selector
│   │   ├── taxonomy-tree.js          # Phylogenetic tree display
│   │   └── utilities.js              # Shared utility functions
│
├── css/                              # Stylesheets
│   ├── README.md                     # CSS organization guide
│   ├── advanced-search-filter.css    # Search filter styling
│   ├── bootstrap.min.css             # Bootstrap framework
│   ├── datatables.css                # DataTables styling
│   ├── datatables.min.css            # DataTables (minified)
│   ├── display.css                   # Display page styling
│   ├── manage-filesystem-permissions.css # Permissions UI
│   ├── manage-groups.css             # Group management UI
│   ├── manage-site-config.css        # Site config UI
│   ├── manage-taxonomy-tree.css      # Taxonomy tree UI
│   ├── manage-users.css              # User management UI
│   ├── moop.css                      # Main stylesheet
│   ├── parent.css                    # Feature detail page
│   ├── registry.css                  # Registry UI
│   ├── retrieve-selected-sequences.css # Sequence download UI
│   └── search-controls.css           # Search controls styling
│
├── metadata/                         # Configuration data
│   ├── README.md                     # Metadata directory documentation
│   ├── annotation_config.json        # Annotation source definitions
│   ├── group_descriptions.json       # Group descriptions & metadata
│   ├── organism_assembly_groups.json # Org-Assembly-Group mapping
│   ├── taxonomy_tree_config.json     # Phylogenetic tree (auto-generated)
│   ├── backups/                      # Backup and changelog directory
│   │   └── *.backup_* files and logs
│   └── change_log/                   # Audit trail of config changes
│
├── organisms/                        # Organism data directories
│   ├── README.md                     # Organisms directory documentation
│   ├── Anoura_caudifer/              # Organism data directory
│   ├── Lasiurus_cinereus/            # Organism data directory
│   └── ...                           # Additional organisms
│
└── logs/                             # System logs
    ├── error_log.json                # Detailed error log
    └── access.log                    # Access tracking

/var/www/html/                        # Web root
├── users.json                        # User accounts & permissions
└── [symlink to /data/moop]           # Main application
```

### Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Frontend** | HTML5, CSS3, Bootstrap | 5.x |
| **UI Framework** | jQuery, DataTables | 1.13.4 |
| **Backend** | PHP | 7.4+ |
| **Database** | SQLite 3 | Per-organism |
| **Search** | BLAST | ncbi-blast+ |
| **Web Server** | Apache/Nginx | Latest |
| **Authentication** | bcrypt, Sessions | PHP native |

---

## Page Architecture: Controllers & View Templates

MOOP uses a **controller + view template pattern** for both admin and user-facing pages:

### Pattern Overview

```
User Request
    ↓
Controller (admin/manage_organisms.php or tools/organism.php)
├─ Process request
├─ Handle AJAX/form submissions  
├─ Validate permissions
├─ Query database
└─ Call view template
    ↓
View Template (admin/pages/manage_organisms.php or tools/pages/organism.php)
├─ Render HTML
├─ Display data
└─ Include layout (header, footer, etc.)
```

### Admin Pages

**Controllers** (root admin/*.php files):
- Handle business logic and data processing
- Process form submissions and AJAX requests
- Validate permissions and access
- Query database and manipulate data
- Call appropriate view template

**View Templates** (admin/pages/*.php files):
- Render HTML for the user interface
- Display data passed from controller
- Handle form inputs and buttons
- Wrapped with layout.php (header/footer)

**Example:**
```php
// admin/manage_organisms.php (controller)
- Handles organism creation/editing
- Processes form submissions
- Handles permission fixes
- Calls admin/pages/manage_organisms.php

// admin/pages/manage_organisms.php (view)
- Displays organism list
- Shows form for adding/editing
- Renders UI elements
- Included via layout.php
```

### User-Facing Pages (Tools)

**Controllers** (tools/*.php files):
- Display organism, assembly, or feature information
- Handle searches and filters
- Process downloads and exports
- Validate user access

**View Templates** (tools/pages/*.php files):
- Render organism/assembly/feature pages
- Display search results
- Show feature details and annotations

**Example:**
```php
// tools/organism.php (controller)
- Get organism data
- Get all assemblies
- Validate permissions
- Calls tools/pages/organism.php

// tools/pages/organism.php (view)
- Displays organism information
- Lists assemblies
- Shows feature search
- Included via layout.php
```

### Key Files

**Layout System** (includes/layout.php):
- Wraps all pages with header and footer
- Includes head resources (CSS, JS)
- Provides navbar, banner, footer
- Manages page structure

**Admin Pages** (15 controllers + 12 views):
- Controllers: manage_*.php, admin.php, organism_checklist.php
- Views: admin/pages/manage_*.php, admin/pages/organism_checklist.php
- Support files: admin_init.php, admin_access_check.php, registry-template.php

**User Pages** (controllers + views):
- Controllers: organism.php, assembly.php, parent.php, groups.php, multi_organism.php, blast.php, etc.
- Views: tools/pages/[corresponding files]

### Shared Libraries

All pages use shared functions from `/data/moop/lib/`:
- Database queries
- Permission checks
- Display helpers
- Validation functions
- File operations

---

## Core Concepts

### 1. Organisms vs. Assemblies vs. Features

#### Organism
- **Definition:** A biological species (e.g., *Homo sapiens*, *Anoura caudifer*)
- **Represents:** A conceptual unit of life
- **Storage:** One SQLite database per organism
- **Contains:** One or more assemblies
- **Configuration:** Defined in organism.json metadata

**Example:**
```
Organism: Anoura_caudifer
├─ Assembly: assembly_v1
├─ Assembly: GCA_004027475.1
└─ Assembly: GCA_004027476.1
```

#### Assembly
- **Definition:** A specific genome sequence build for an organism
- **Represents:** One version/version of an organism's genome
- **Storage:** FASTA files, BLAST databases
- **Contains:** One or more features (genes, mRNAs, exons)
- **Configuration:** Listed in organism_assembly_groups.json

**Example:**
```
Assembly: GCA_004027475.1
├─ Genome: reference_genome.fasta (2.3 GB)
├─ Proteins: proteins.faa (200 MB)
├─ BLAST DB: blast_nt (indexed)
└─ Annotations: 25,000 genes
```

#### Feature
- **Definition:** A specific genomic element (gene, mRNA, exon, protein domain)
- **Represents:** A localized, annotated sequence
- **Storage:** Record in organism SQLite database
- **Contains:** Annotations (homologs, orthologs, protein domains)
- **Identifier:** Unique ID within organism (uniquename)

**Example:**
```
Feature: GENE_12345
├─ Type: gene
├─ Uniquename: GENE_12345
├─ Name: Insulin gene
├─ Description: Insulin precursor
├─ Location: Chromosome 11, 2.5M-2.6M bp
└─ Children:
   ├─ mRNA_001 (transcript)
   └─ mRNA_002 (alternative spliceform)
```

### 2. Groups: Organization, Not Access Control

#### What Groups Are

Groups are **pure UI/organizational constructs** that help users find and browse assemblies. They are NOT permission boundaries.

**Key Principle:** An assembly can belong to zero, one, or many groups simultaneously.

#### Common Group Types

```
Organization-based:
- "Project_2024"        → All assemblies from 2024 project
- "Collab_University"   → Collaborative research partner
- "Lab_JohnSmith"       → Results from specific lab

Taxonomy-based:
- "Bats"                → All bat species
- "Primates"            → All primate species
- "Coral"               → All coral species

Data quality:
- "High_Quality"        → Verified, production-ready
- "Draft"               → Preliminary/testing
- "Deprecated"          → Older versions (still accessible)

Access groups:
- "Public"              → Publicly accessible (special: affects permissions)
- "Restricted"          → Team-only (UI label only; see users.json for real access)
```

#### Group Special Case: "Public"

The "Public" group is the **only group that affects access control:**

- If an assembly has `"Public"` in its groups → **Visitors can access without login**
- If an assembly lacks `"Public"` → **Only admins and explicit users can access**

All other groups are purely for UI organization.

#### Example: Multiple Groups, Single Assembly

```json
{
  "organism": "Lasiurus_cinereus",
  "assembly": "GCA_011751065.1",
  "groups": ["Bats", "High_Quality", "2024_Study", "Public"]
}
```

This assembly appears in:
- Bats group (UI filter)
- High_Quality group (UI filter)
- 2024_Study group (UI filter)
- Public group (UI filter + **accessible without login**)

### 3. One Organism, Multiple Groups

An organism can have multiple assemblies, each in different groups:

```json
[
  {
    "organism": "Homo_sapiens",
    "assembly": "GRCh38",
    "groups": ["Primates", "Reference_Genomes", "Public"]
  },
  {
    "organism": "Homo_sapiens",
    "assembly": "GRCh37",
    "groups": ["Primates", "Legacy", "Public"]
  },
  {
    "organism": "Homo_sapiens",
    "assembly": "Assemblathon_v1",
    "groups": ["Primates", "Research_Only", "Lab_ProjectX"]
  }
]
```

Result:
- GRCh38 appears in: Primates, Reference_Genomes, Public
- GRCh37 appears in: Primates, Legacy, Public
- Assemblathon_v1 appears in: Primates, Research_Only, Lab_ProjectX
- Users see all three under "Homo_sapiens" in organism dropdown
- Visitors can only access GRCh38 and GRCh37 (Public)
- Collaborator with explicit permission can access Assemblathon_v1

---

## User Access & Permissions

### User Types & Access Levels

MOOP recognizes four types of users with distinct access patterns:

#### 1. **VIEW ALL (IP-Based Auto-Login)**

```
Criteria:       Client IP in allowed range (defined in access_control.php)
Authentication: Automatic (no login required)
Data Access:    Everything (all organisms, all assemblies)
Admin Access:   NO
Session:        $_SESSION['access_level'] = 'ALL'
                $_SESSION['logged_in'] = true
```

**Use Case:** Internal research network, campus intranet

**Example Configuration:**
```php
// In access_control.php
$allowed_ip_ranges = [
    '192.168.1.0/24',      // Campus network
    '10.0.0.0/8'            // Internal network
];
```

**Behavior:**
- Automatic authentication on first page load
- No login required
- Can download from any assembly
- Cannot access admin tools (even though data access is universal)

#### 2. **Admin Users (Authenticated, Admin Role)**

```
Criteria:       Username/password login with "role": "admin" in users.json
Authentication: Login required
Data Access:    Everything (all organisms, all assemblies)
Admin Access:   YES
Session:        $_SESSION['access_level'] = 'Admin'
                $_SESSION['logged_in'] = true
                $_SESSION['role'] = 'admin'
```

**Use Case:** System administrators, lab managers, data curators

**Capabilities:**
- View and download from all assemblies
- Access `/admin/` tools
- Manage users and organisms
- View error logs
- Generate phylogenetic tree
- Manage groups and annotations

#### 3. **Collaborator Users (Authenticated, Specific Access)**

```
Criteria:       Username/password login with "access" object in users.json
Authentication: Login required
Data Access:    Specific assemblies listed in users.json
                + all "Public" group assemblies (bonus)
Admin Access:   NO
Session:        $_SESSION['access_level'] = 'Collaborator'
                $_SESSION['logged_in'] = true
                $_SESSION['access'] = {...permission mapping...}
                $_SESSION['role'] = null
```

**Use Case:** Research collaborators, external partners, team members

**Example users.json:**
```json
{
  "maria": {
    "password": "$2y$10$...",
    "access": {
      "Anoura_caudifer": ["assembly_v1", "GCA_004027475.1"],
      "Lasiurus_cinereus": ["GCA_011751065.1"],
      "Montipora_capitata": ["HIv3"]
    }
  }
}
```

**Maria can access:**
- Anoura_caudifer/assembly_v1 ✓
- Anoura_caudifer/GCA_004027475.1 ✓
- Lasiurus_cinereus/GCA_011751065.1 ✓
- Montipora_capitata/HIv3 ✓
- Any other assembly in "Public" group ✓
- Cannot access /admin/ tools ✗

#### 4. **Visitor Users (No Authentication)**

```
Criteria:       IP not in allowed range, not logged in
Authentication: None
Data Access:    Only assemblies in "Public" group
Admin Access:   NO
Session:        No special session variables
```

**Use Case:** General public, external researchers, data exploration

**Behavior:**
- See only public datasets
- No login required
- Can browse organism info
- Can download from public assemblies only
- Cannot access admin tools

### Permission Check Flow

#### For Data Access

```
User requests access to: Organism X / Assembly Y

1. Is user ALL (IP-based)?
   YES → ALLOW access
   NO  → Continue to step 2

2. Is user Admin?
   YES → ALLOW access
   NO  → Continue to step 3

3. Is Assembly Y in "Public" group?
   YES → ALLOW access
   NO  → Continue to step 4

4. Is user Collaborator with (X, Y) in $_SESSION['access']?
   YES → ALLOW access
   NO  → DENY access (redirect to login or access_denied)
```

#### For Admin Tools

```
User requests: /admin/* page

Check: Is user logged-in AND $_SESSION['role'] === 'admin'?
   YES → ALLOW access
   NO  → DENY access (redirect to access_denied.php)
```

**Key Security Note:** IP-based users (ALL) cannot access admin tools even with universal data access. They must be authenticated as admin to use admin tools.

### Permission Definition Files

#### /var/www/html/users.json

Defines user accounts and their specific assembly-level permissions.

```json
{
  "username": {
    "password": "bcrypt_hashed_password",
    "access": {
      "Organism_Name": ["assembly_1", "assembly_2"],
      "Another_Organism": ["assembly_x"]
    },
    "role": "admin"
  },
  "collaborator": {
    "password": "bcrypt_hashed_password",
    "access": {
      "Organism_Name": ["assembly_1"],
      "Another_Organism": ["assembly_x"]
    }
  },
  "admin_user": {
    "password": "bcrypt_hashed_password",
    "access": {},
    "role": "admin"
  }
}
```

**Rules:**
- `password`: Must be bcrypt hashed (not plaintext)
- `access`: Object mapping organisms to array of assembly names
- `role`: Optional; only include `"role": "admin"` for admin users
- Admin users can have empty `access` object (they access everything anyway)

#### /data/moop/metadata/organism_assembly_groups.json

Defines how assemblies are grouped (UI organization) and which are public.

```json
[
  {
    "organism": "Anoura_caudifer",
    "assembly": "GCA_004027475.1",
    "groups": ["Bats", "Lab_Project", "Public"]
  },
  {
    "organism": "Lasiurus_cinereus",
    "assembly": "GCA_011751065.1",
    "groups": ["Bats", "Research_Only"]
  },
  {
    "organism": "Montipora_capitata",
    "assembly": "HIv3",
    "groups": ["Coral", "Public"]
  }
]
```

**Rules:**
- Each entry = one organism + assembly combination
- `groups` array = list of UI categories this assembly belongs to
- "Public" in groups = publicly accessible (no login required)
- Other groups = purely organizational (don't affect permissions)

---

## Data Organization

### Database Schema Per Organism

Each organism has one SQLite database containing:

```
Table: organism
├─ organism_id          (PRIMARY KEY)
├─ genus                (genus name)
├─ species              (species name)
├─ subtype              (optional subspecies)
├─ common_name          (display name)
└─ taxon_id             (optional taxonomy ID)

Table: genome
├─ genome_id            (PRIMARY KEY)
├─ organism_id          (links to organism)
├─ genome_name          (assembly name)
├─ genome_accession     (assembly accession)
└─ genome_description   (description)

Table: feature
├─ feature_id           (PRIMARY KEY)
├─ feature_uniquename   (unique ID - UNIQUE constraint)
├─ feature_type         (gene, mRNA, exon, etc.)
├─ feature_name         (display name)
├─ feature_description  (text description)
├─ genome_id            (which assembly)
├─ organism_id          (which organism)
└─ parent_feature_id    (hierarchical - parent feature if applicable)

Table: annotation
├─ annotation_id        (PRIMARY KEY)
├─ annotation_accession (external ID: NM_123456)
├─ annotation_description (text from external source)
└─ annotation_source_id (links to source)

Table: feature_annotation
├─ feature_annotation_id (PRIMARY KEY)
├─ feature_id           (links to feature)
├─ annotation_id        (links to annotation)
├─ score                (e-value, bit score, confidence)
└─ date                 (when calculated)

Table: annotation_source
├─ annotation_source_id (PRIMARY KEY)
├─ annotation_source_name (NCBI, InterPro, UniProt, etc.)
├─ annotation_source_version (version info)
├─ annotation_accession_url (URL template for links)
├─ annotation_source_url (source website URL)
└─ annotation_type      (homolog, domain, ortholog, etc.)

```

### Organism Data Storage & Management

**Location:** `/data/moop/organisms/`

**Structure:**
```
organisms/
├─ Organism_Name_1/
│  └─ organism.sqlite          # SQLite database for this organism
├─ Organism_Name_2/
│  └─ organism.sqlite          # SQLite database for this organism
└─ [more organism directories...]
```

**Key Details:**

- **Per-Organism SQLite Database:** Each organism has its own `organism.sqlite` containing all metadata, features, annotations, and relationships for that organism
- **FASTA Files:** Patterns for organism/assembly FASTA files are configured in site configuration (`admin/manage_site_config.php`)
- **Management:** Organism and assembly status can be reviewed in the `admin/manage_organisms.php` page
- **Multiple Groups:** One organism can belong to multiple groups (e.g., "Primates" and "Mammals"). Group membership is managed in `admin/manage_groups.php`
- **Phylogenetic Organization:** Organisms are organized in a tree structure for taxonomic browsing and searches. The tree structure is managed in `admin/manage_taxonomy_tree.php`

**Organism Metadata Includes:**
- Common name and scientific name (stored in: `organism.sqlite` - `organism` table)
- Taxonomy: genus, species, family, order, etc. (stored in: `organism.sqlite` - `organism` table)
- Available assemblies and their statuses (stored in: `organism.sqlite` - `genome` table)
- Associated groups/membership (stored in: `metadata/organism_assembly_groups.json`)
- Public/private status (indicated via group membership; e.g., "Public" group indicates public organism)

**Related Files:**
- `organisms/[OrganismName]/organism.sqlite` - Per-organism database with all metadata, features, and annotations
- `metadata/organism_assembly_groups.json` - Maps organisms and assemblies to groups (public/private, categorization)
- `metadata/group_descriptions.json` - Group definitions and descriptions
- `metadata/taxonomy_tree_config.json` - Phylogenetic tree organization for navigation
- `admin/manage_organisms.php` - UI for managing organisms and assemblies
- `admin/manage_groups.php` - UI for managing group memberships
- `admin/manage_taxonomy_tree.php` - UI for managing phylogenetic tree structure

---

### File Organization Per Assembly

```
/data/moop/organisms/
└─ Organism_Name/
   └─ Assembly_Accession/                  (assembly name/accession as configured)
      ├─ genome.fa                         (reference genome nucleotides)
      ├─ transcript.nt.fa                  (mRNA/transcript sequences)
      ├─ cds.nt.fa                         (coding sequence nucleotides)
      ├─ protein.aa.fa                     (protein sequences)
      ├─ genome.fa.n*                      (BLAST database files for genome)
      ├─ transcript.nt.fa.n*               (BLAST database files for transcripts)
      ├─ cds.nt.fa.n*                      (BLAST database files for CDS)
      └─ protein.aa.fa.p*                  (BLAST database files for proteins)
```

**File Naming Conventions:**
- Sequence type patterns are configured in `config/config_editable.json` under `sequence_types`
- Current patterns: `genome.fa`, `transcript.nt.fa`, `cds.nt.fa`, `protein.aa.fa`
- BLAST database files (when present) are created using `makeblastdb` and are identified by file extensions:
  - Nucleotide databases: `.n*` extensions (nhr, nin, nsq, ndb, nog, nos, not, ntf, nto)
  - Protein databases: `.p*` extensions (phr, pin, psq, pdb, pot, ptf, pto)
- If BLAST database files are missing, helpful tips with `makeblastdb` commands are displayed to guide creation

**Configuration:**
- File patterns are NOT hardcoded and can be customized in `config/config_editable.json`
- Allows flexibility for different naming conventions across organisms

---

## Search Functionality

### Search Types

#### 1. Multi-Organism Organism-Level Search

**Purpose:** Browse and search across multiple organisms/assemblies simultaneously

**Flow:**
```
User selects organisms/assemblies
          ↓
User enters search term (feature ID, Gene ID, protein domain, gene name, gene description)
          ↓
System queries selected assemblies
          ↓
Results aggregated by organism
          ↓
Table with export options (CSV, Excel, FASTA)
```

**Example:**
- Select: Bats group (6 species, 12 assemblies)
- Search: "insulin"
- Results: Insulin gene from 4 bat species

#### 2. Assembly-Level Feature Search

**Purpose:** Search for features (genes, mRNAs) within a single assembly

**How it Works:**
```
User selects organism + assembly
          ↓
Search by:
  - Feature name (fuzzy match)
  - Feature ID (exact match)
  - Description text (substring)
          ↓
Results from SQLite database
          ↓
Click feature → detailed page with annotations
```

#### 3. Annotation-Level Search

**Purpose:** Find features with specific annotations (BLAST hits, protein domains, orthologs)

**How it Works:**
```
From feature detail page:
- Table of annotations displayed
- Search box searches: Annotation ID, Description, Score, Source
- Substring matching (not word-boundary sensitive)
- Real-time filtering as user types
```

**Example:**
- Feature: GENE_001
- Search: "Homo" → Shows all annotations mentioning Homo (Homo sapiens hits)

#### 4. BLAST Search

**Purpose:** Find homologous sequences in a target assembly

**How it Works:**
```
User uploads or pastes sequence
          ↓
Selects target organism + assembly
          ↓
System runs BLAST against indexed database
          ↓
Results with alignments, e-values, bit scores
          ↓
Links to feature pages for further analysis
```

#### 5. Sequence Search by Feature ID

**Purpose:** Find and download FASTA sequences by feature accessions

**How it Works:**
```
User enters feature IDs (comma-separated)
          ↓
Selects output format:
  - Full genome regions
  - CDS (coding sequence)
  - Proteins
  - Promoter regions
          ↓
System validates IDs and extracts sequences
          ↓
Downloads FASTA file
```

### Search Implementation: DataTables with Substring Matching

Annotation tables on feature pages use DataTables with **substring matching** search:

```javascript
// Search behavior:
// "Homo" → Finds "Ensembl Homo sapiens"
// "Ensembl Homo" → Finds "Ensembl Homo sapiens"
// Not word-boundary dependent

// Implementation:
// Tables marked with class="substring-search" use custom plugin
// Standard tables use DataTables default smart search
```

---

## Annotation System

### Annotation Types & Sources

Annotations are functional hits from computational analysis:

```
Annotation Types:
├─ Homologs (BLAST vs. nr/nt database)
│  └─ Similar sequences from other organisms
├─ Orthologs (OMA, EggNOG)
│  └─ Evolutionary related genes in other species
├─ Protein Domains (InterPro, Pfam)
│  └─ Conserved structural/functional domains
├─ Pathways (KEGG, Reactome)
│  └─ Metabolic/signaling pathway membership
└─ GO Terms (Gene Ontology)
   └─ Biological process, cellular component, molecular function
```

### Annotation Data Structure

```
Feature → [Has multiple annotations]

Example: GENE_12345 (insulin gene)
├─ Annotation 1
│  ├─ Accession: NP_000207.1
│  ├─ Description: Homo sapiens insulin
│  ├─ Source: BLAST/NCBI
│  ├─ Score: 1.2e-45 (e-value or classification)
│  ├─ Date: 2024-12-02 (when calculated)
│  └─ Link: https://www.ncbi.nlm.nih.gov/protein/NP_000207.1
│
├─ Annotation 2
│  ├─ Accession: IPR003236
│  ├─ Description: Insulin-like growth factor domain
│  ├─ Source: InterPro
│  ├─ Score: 1.5e-20
│  ├─ Date: 2024-12-02
│  └─ Link: https://www.ebi.ac.uk/interpro/entry/IPR003236
│
└─ Annotation 3
    ├─ Accession: GO:0005179
    ├─ Description: hormone activity
    ├─ Source: Gene Ontology
    ├─ Score: Ortholog (annotation classification)
    ├─ Date: 2024-12-02
    └─ Link: http://amigo.geneontology.org/amigo/term/GO:0005179
```


### Annotation Configuration

Defined in `/data/moop/metadata/annotation_config.json`:

```json
{
  "annotation_types": {
    "homolog": {
      "display_label": "Homologs (BLAST)",
      "description": "Similar sequences found using BLAST against NCBI nr/nt database",
      "color": "info",
      "order": 1,
      "enabled": true
    },
    "ortholog": {
      "display_label": "Orthologs",
      "description": "Evolutionary related genes from other species (OMA)",
      "color": "success",
      "order": 2,
      "enabled": true
    },
    "protein_domain": {
      "display_label": "Protein Domains",
      "description": "Conserved domains identified using InterProScan",
      "color": "warning",
      "order": 3,
      "enabled": true
    }
  }
}
```

### Annotation Display on Feature Pages

On each feature detail page, for each annotation type:

```
┌─ Annotation Type Header ────────────────────────────────┐
│ [Badge: Type]  [Badge: Count]  [Info]  [Filter Search]  │
│                                   🔗       "Search..."   │
├─────────────────────────────────────────────────────────┤
│ Table with columns:                                      │
│ - Annotation ID (linked to external resource)           │
│ - Description (text from source)                        │
│ - Score (e-value, bit score, or classification)        │
│ - Source (NCBI, InterPro, etc.)                         │
│                                                          │
│ Export options: CSV, Excel, PDF, Print                  │
├─────────────────────────────────────────────────────────┤
│ [Collapsible info: explanation of annotation type]      │
└─────────────────────────────────────────────────────────┘
```

**Components:**
- **Badge: Type** - Annotation type (e.g., "Homologs", "Orthologs")
- **Badge: Count** - Number of results for this type
- **Info Icon** - Toggles collapsible description below
- **Filter Search** - DataTables search box (real-time substring filtering)
- **Jump to Sequences** - Quick link to sequences section

---

## Configuration Management System

MOOP uses a dual-config system with static defaults and dynamic editable overrides:

### Configuration Files

#### site_config.php (Static Defaults)
**Location:** `/data/moop/config/site_config.php`

- Defines all default configuration values
- PHP file with hardcoded constants and arrays
- Never modified by users/admins
- Contains:
  - File paths (organism_data, metadata, logs, etc.)
  - Site title and branding defaults
  - Feature ID samples
  - BLAST sample sequences
  - IP whitelisting rules

**Purpose:** Single source of truth for system paths and default settings

#### config_editable.json (Runtime Overrides)
**Location:** `/data/moop/config/config_editable.json`

- JSON file with editable configuration values
- Can be modified through admin UI (manage_site_config.php)
- Only whitelisted keys can be edited (security)
- Contains only values that differ from defaults
- Overrides site_config.php values when present

**Editable Keys (Whitelisted):**
- `siteTitle` - Site display name
- `admin_email` - Admin contact email
- `sequence_types` - Available sequence output formats
- `header_img` - Header image settings
- `favicon_filename` - Favicon file
- `auto_login_ip_ranges` - IP ranges for auto-login
- `sample_feature_ids` - Example feature IDs for UI
- `blast_sample_sequences` - Example FASTA sequences

### How They Relate

```
ConfigManager (includes/ConfigManager.php)
    ↓
1. Load defaults from site_config.php
    ↓
2. Check if config_editable.json exists
    ↓
3. If exists, overlay editable values:
   For each whitelisted key:
   - If value in config_editable.json → Use it
   - Otherwise → Use default from site_config.php
    ↓
Final Configuration (merged result)
```

### Admin Management Interface

#### admin/manage_site_config.php
**Purpose:** Admin tool to edit configuration

**What It Does:**
1. **Loads current configuration** from ConfigManager
   - Shows defaults (site_config.php)
   - Shows current overrides (config_editable.json)
   
2. **Provides editable form fields**
   - Only for whitelisted keys
   - Other settings cannot be edited (secure)
   
3. **Saves changes**
   - Writes to config_editable.json only
   - Never modifies site_config.php
   - Uses ConfigManager::saveEditableConfig()

4. **Additional Features:**
   - Banner image upload/management
   - Settings validation
   - Changes take effect immediately

### Data Flow

```
Admin User
    ↓
Navigates to: /admin/manage_site_config.php
    ↓
admin/manage_site_config.php (controller)
├─ Loads current config via ConfigManager
├─ Validates admin permissions
└─ Calls admin/pages/manage_site_config.php (view)
    ↓
admin/pages/manage_site_config.php (view)
├─ Displays form with current values
├─ Shows editable fields only
└─ Includes file upload for banners
    ↓
User submits form
    ↓
Admin handles POST request
├─ Validates input
├─ Calls ConfigManager::saveEditableConfig()
└─ Writes to config_editable.json
    ↓
Next page load
    ↓
ConfigManager loads merged config:
├─ site_config.php (defaults)
└─ config_editable.json (overrides)
```

### Security & Best Practices

**Static Separation:**
- `site_config.php` - Never edited, version controlled, production defaults
- `config_editable.json` - Only edited via admin UI, user-customizable

**Whitelist System:**
- Only specific keys can be edited
- Prevents accidental modification of critical paths
- Defined in ConfigManager::$editableConfigKeys

**File Permissions:**
- `site_config.php` - Version controlled, read-only after deployment
- `config_editable.json` - Writable by web server (www-data)
- Allows user customization without code changes

**No Direct Edit:**
- Users should NOT manually edit these files
- All changes go through admin UI (manage_site_config.php)
- Ensures validation and consistency

---

## Source Selector System

MOOP uses a unified **source selector** component for tools that need to filter and select FASTA sources (genomes/assemblies):

### Usage in Tools

**Tools that use source selector:**
- `tools/retrieve_sequences.php` - Download FASTA by feature ID
- `tools/blast.php` - BLAST sequence search

### Components

#### source-selector-helpers.php
**Purpose:** Centralized logic for source selection and filtering

**Main Function:** `prepareSourceSelection()`
- Takes context parameters (organism, assembly, group)
- Builds filtered organism list based on context
- Determines auto-selection behavior
- Returns selection state for the UI

**Parameters:**
```php
prepareSourceSelection(
    $context,              // Parsed context: organism, assembly, group
    $sources_by_group,     // Nested sources organized by group
    $accessible_sources,   // Flat list of accessible sources
    $selected_organism,    // User's selected organism (POST/GET)
    $assembly_param,       // User's selected assembly (POST/GET)
    $organisms_param       // Multi-organism filtering (?organisms[])
);
```

**Returns:**
```php
[
    'filter_organisms' => [...],       // Organisms to show in dropdown
    'selected_source' => '...',        // Which radio button to select
    'selected_organism' => '...',      // Currently selected organism
    'selected_assembly_accession' => '...',  // Assembly ID
    'selected_assembly_name' => '...',       // Display name
    'should_auto_select' => true/false,      // Auto-select in JS?
    'context_group' => '...'           // Current group context
]
```

**Selection Rules:**
1. **Assembly specified** → Auto-select that assembly
2. **Organism specified** → Auto-select first assembly of that organism
3. **Group specified** → Show group, don't auto-select (user chooses)
4. **Multiple organisms** → Restrict list to those organisms only

#### source-list.php
**Purpose:** Renders the FASTA source selector HTML component

**What It Displays:**
- Organism/Assembly list with filtering
- Radio buttons to select source
- Search/filter box to find sources
- Color-coded by group
- Handles multi-level selection (organism → assembly)

**Required Variables:**
```php
$sources_by_group          // Array organized by group
$context_organism          // Current organism context
$context_assembly          // Current assembly context
$context_group             // Current group context
$selected_source           // Which source is selected
$selected_organism         // Selected organism name
$selected_assembly_accession // Selected assembly ID
$filter_organisms          // Which organisms to display
```

**Optional Callbacks:**
```php
$clear_filter_function     // JS function for "Clear" button
$on_change_function        // JS function on selection change
```

### Data Flow

```
Tool (retrieve_sequences.php or blast.php)
    ↓
Include source-selector-helpers.php
    ↓
Call prepareSourceSelection()
├─ Parse context (organism, assembly, group)
├─ Build filtered organism list
├─ Determine auto-selection
└─ Return selection state
    ↓
Include source-list.php
├─ Use selection state to render HTML
├─ Display organism/assembly list
├─ Show selected source
└─ Include source-list-manager.js for interactivity
    ↓
User selects source
    ↓
JavaScript handles selection
└─ Updates form and re-filters if needed
```

### Example: Sequence Extraction Tool

**User Context:**
- User browsing Bats group
- Views organism: Lasiurus_cinereus
- Clicks "Sequence Extraction Tool"

**Flow:**
```
tools/retrieve_sequences.php
    ↓
1. Parse context: organism=Lasiurus_cinereus
    ↓
2. Include source-selector-helpers.php
    ↓
3. Call prepareSourceSelection()
   - filter_organisms = [Lasiurus_cinereus]  (only this organism)
   - should_auto_select = true
   - selected_assembly = first assembly of Lasiurus
    ↓
4. Include source-list.php
   - Renders selector with Lasiurus assemblies only
   - First assembly pre-selected
   - User sees filtered list, doesn't have to re-select
    ↓
5. Tool works with pre-selected source
   - User can change if needed
   - Form remembers last selection
```

### JavaScript Integration

**JavaScript file:** `js/modules/source-list-manager.js`

**Functionality:**
- Handles organism dropdown changes
- Filters assembly list based on selected organism
- Updates radio button selection
- Auto-selects when page loads (if `should_auto_select = true`)
- Manages "Clear Filter" button

### Benefits

1. **Consistent UX** - Same source selector in all tools
2. **Context-Aware** - Pre-filters based on where user came from
3. **No Redundant Selection** - Auto-selects when obvious
4. **User Control** - Users can still change selection
5. **Code Reuse** - Single component used by multiple tools

---

## Include System: Related Components

MOOP's `/includes/` directory contains interconnected components that work together to provide page structure, configuration, and access control. Here's how they relate:

### Group 1: Page Structure & Layout System

**Files:** `layout.php`, `page-setup.php`, `footer.php`

**Purpose:** Provide the complete HTML page skeleton and rendering pipeline

**Architecture:**

```
Controller Page (e.g., tools/organism.php)
    ↓
1. Include config_init.php (setup config)
2. Include access_control.php (check permissions)
3. Prepare data ($title, $scripts, $styles, etc.)
4. Call render_display_page(content_file, $data, $title)
    ↓
render_display_page() function (in layout.php)
    ↓
Includes page-setup.php
    ├─ Opens: <!DOCTYPE>, <html>, <head>
    ├─ Includes head-resources.php
    ├─ Sets meta tags, CSS, fonts
    ├─ Opens <body>
    ├─ Includes navbar.php (header + toolbar)
    └─ Outputs opening HTML structure
        ↓
Then includes the content file (e.g., tools/pages/organism.php)
    ├─ Outputs page-specific content
    ├─ Uses $data passed from controller
    └─ Does NOT include any page structure tags
        ↓
Then includes footer.php
    ├─ Closes </body>
    ├─ Includes footer content
    └─ Closes </html>
```

**How They Work Together:**

- **layout.php**: Core rendering function `render_display_page()`
  - Orchestrates page rendering
  - Calls page-setup.php at start
  - Includes content file in middle
  - Calls footer.php at end

- **page-setup.php**: Opening HTML structure
  - Outputs: `<!DOCTYPE html>`, `<html>`, `<head>`
  - Includes head-resources.php
  - Opens `<body>` tag
  - Includes navbar.php
  - Must be paired with footer.php

- **footer.php**: Closing HTML structure
  - Outputs footer content
  - Closes `</body>` and `</html>`
  - Paired with page-setup.php

**Clean Separation:**
- Controllers load data and prepare variables
- Content files display data (no page structure)
- layout.php orchestrates (controller ↔ content file ↔ footer)
- Result: reusable content files, consistent page structure

---

### Group 2: Page Header Components

**Files:** `head-resources.php`, `navbar.php`, `banner.php`, `toolbar.php`

**Purpose:** Build the opening section of the page (header area)

**Architecture:**

```
page-setup.php opens the page
    ↓
Includes head-resources.php (in <head> tag)
    ├─ Meta tags (charset, viewport)
    ├─ CSS links (Bootstrap, MOOP styles)
    ├─ Favicon link
    ├─ Font links (Roboto, etc.)
    └─ Other <head> resources
        ↓
Includes navbar.php (right after <body> opens)
    ├─ Includes banner.php
    │  ├─ Gets banner images from config
    │  ├─ Rotates between banners
    │  ├─ Uses blurred background + sharp image
    │  └─ Falls back to default header_img
    └─ Includes toolbar.php
       ├─ Displays tool toolbar
       ├─ Shows current location/context
       └─ Provides navigation help
```

**How They Work Together:**

- **head-resources.php**: Assets for the browser
  - Meta tags tell browser how to render
  - CSS files style the page
  - Fonts and icons load early

- **navbar.php**: Main page header
  - Includes banner.php at top
  - Includes toolbar.php below banner
  - Combines visual header with functional toolbar

- **banner.php**: Visual header
  - Rotates banner images
  - Uses ConfigManager to get banner paths
  - Handles fallback to default image
  - Creates visual branding at page top

- **toolbar.php**: Functional navigation
  - Shows tool context
  - Provides quick navigation
  - Displays current user/session info

**Rendering Order:**
1. `head-resources.php` runs inside `<head>` (CSS, meta, fonts)
2. `page-setup.php` opens `<body>`
3. `navbar.php` runs (displays header)
4. `banner.php` displays rotating images (via navbar)
5. `toolbar.php` displays toolbar (via navbar)
6. Content file displays actual page content
7. `footer.php` closes page

---

### Group 3: Configuration & Access Control System

**Files:** `config_init.php`, `ConfigManager.php`, `access_control.php`

**Purpose:** Initialize configuration and validate user access

**Architecture:**

```
Every Page Load
    ↓
include config_init.php
    ├─ Requires ConfigManager.php class
    ├─ Initializes ConfigManager singleton
    ├─ Loads site_config.php (defaults)
    ├─ Loads config_editable.json (overrides)
    └─ Makes $config available globally
        ↓
include access_control.php
    ├─ Checks $_SESSION for authentication
    ├─ Validates user permissions
    ├─ Checks IP-based access
    ├─ Determines access level
    └─ Sets $_SESSION variables for page
        ↓
Page continues with:
    - $config available from ConfigManager::getInstance()
    - $_SESSION variables set for access control
    - User permissions validated
```

**How They Work Together:**

- **ConfigManager.php**: Manages all configuration
  - Singleton pattern (one instance per page)
  - Loads both static and editable config
  - Provides methods: `getPath()`, `getString()`, `getArray()`
  - Returns merged config (defaults + overrides)

- **config_init.php**: Initialize configuration
  - One-time setup per page
  - Creates ConfigManager singleton
  - Loads both config files
  - No user access control (that's separate)

- **access_control.php**: Validate user access
  - Checks if user is logged in
  - Validates permissions for page
  - Checks IP-based access
  - Separate from configuration (clean separation)

**Security Design:**
- Config loading and access control are separate
- Config doesn't touch $_SESSION (that's access_control's job)
- Access control doesn't load site paths (that's ConfigManager's job)
- If either fails, page can't render (safe failure)

---

### Group 4: Configuration Reference Files

**Files:** `config/README.md`

**Purpose:** Documentation for configuration system

**Contains:**
- How to edit site_config.php
- How to use config_editable.json
- Available configuration keys
- How ConfigManager merges configs
- Security best practices

**Used By:** Admins and developers setting up MOOP

---

## Tools & Features

### User-Facing Tools

#### Organism Display Tool
- **Purpose:** View all assemblies for an organism, browse features
- **Access:** Select organism → see all assemblies, feature count, statistics
- **Features:** Group filtering, assembly comparison

#### Assembly Display Tool
- **Purpose:** View features in specific assembly
- **Access:** Organism → Assembly → Feature list
- **Features:** Search features, filter by type, view statistics

#### Feature Detail Tool (Parent Page)
- **Purpose:** Detailed view of a feature with full annotation hierarchy
- **Access:** Click feature → detail page
- **Features:**
  - Feature hierarchy (parents, children, siblings)
  - Full annotation list with search
  - FASTA sequence extraction
  - Sequence downloads by format
  - Links to other tools

#### Sequence Extraction Tool
- **Purpose:** Download FASTA sequences by feature IDs
- **Access:** Enter feature IDs → select format → download
- **Formats:**
  - Full genome region
  - CDS (coding sequence)
  - Protein sequence
  - Promoter region (configurable)
  - UTRs (5' and 3')
- **Features:** Batch processing, multiple assemblies, format selection

#### BLAST Search Tool
- **Purpose:** Find homologous sequences in target assembly
- **Access:** Paste sequence → select target → run BLAST
- **Features:**
  - Sequence validation
  - Multiple algorithm support (BLASTN, BLASTP, BLASTX)
  - Configurable parameters (e-value threshold, word size, etc.)
  - Interactive results table
  - Feature linking

#### Multi-Organism Search
- **Purpose:** Search across multiple organisms/assemblies simultaneously
- **Access:** Select group or individual organisms → enter search term → view results
- **Features:**
  - Group-based pre-filtering
  - Aggregated results by organism
  - Batch export (CSV, Excel)
  - Filter and sort results
  - One-click navigation to features

### Tool Context Preloading

**Smart Feature:** Tools remember the organisms/assemblies you were viewing

```
User Flow:
1. Browse Organism view: Bats group (selected 6 organisms)
2. Click "Sequence Extraction Tool"
   ↓
3. Tool opens with Bats group pre-selected (not all organisms)
   ↓
4. User can still select different organisms if needed
```

**Implementation:**
```php
// When tool launches, system passes context:
$context = createToolContext('tool_name', [
    'organisms' => $selected_organisms,
    'assemblies' => $selected_assemblies,
    'search_term' => $current_search
]);

// Tool receives in URL parameters and pre-loads
```

**Benefit:** Faster workflow, less clicking, contextual awareness

---

## Centralized Function Library & Registry System

MOOP uses a centralized approach to managing functions across PHP and JavaScript to maintain consistency and reduce duplication.

### Philosophy

**Goal:** Single source of truth for reusable functions

- **Organize by category:** Functions grouped by purpose (data, display, validation, etc.)
- **Avoid duplication:** Shared functions in `/lib/` instead of duplicated across tools
- **Registry visibility:** Admin interface to discover available functions
- **Progressive enhancement:** Functions documented and tracked

### PHP Function Organization

All shared PHP functions are organized in `/data/moop/lib/`:

#### By Category

**Data & Retrieval:**
- `functions_data.php` - Load organisms, assemblies, groups
- `database_queries.php` - Database operations
- `functions_database.php` - Connection & helpers
- `functions_json.php` - JSON file handling

**Display & Rendering:**
- `functions_display.php` - Display utilities & formatting
- `display_functions.php` - Organism image/display logic
- `parent_functions.php` - Feature detail page helpers
- `tool_section.php` - Tool section rendering

**Searching & Analysis:**
- `search_functions.php` - Search & query helpers
- `blast_functions.php` - BLAST operations
- `blast_results_visualizer.php` - BLAST result formatting
- `extract_search_helpers.php` - Sequence extraction

**File & System:**
- `functions_filesystem.php` - File/path operations
- `functions_system.php` - System utilities & cleanup
- `fasta_download_handler.php` - FASTA download processing

**Security & Validation:**
- `functions_access.php` - Access control & permissions
- `functions_validation.php` - Input validation & sanitization
- `functions_errorlog.php` - Error logging & handling

**Utilities:**
- `moop_functions.php` - General utilities
- `common_functions.php` - Common/shared functions
- `tool_config.php` - Tool configuration
- `functions_tools.php` - Tool support & context

### PHP Function Registry

**Purpose:** Discover and manage PHP functions available in MOOP

**Location:** `/docs/function_registry.json` (auto-generated)

**Admin Interface:** `/admin/manage_registry.php`

**What's Tracked:**
- Function name
- File location
- Parameters
- Return value
- Description
- Usage examples
- Last updated

**Example Entry:**
```json
{
  "function_name": "getAccessibleOrganisms",
  "file": "lib/functions_data.php",
  "parameters": {
    "user_id": "string (optional, defaults to $_SESSION['user']['id'])"
  },
  "returns": "array of organisms user can access",
  "description": "Get list of organisms accessible by current user",
  "examples": [
    "$orgs = getAccessibleOrganisms();"
  ]
}
```

**Updating Registry:**

The registry is auto-generated by scanning `/lib/` PHP files for documented functions. To add a function:

1. Write function in appropriate `/lib/` file
2. Add documentation comment:
   ```php
   /**
    * Function description
    * 
    * @param type $param Description
    * @return type Description
    */
   function myFunction($param) { ... }
   ```
3. Generate registry via admin: "Manage > Function Registry" → "Generate Registry"
4. Registry updates automatically

### CSS File Organization

See dedicated documentation: `/css/README.md`

**Structure:**
- **Base stylesheet** - `moop.css` (always loaded, global styles)
- **Framework** - Bootstrap 5.3.2 (from CDN)
- **Page-specific CSS** - `manage-groups.css`, `parent.css`, etc. (conditional load)
- **Component CSS** - `advanced-search-filter.css`, etc. (loaded per feature)
- **Third-party** - DataTables, Font Awesome (from CDN)

**How to Include CSS:**

Include in controller via `page_styles` array in `display_config`:
```php
$display_config = [
    'title' => 'Manage Groups',
    'content_file' => __DIR__ . '/pages/manage_groups.php',
    'page_styles' => [
        '/' . $site . '/css/manage-groups.css'
    ],
    'page_script' => [
        '/' . $site . '/js/modules/manage-groups.js'
    ]
];
```

**CSS Load Order (in head):**
1. Bootstrap framework (CDN)
2. `moop.css` (base/global styles)
3. Page-specific CSS (overrides Bootstrap & moop)
4. DataTables CSS
5. Font Awesome icons

**Why this order:** Bootstrap first (baseline), moop overrides, page-specific overrides both.

**When to Create CSS File:**
- Page has unique layout/styling
- Styles exceed ~200 lines
- Feature used across multiple pages
- Complex selectors or media queries

**When to Use Inline Styles:**
- ✅ Single element, very simple (1-3 properties)
- ✅ Dynamic styling from PHP: `style="color: <?php echo $color; ?>"`
- ❌ Multiple elements (use class instead)
- ❌ More than 3 CSS properties (use file instead)

**Example: Acceptable inline styles**
```html
<div style="margin-top: 10px;">Content</div>
<span style="font-weight: bold;">Important</span>
<div style="display: none;" id="modal">Hidden content</div>
```

### Inline CSS and JavaScript Guidelines

**Philosophy:** Separate concerns - CSS in files for reusability, JS in files for maintainability, inline only for essential configuration/runtime values.

#### Inline CSS (✅ Acceptable in Limited Cases)

**ACCEPTABLE inline CSS:**
```html
<!-- Single element, 1-2 properties -->
<div style="margin-top: 10px;">Content</div>
<button style="display: none;" id="btn">Button</button>

<!-- Dynamic PHP values -->
<div style="width: <?php echo $width; ?>px; height: <?php echo $height; ?>px;">
    Dynamically sized container
</div>

<!-- Temporary overrides -->
<p style="color: red;">TODO: Fix this styling</p>
```

**NEVER inline CSS for:**
```html
<!-- ❌ Multiple elements with same styles -->
<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px;">Item 1</div>
<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px;">Item 2</div>
<!-- Use .card class instead -->

<!-- ❌ Complex responsive styling -->
<div style="display: flex; flex-wrap: wrap; gap: 20px; @media (max-width: 768px) { ... }">
<!-- Use CSS file instead -->

<!-- ❌ Animation or hover effects -->
<button style="transition: background 0.3s; :hover { background: blue; }">
<!-- Use CSS file instead -->

<!-- ❌ More than 3-4 properties -->
<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 10px; border-radius: 5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
<!-- Use CSS file instead -->
```

#### Inline JavaScript (⚠️ Very Limited Use)

**ACCEPTABLE inline JavaScript:**
```php
// Configuration variables from PHP
'inline_scripts' => [
    "const API_ENDPOINT = '/" . $site . "/api/';",
    "const currentUserId = " . $user_id . ";",
    "const isAdmin = " . ($is_admin ? 'true' : 'false') . ";"
]

// Runtime environment detection
<script>
    const isMobile = window.innerWidth < 768;
    const isTouch = 'ontouchstart' in window;
</script>

// Feature flags from config
<script>
    const hasBlastTools = <?php echo $config->hasBlastTools() ? 'true' : 'false'; ?>;
</script>
```

**NEVER inline JavaScript for:**
```php
// ❌ Event handlers
'inline_scripts' => [
    "
    jQuery(document).ready(function() {
        $('button').click(function() {
            // Logic here
        });
    });
    "
]
// Use js/modules/button-handler.js instead

// ❌ AJAX requests
'inline_scripts' => [
    "
    function fetchData() {
        $.ajax({
            url: '/api/data',
            success: function(data) { ... }
        });
    }
    "
]
// Use js/modules/data-fetcher.js instead

// ❌ DOM manipulation
'inline_scripts' => [
    "
    function buildTable(data) {
        var html = '';
        data.forEach(item => {
            html += '<tr><td>' + item.name + '</td></tr>';
        });
        $('#table').html(html);
    }
    "
]
// Use js/modules/table-builder.js instead

// ❌ Complex logic
'inline_scripts' => [
    "
    function validateForm(form) {
        // Lots of validation logic
    }
    "
]
// Use js/modules/form-validator.js instead
```

#### Decision Tree

**For CSS styling:**
```
Is it for ONE element only?
├─ Yes: Is it very simple (1-3 properties)?
│   ├─ Yes: Use inline style ✅
│   └─ No: Create CSS file ✅
└─ No: Create CSS file ✅
```

**For JavaScript:**
```
Is it PHP configuration that JavaScript needs?
├─ Yes: Use inline script ✅
│   Example: const userId = 123; const apiUrl = "/api/";
└─ No: Is it logic, events, or DOM manipulation?
    ├─ Yes: Create js/modules/ or js/page.js file ✅
    │   Example: AJAX, click handlers, form validation
    └─ No: Still create js file ✅
         (Keep JavaScript organized and reusable)
```

#### Best Practices

1. **CSS Priority:**
   - ✅ CSS files (reusable, maintainable)
   - ⚠️ Inline styles (only for single elements or dynamic values)
   - ❌ !important (avoid unless absolutely necessary)

2. **JavaScript Priority:**
   - ✅ JS modules in `/js/modules/` (shared logic)
   - ✅ Page-specific in `/js/page-name.js` (page logic)
   - ⚠️ Inline scripts (only config/environment variables)
   - ❌ Event handlers inline (always use files)

3. **Passing Data to JavaScript:**
   - ✅ Use inline scripts for config variables:
     ```php
     'inline_scripts' => ["const userId = " . $user_id . ";"]
     ```
   - ✅ Use data attributes for HTML-to-JS communication:
     ```html
     <button data-item-id="<?php echo $id; ?>">Delete</button>
     ```
   - ❌ Don't embed large data objects inline

4. **Dynamic CSS Values:**
   - ✅ Simple inline for single elements:
     ```html
     <div style="width: <?php echo $width; ?>px;">Content</div>
     ```
   - ⚠️ Multiple values might warrant CSS file:
     ```php
     <style>
     :root {
         --width: <?php echo $width; ?>px;
         --height: <?php echo $height; ?>px;
     }
     </style>
     ```
   - ❌ Don't inline huge blocks of CSS

#### Examples

**Good:**
```php
// Controller
$display_config = [
    'page_styles' => [
        '/' . $site . '/css/manage-groups.css'
    ],
    'page_script' => [
        '/' . $site . '/js/modules/manage-groups.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';"  // Only config
    ]
];
```

```css
/* css/manage-groups.css */
.group-card {
    background: #f5f5f5;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    border-radius: 5px;
}

.group-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
```

```js
// js/modules/manage-groups.js
jQuery(document).ready(function() {
    initializeGroupManager();
});

function initializeGroupManager() {
    $('.group-card').on('click', function() {
        const groupId = $(this).data('group-id');
        loadGroupDetails(groupId);
    });
}
```

**Bad:**
```php
// ❌ Inline CSS for reusable card
echo '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';

// ❌ Inline JavaScript for event handler
'inline_scripts' => [
    "
    jQuery(document).ready(function() {
        $('.group-card').click(function() {
            const id = $(this).data('id');
            $.ajax({url: '/api/groups/' + id, ...});
        });
    });
    "
]
```

---

### JavaScript File Organization

See dedicated documentation: `/js/README.md`

**Structure:**
- **Root level files** (11 files) - Page-specific scripts loaded inline in controllers
- **modules/ directory** (19 files) - Shared feature modules used across multiple pages
- **CDN URLs** - Third-party libraries (Bootstrap, jQuery, jszip) loaded from external CDNs

**Root-level files (page-specific):**
- `admin-utilities.js`, `assembly-display.js`, `blast-manager.js`
- `groups-display.js`, `index.js`, `multi-organism-search.js`
- `organism-display.js`, `permission-manager.js`, `registry.js`
- `sequence-retrieval.js`, `manage-registry.js`

**Shared modules (multiple pages):**
- UI components: `datatable-config.js`, `collapse-handler.js`, `copy-to-clipboard.js`
- Search/filtering: `advanced-search-filter.js`, `annotation-search.js`
- Management UIs: `manage-annotations.js`, `manage-groups.js`, `manage-registry.js`
- `manage-site-config.js`, `manage-taxonomy-tree.js`, `manage-users.js`
- `organism-management.js`, `parent-tools.js`, `shared-results-table.js`
- `source-list-manager.js`, `taxonomy-tree.js`, `utilities.js`
- Visualization: `blast-canvas-graph.js`
- File handling: `download-handler.js`

**Including JavaScript:**

1. **Root files** - Loaded inline in PHP controller via `page_script` config:
   ```php
   $display_config = [
       'page_script' => ['/' . $site . '/js/blast-manager.js']
   ];
   ```

2. **Modules** - Included when multiple pages need same functionality:
   ```php
   $display_config = [
       'page_script' => [
           '/' . $site . '/js/modules/datatable-config.js',    // Modules first
           '/' . $site . '/js/organism-display.js'              // Then page script
       ]
   ];
   ```

3. **CDN libraries** - Third-party loaded from external CDN in head-resources.php:
   ```php
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"></script>
   <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
   ```

**Why two levels (root + modules)?**
- **Performance:** Load only code needed per page (smaller pages = faster load)
- **Reusability:** modules/ shared across pages = single source of truth
- **Maintenance:** Fix bug once in module, all pages get fix

### JavaScript Function Registry

**Purpose:** Discover and manage shared JavaScript functions available in MOOP

**Scope:** Tracks shared functions in `/js/modules/` directory only
- Root-level page scripts are not indexed (page-specific)
- Only shared, reusable module functions are tracked

**Location:** `/docs/js_function_registry.json` (auto-generated)

**Admin Interface:** `/admin/manage_js_registry.php`

**What's Tracked:**
- Function name
- File location (in modules/)
- Parameters with types
- Return value with type
- Description
- Usage examples
- Last updated timestamp

**Example Entry:**
```json
{
  "function_name": "initDataTable",
  "file": "js/modules/datatable-config.js",
  "parameters": {
    "tableId": "string - element ID of table",
    "options": "object - DataTables configuration options (optional)"
  },
  "returns": "DataTable instance",
  "description": "Initialize DataTables with MOOP defaults and custom options",
  "examples": [
    "const dt = initDataTable('#myTable');",
    "const dt = initDataTable('#results', {paging: false});"
  ],
  "last_updated": "2026-01-15T10:30:00Z"
}
```

**Adding Shared JavaScript Functions:**

1. Determine if function is shared (used by 2+ pages)
   - If shared → create in `js/modules/`
   - If page-specific → create in root `js/` as page script

2. Write function in appropriate `js/modules/` file with JSDoc comment:
   ```js
   /**
    * Initialize DataTables with MOOP defaults
    * 
    * @param {string} tableId - Element ID of the table
    * @param {object} options - DataTables options (optional)
    * @returns {DataTable} DataTable instance
    * @example
    * const dt = initDataTable('#myTable', {paging: false});
    */
   function initDataTable(tableId, options = {}) {
       // Implementation
   }
   ```

3. Generate registry via admin: "Manage > JS Registry" → "Generate Registry"
4. Registry auto-updates with new functions

### Benefits of Centralization

1. **Discoverability** - Find existing functions via admin interface
2. **Consistency** - All tools use same implementation
3. **Maintainability** - Fix bug once, all tools get fix
4. **Reusability** - Developers know where to find functions
5. **Documentation** - Registry serves as API documentation
6. **Quality** - Reduces duplicate/conflicting implementations
7. **Audit Trail** - Track when functions were last updated

### Common Patterns

**Finding a function:**
1. Login as admin
2. Navigate to "Manage > Function Registry"
3. Search for function name or keyword
4. View file location and parameters
5. Click to see full documentation

**Adding a function to lib:**
1. Determine category (data? display? validation?)
2. Add to appropriate `functions_*.php` file
3. Document with PHPDoc comments
4. Include in your tool: `include_once __DIR__ . '/../lib/functions_category.php';`
5. Regenerate registry via admin

**Using a function from lib:**
1. Determine which file it's in (check registry)
2. Include that file at top of your script
3. Call the function
4. Example:
   ```php
   include_once __DIR__ . '/../lib/functions_data.php';
   $organisms = getAccessibleOrganisms();
   ```

### Registry Management (Admin Only)

**Regenerate PHP Registry:**
- Admin → Manage → Function Registry → "Regenerate Registry"
- Scans all `/lib/*.php` files
- Extracts function names, parameters, documentation
- Updates `/docs/function_registry.json`

**Regenerate JS Registry:**
- Admin → Manage → JS Registry → "Regenerate Registry"
- Scans `/js/modules/*.js` files (shared modules only)
- **Does NOT scan** root-level `/js/*.js` files (those are page-specific)
- Extracts function names, parameters, JSDoc documentation
- Updates `/docs/js_function_registry.json`

**Registry Staleness:**
- System detects when PHP files updated since last registry generation
- Warns admin registry may be out of date
- Auto-suggests regeneration

**Adding a function to JavaScript modules:**
1. Determine if shared (used by 2+ pages)
2. If shared: Create in `js/modules/feature-name.js`
   - If page-specific: Create in root `js/page-name.js`
3. Document with JSDoc comments (see example above)
4. Include in controller via `page_script` config:
   ```php
   $display_config = [
       'page_script' => [
           '/' . $site . '/js/modules/feature-name.js',   // Modules first
           '/' . $site . '/js/page-specific.js'            // Page script last
       ]
   ];
   ```
5. For shared modules: Regenerate registry via admin

**Using a function from js/modules:**
1. Check if function exists in registry or module file
2. Include module in controller `page_script` array (modules must load before calling code)
3. Call the function in your page-specific script
4. Example:
   ```js
   // Module loads first (datatable-config.js)
   // Then page-specific script can call:
   const table = initDataTable('#myTable', {paging: false});
   ```

**For detailed JavaScript organization guidelines:**
- See `/js/README.md` for complete documentation
- Explains when to use root vs modules
- Decision tree for where to put new JS
- Best practices and naming conventions

---

## Administrative Management

### Admin Dashboard

**Location:** `/admin/index.php` (requires login as admin)

**Available Functions:**
1. **Organism Management** - Add/edit organisms, configure metadata
2. **User Management** - Create/modify user accounts, set permissions
3. **Annotation Sources** - View/configure annotation types
4. **Phylogenetic Tree** - Auto-generate or manually edit
5. **Error Log Viewer** - Inspect system errors for debugging
6. **Group Management** - Organize assemblies into groups

### Adding a New Organism

```
Manual process:
1. Prepare organism data directory with FASTA files
2. Create organism.json with metadata (taxon_id, feature_types, etc.)
3. Create SQLite database with schema (features, annotations, etc.)
4. Create symlink in /organisms/ → actual data directory
5. Add entries to organism_assembly_groups.json
6. Update users.json if restricted access needed
7. (Optional) Regenerate phylogenetic tree
```

**Result:** New organism immediately available for search and download

### Phylogenetic Tree Generation

**Auto-Generate Method:**
```
1. System reads all organism directories
2. Extracts taxon_id from each organism.json
3. Queries NCBI Taxonomy API for each taxon
4. Retrieves complete lineage (Kingdom → Species)
5. Builds nested JSON tree structure
6. Saves to taxonomy_tree_config.json
7. Homepage displays interactive tree

Process time: ~1-3 seconds per organism
Rate limited: 3 requests/second (NCBI requirement)
```

**Manual Edit:**
- After auto-generation, admins can manually edit JSON
- Remove intermediate ranks to simplify tree
- Reorganize hierarchy as needed
- Add custom metadata

**Usage:**
- Homepage shows interactive tree
- Click taxon → filters organisms
- Drill down: Phylum → Class → Order → Family → Genus → Species

### Error Logging & Debugging

**Error Log Viewer** (`/admin/error_log.php`):
- View all system errors with timestamps
- Search and filter errors
- Export error logs
- Clear logs (with confirmation)
- Error details include: context, error message, user info, stack trace

**Logged Events:**
- Database access failures
- Permission denials
- Invalid input/validation errors
- File system errors
- API failures (NCBI, BLAST)
- Session issues
- Authentication failures

---

## Dependencies & Requirements

### System Requirements

```
Server:
  - Apache 2.4+ OR Nginx
  - PHP 7.4 or higher
  - SQLite 3
  - Linux/Unix (not tested on Windows)

Tools:
  - ncbi-blast+ 2.9+
  - blastdbcmd (BLAST utility)

Libraries & Extensions:
  - PHP: sqlite3, curl, json, session
  - JavaScript: jQuery 3.6+, DataTables 1.13+, Bootstrap 5+

Data Storage:
  - Disk space: ~500 GB+ (varies by organisms)
  - Fast storage recommended for BLAST databases

Network:
  - Internet access for: NCBI Taxonomy API (optional but recommended)
  - SMTP for email features (if configured)
```

### Third-Party JavaScript Libraries

```
Frontend JavaScript:
- jQuery 3.6.0             (DOM manipulation, AJAX)
- DataTables 1.13.4        (Interactive tables, export)
- Bootstrap 5.3+           (UI framework, responsive)
- Font Awesome 6+          (Icons)
- JSTree (optional)        (Hierarchical tree display)
- Chart.js (optional)      (Data visualization)

AJAX Libraries:
- Fetch API (modern, built-in)
- jQuery.ajax (fallback)

Export Formats:
- CSV/Excel (DataTables Buttons extension)
- PDF/Print (Browser native + DataTables)
- FASTA (custom implementation)
```

### PHP Dependencies

```
Core:
- PDO (database abstraction)
- SPL (standard library)
- Reflection (for introspection)

File I/O:
- file_get_contents / file_put_contents
- scandir / glob
- fopen / fread

Cryptography:
- password_hash / password_verify (bcrypt)

JSON:
- json_encode / json_decode

Session:
- session_start() / $_SESSION

Security:
- htmlspecialchars (XSS prevention)
- filter_var (input validation)
- preg_match (regex validation)
```

---

## Deployment Architecture

### Single-Server Deployment

```
┌─────────────────────────────────────┐
│ Web Server (Apache/Nginx)           │
├─────────────────────────────────────┤
│ PHP Application (/data/moop/)       │
│ - Web root: /var/www/html → symlink │
│ - config/site_config.php            │
│ - includes/ (shared code)           │
│ - tools/ (user pages)               │
│ - admin/ (admin pages)              │
├─────────────────────────────────────┤
│ Data Storage                        │
│ - /data/moop/metadata/              │
│ - /organism_data/ (symlinks)        │
│ - SQLite databases (per organism)   │
│ - FASTA files (per assembly)        │
│ - BLAST databases (indexed)         │
├─────────────────────────────────────┤
│ System Logs                         │
│ - error_log.json                    │
│ - access.log                        │
│ - PHP error_log                     │
├─────────────────────────────────────┤
│ User Permissions                    │
│ - /var/www/html/users.json          │
│ - organism_assembly_groups.json     │
└─────────────────────────────────────┘
```

### Multi-Organism Database Layout

```
/organism_data/
├── Anoura_caudifer/
│   ├── organism.sqlite               (SQLite database - features, annotations)
│   ├── assembly_v1/
│   │   ├── reference_genome.fasta
│   │   ├── blast_nt/ [BLAST indices]
│   │   └── proteins.faa
│   └── GCA_004027475.1/
│       ├── reference_genome.fasta
│       ├── blast_nt/ [BLAST indices]
│       └── proteins.faa
│
├── Lasiurus_cinereus/
│   ├── organism.sqlite
│   ├── GCA_011751065.1/
│   │   ├── reference_genome.fasta
│   │   ├── blast_nt/ [BLAST indices]
│   │   └── proteins.faa
│   └── assembly_v2/
│       ├── reference_genome.fasta
│       ├── blast_nt/ [BLAST indices]
│       └── proteins.faa
│
└── [More organisms...]

Symlinks in /moop/organisms/:
├── Anoura_caudifer → /organism_data/Anoura_caudifer
├── Lasiurus_cinereus → /organism_data/Lasiurus_cinereus
└── [etc.]
```

### Access Control Implementation

**IP-Based Access:**
```
Allowed IP Ranges (configured in access_control.php):
- 192.168.1.0/24      → Internal network
- 10.0.0.0/8          → Corporate network
- 203.0.113.10        → Specific server

User from 192.168.1.50 accesses site:
├─ IP check: 192.168.1.50 in 192.168.1.0/24? YES
├─ Set: $_SESSION['access_level'] = 'ALL'
├─ Automatic authentication (no login)
├─ Can access all data
└─ Cannot access /admin/ (requires login as admin)
```

**User-Based Access:**
```
Authentication Chain:
1. Check if session exists + not expired
2. If not: show login form
3. User enters username/password
4. bcrypt verify against users.json
5. If valid:
   - Load user's access object
   - Set session variables
   - Redirect to requested page
6. If invalid:
   - Log failed attempt
   - Show error
   - Prevent brute force (consider rate limiting)
```

---

## Summary

| Component | Purpose | Technology |
|-----------|---------|-----------|
| **Organisms** | Conceptual unit of life | Metadata + SQLite DB |
| **Assemblies** | Specific genome builds | FASTA + BLAST indices |
| **Features** | Genomic elements (genes, exons) | SQLite database records |
| **Groups** | UI organization & access control | JSON configuration |
| **Annotations** | Functional hits from computation | SQLite database + external links |
| **Permissions** | Access control | users.json + IP whitelist |
| **Tools** | User functionality | PHP + JavaScript |
| **Admin Panel** | System management | PHP + Bootstrap UI |
| **Phylogenetic Tree** | Taxonomy visualization | NCBI API + JSON tree |

### Key Design Principles

1. **Scalability:** One database per organism enables independent updates
2. **Security:** Assembly-level permissions with multiple fallbacks
3. **Flexibility:** Groups for any organizational need
4. **Usability:** Context-aware tool launching, pre-filled searches
5. **Maintainability:** Centralized configuration, clear separation of concerns
6. **Transparency:** Comprehensive error logging, admin visibility

### Quick Reference: Permission Hierarchy

```
ANY user tries to access Assembly X:

1. Is IP-based user (ALL)?        → ALLOW
2. Is admin user?                  → ALLOW
3. Is assembly in Public group?    → ALLOW
4. Is collaborator with permission? → ALLOW
5. Otherwise                       → DENY
```

### Quick Reference: User File Locations

```
Users:                   /var/www/html/users.json
Assembly Groups:        /data/moop/metadata/organism_assembly_groups.json
Annotation Config:      /data/moop/metadata/annotation_config.json
Phylogenetic Tree:      /data/moop/metadata/taxonomy_tree_config.json
Organisms Data:         /organism_data/ (symlinked from real data directory)
Application Code:       /data/moop/
Web Root:              /var/www/html/
```

---

**End of Document**

For detailed technical implementation, see:
- `/data/moop/notes/SECURITY_IMPLEMENTATION.md` - Security architecture
- `/data/moop/notes/PERMISSIONS_WORKFLOW.md` - Permission details
- `/data/moop/notes/CONFIG_ADMIN_GUIDE.md` - Configuration guide
- `/data/moop/tools/DEVELOPER_GUIDE.md` - Developer documentation
