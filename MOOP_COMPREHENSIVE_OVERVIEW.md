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
8. [Tools & Features](#tools--features)
9. [Administrative Management](#administrative-management)
10. [Dependencies & Requirements](#dependencies--requirements)
11. [Deployment Architecture](#deployment-architecture)

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
│   ├── admin.php                     # Admin dashboard landing page
│   ├── admin_init.php                # Admin initialization & setup
│   ├── admin_access_check.php        # Admin permission validation
│   ├── manage_organisms.php          # Add/edit organisms
│   ├── manage_users.php              # User account management
│   ├── manage_site_config.php        # Site-wide configuration settings
│   ├── manage_annotations.php        # Manage annotation sources & types
│   ├── manage_taxonomy_tree.php      # Generate/edit phylogenetic tree
│   ├── manage_groups.php             # Organize assemblies into groups
│   ├── manage_error_log.php          # View/filter/clear system error logs
│   ├── manage_filesystem_permissions.php # Diagnose & fix file permissions
│   ├── manage_registry.php           # Function registry management
│   ├── manage_js_registry.php        # JavaScript registry management
│   ├── debug_permissions.php         # Permission debugging tool
│   ├── organism_checklist.php        # Organism setup checklist
│   ├── pages/                        # Admin page templates
│   ├── api/                          # Admin API endpoints
│   ├── tools/                        # Admin-specific utilities
│   └── css/                          # Admin-specific styles
│
├── config/                           # Configuration files
│   ├── site_config.php               # Site settings (paths, titles)
│   ├── tools_config.php              # Tool-specific configuration
│   ├── config_editable.json          # Editable site configuration
│   └── build_and_load_db/            # Database build scripts
│
├── includes/                         # Shared includes
│   ├── layout.php                    # HTML template/layout
│   ├── ConfigManager.php             # Configuration manager class
│   ├── access_control.php            # Permission helpers & IP validation
│   ├── config_init.php               # Configuration initialization
│   ├── page-setup.php                # Page setup utilities
│   ├── head-resources.php            # HTML head resources (CSS, JS)
│   ├── banner.php                    # Site banner/header
│   ├── navbar.php                    # Navigation bar
│   ├── footer.php                    # Site footer
│   ├── toolbar.php                   # Tool toolbar
│   ├── source-list.php               # Annotation source management
│   ├── source-selector-helpers.php   # Source selection utilities
│   └── moop_functions.php            # Utility functions
│
├── tools/                            # User-facing tools
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
│   └── includes/                     # Tool-specific libraries
│       ├── parent_functions.php      # Parent/feature display helpers
│       ├── blast_functions.php       # BLAST integration helpers
│       ├── extract_search_helpers.php # Sequence extraction
│       └── [other libraries]
│
├── lib/                              # Library functions (shared)
│   ├── display_functions.php         # Core display rendering helpers
│   ├── parent_functions.php          # Parent/feature display helpers
│   ├── blast_functions.php           # BLAST integration & searching
│   ├── blast_results_visualizer.php  # BLAST results formatting
│   ├── extract_search_helpers.php    # Sequence extraction & search
│   ├── database_queries.php          # Database query operations
│   ├── moop_functions.php            # General utility functions
│   ├── search_functions.php          # Search operation helpers
│   ├── fasta_download_handler.php    # FASTA download processing
│   ├── functions_access.php          # Access control functions
│   ├── functions_data.php            # Data retrieval functions
│   ├── functions_database.php        # Database helper functions
│   ├── functions_errorlog.php        # Error logging functions
│   ├── functions_filesystem.php      # File system operations
│   ├── functions_json.php            # JSON handling functions
│   ├── functions_system.php          # System utility functions
│   ├── functions_tools.php           # Tool support functions
│   ├── functions_validation.php      # Input validation functions
│   ├── common_functions.php          # Common/shared functions
│   ├── tool_config.php               # Tool configuration
│   └── tool_section.php              # Tool section rendering
│
├── js/                               # Client-side scripts
│   ├── modules/                      # Feature modules
│   │   ├── datatable-config.js       # DataTables export config
│   │   ├── parent-tools.js           # Feature page tools
│   │   └── collapse-handler.js       # UI interactions
│   └── libraries/                    # Third-party JS
│
├── css/                              # Stylesheets
│   ├── moop.css                      # Main stylesheet
│   └── component-styles.css          # Component-specific styles
│
├── metadata/                         # Configuration data
│   ├── organism_assembly_groups.json # Org-Assembly-Group mapping
│   ├── annotation_config.json        # Annotation source definitions
│   └── taxonomy_tree_config.json     # Phylogenetic tree (generated)
│
├── organisms/                        # Symlinks to organism data
│   ├── Anoura_caudifer -> /path/to/data/Anoura_caudifer
│   ├── Lasiurus_cinereus -> /path/to/data/Lasiurus_cinereus
│   └── ...
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

#### 1. **ALL Users (IP-Based Auto-Login)**

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
Table: feature
├─ feature_id          (PRIMARY KEY)
├─ feature_uniquename  (unique ID for this organism)
├─ feature_type        (gene, mRNA, exon, etc.)
├─ feature_name        (display name)
├─ feature_description (text description)
├─ genome_id           (which assembly)
├─ organism_id         (which organism)
└─ [other fields...]

Table: annotation
├─ annotation_id        (PRIMARY KEY)
├─ annotation_accession (external ID: NM_123456)
├─ annotation_description (text from external source)
├─ annotation_source_id (which source: BLAST, InterPro, etc.)
└─ [other fields...]

Table: feature_annotation
├─ feature_annotation_id (PRIMARY KEY)
├─ feature_id           (links to feature)
├─ annotation_id        (links to annotation)
├─ score               (e-value, bit score, confidence)
├─ date                (when calculated)
└─ [other fields...]

Table: annotation_source
├─ annotation_source_id (PRIMARY KEY)
├─ annotation_source_name (NCBI, InterPro, UniProt, etc.)
├─ annotation_accession_url (URL template for links)
├─ annotation_type      (homolog, domain, ortholog, etc.)
└─ [other fields...]

[Plus other tables for genome, organism, hierarchy...]
```

### File Organization Per Assembly

```
/organism_data/
└─ Organism_Name/
   ├─ assembly_v1/
   │  ├─ reference_genome.fasta       (or .fa, .fna)
   │  ├─ proteins.faa
   │  ├─ blast_nt/                    (BLAST database)
   │  │  ├─ reference_genome.00.nhr
   │  │  ├─ reference_genome.00.nin
   │  │  ├─ reference_genome.00.nsq
   │  │  └─ [more BLAST files]
   │  └─ [annotations, metadata]
   │
   └─ GCA_004027475.1/
      ├─ reference_genome.fasta
      ├─ proteins.faa
      ├─ blast_nt/
      │  ├─ reference_genome.00.nhr
      │  ├─ reference_genome.00.nin
      │  ├─ reference_genome.00.nsq
      │  └─ [more BLAST files]
      └─ [annotations, metadata]
```

---

## Search Functionality

### Search Types

#### 1. Multi-Organism Organism-Level Search

**Purpose:** Browse and search across multiple organisms/assemblies simultaneously

**Flow:**
```
User selects organisms/assemblies
          ↓
User enters search term (organism name, common name, feature)
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
│  ├─ Type: Homolog
│  ├─ Source: BLAST/NCBI
│  ├─ Hit: NP_000207.1 (Homo sapiens insulin)
│  ├─ Score: 1.2e-45 (e-value)
│  ├─ Bit Score: 178
│  └─ Link: https://www.ncbi.nlm.nih.gov/protein/NP_000207.1
│
├─ Annotation 2
│  ├─ Type: Protein Domain
│  ├─ Source: InterPro
│  ├─ Hit: IPR003236 (Insulin-like growth factor)
│  ├─ Score: 1.5e-20
│  └─ Link: https://www.ebi.ac.uk/interpro/entry/IPR003236
│
└─ Annotation 3
   ├─ Type: GO Term
   ├─ Source: InterPro → GO
   ├─ Hit: GO:0005179 (hormone activity)
   ├─ Evidence: IEA (inferred from electronic annotation)
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

On each feature detail page:

```
┌─ Annotation Header ─────────────────────────────────────┐
│ [Badge: Type]  [Badge: Count]  [Info icon]  [Search]    │
├─────────────────────────────────────────────────────────┤
│ Table with columns:                                      │
│ - Annotation ID (linked to external resource)           │
│ - Description (text from source)                        │
│ - Score (e-value, bit score, confidence)               │
│ - Source (NCBI, InterPro, etc.)                         │
│ - [Export buttons: CSV, Excel, Copy, Print]            │
├─────────────────────────────────────────────────────────┤
│ [Collapsible description of annotation type]            │
└─────────────────────────────────────────────────────────┘
```

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
│   ├── Anoura_caudifer.db           (SQLite database)
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
│   ├── Lasiurus_cinereus.db
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
