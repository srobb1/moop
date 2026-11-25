# MOOP: Presentation & Formal Documentation Outlines

This document provides structured outlines for presentations and written documentation about MOOP, organized by audience and context.

---

## Presentation Outline 1: Executive/Non-Technical Audience (15 min)

### Slide 1: What is MOOP?
**Key Message:** One platform, many genomes, fast access

- **Full Name:** Many Organisms One Platform
- **Core Mission:** Enable genomic research across multiple species efficiently and securely
- **Why It Matters:** Researchers spend less time on data access, more time on science

### Slide 2: The Problem We Solve
- Traditional approach: Each organism has separate database/website
- Problem: Researchers need to switch between systems to compare species
- MOOP Solution: All organisms in one place with unified search

### Slide 3: Key Capabilities (Headline View)
1. **Search across multiple organisms at once**
2. **Fine-grained access control** (public, private, collaborative)
3. **Fast downloads** (billions of base pairs in seconds)
4. **Compare genomes** using phylogenetic organization
5. **Role-based access** (admin, collaborators, public)

### Slide 4: Who Uses It?
- Internal research teams
- Collaborators at other institutions
- Public audiences (if data published)
- Administrators who manage the system

### Slide 5: What Can Users Do?
- **Browse:** Explore organisms organized by taxonomy
- **Search:** Find genes/sequences by name, ID, or annotation
- **Download:** Get FASTA sequences in various formats
- **Analyze:** Run BLAST searches, view functional annotations
- **Compare:** Search across multiple species simultaneously

### Slide 6: Security & Privacy
- Public data is freely available
- Private data requires login
- Role-based access (what each person can see)
- Internal networks can auto-login
- All access logged for audit trail

### Slide 7: The Numbers
- *[Insert your stats]*
  - X organisms in system
  - Y assemblies total
  - Z billion base pairs
  - A+ annotations available

### Slide 8: Technical Foundation
- Built on proven technologies
- Fast SQLite databases (one per organism)
- Indexed BLAST for searching
- Secure authentication
- Scalable architecture

### Slide 9: Call to Action
- Demonstration available
- Questions?
- Contact admin for access

---

## Presentation Outline 2: Technical/Developer Audience (30 min)

### Slide 1-2: Architecture Overview
**Show Diagram:**
```
Browser UI → Access Control → App Logic → Database Layer → SQLite/FASTA
              ↓
         Error Logging & Admin Tools
```

**Key Points:**
- Layered architecture for separation of concerns
- Permission checks at every data access layer
- Comprehensive logging for debugging

### Slide 3-4: Database Design
**Per-Organism SQLite:**
```
Features table
    ↓ (contains many) ↓
Annotations table ← annotation_sources table
    ↑                     ↑
    └─ feature_annotation ─┘
```

**Why SQLite:**
- One DB per organism (independent scaling)
- Fast local queries
- No server overhead
- Easy backups
- Excellent for millions of records

### Slide 5-6: User Authentication & Session Management

**Auth Flow:**
```
Request arrives
    ↓
Check IP in whitelist?
├─ YES → Auto-auth as ALL
└─ NO → Check session
    ├─ Valid session → Continue
    └─ No/invalid → Show login form
        ├─ User enters creds
        ├─ Verify against bcrypt hash in users.json
        └─ Set session variables
```

**Session Variables:**
```php
$_SESSION['access_level']    // ALL, Admin, Collaborator
$_SESSION['logged_in']       // true/false
$_SESSION['role']            // admin or null
$_SESSION['access']          // Array of organisms/assemblies
$_SESSION['username']        // Username if logged in
```

### Slide 7-8: Permission System
**Assembly-Level Access Control:**

```
Check for Access to Assembly X:

1. Is user ALL (IP-based)?           → ALLOW
2. Is user Admin?                     → ALLOW
3. Is Assembly in "Public" group?     → ALLOW
4. Is user Collaborator with explicit permission? → ALLOW
5. Otherwise                          → DENY
```

**Files Involved:**
- `/var/www/html/users.json` - User accounts & explicit permissions
- `metadata/organism_assembly_groups.json` - Group membership & public status
- `access_control.php` - Permission checking functions

### Slide 9-10: Organisms vs. Assemblies vs. Features
**Hierarchy:**
```
Organism (Anoura_caudifer)
    ├─ Assembly 1 (assembly_v1)
    │  ├─ Feature 1 (GENE_001)
    │  │  ├─ Annotation 1 (BLAST hit)
    │  │  ├─ Annotation 2 (Domain)
    │  │  └─ Annotation 3 (GO term)
    │  └─ Feature 2 (GENE_002)
    │     └─ [annotations...]
    │
    └─ Assembly 2 (GCA_004027475.1)
       ├─ Feature 1 (GENE_001)
       │  └─ [annotations...]
       └─ Feature 2 (GENE_002)
          └─ [annotations...]
```

**Key Difference:**
- **Organism** = biological species (one DB)
- **Assembly** = specific genome build (FASTA files)
- **Feature** = genomic element (database record)

### Slide 11-12: Groups - Organization Without Permissions
**Important Distinction:**
- Groups = UI/organizational only
- Groups do NOT grant access
- Exception: "Public" group = accessible without login

**Example:**
```json
{
  "organism": "Homo_sapiens",
  "assembly": "GRCh38",
  "groups": ["Reference", "Primates", "Public"]
}
```

Result:
- Appears in 3 filters in UI
- Only "Public" affects access control
- Other groups purely organizational

### Slide 13: Multi-Organism Searching
**How It Works:**
```
1. User selects: "Bats group" (6 organisms, 12 assemblies)
2. Enters search: "insulin"
3. System queries each assembly:
   ├─ Anoura_caudifer/assembly_v1
   ├─ Anoura_caudifer/GCA_004027475.1
   ├─ Lasiurus_cinereus/GCA_011751065.1
   └─ [etc.]
4. Aggregates results by organism
5. Presents in interactive table with export options
```

**Key Feature:** Context-aware - tools remember which organisms were selected

### Slide 14: BLAST Integration
**Process:**
```
User uploads sequence
    ↓
Selects target organism/assembly
    ↓
System validates sequence
    ↓
Runs BLAST against indexed database
    ↓
Parses results into database
    ↓
Displays with links back to features
```

**Technical Details:**
- Uses ncbi-blast+ command-line tools
- Databases pre-indexed for speed
- Results linked to feature pages

### Slide 15: Annotation System
**Types of Annotations:**
- Homologs (from BLAST searches)
- Orthologs (from OMA/EggNOG)
- Protein Domains (from InterPro)
- Pathways (from KEGG/Reactome)
- GO Terms (Gene Ontology)

**Configuration:**
```json
// annotation_config.json
{
  "annotation_types": {
    "homolog": {
      "display_label": "Homologs (BLAST)",
      "description": "...",
      "color": "info",
      "order": 1,
      "enabled": true
    }
  }
}
```

### Slide 16: Search Implementation - DataTables
**Feature Detail Page:**
- Tables with DataTables for interactive search/sort/export
- Custom substring search (not word-boundary based)
- Filters: Annotation ID, Description, Score, Source
- Export options: CSV, Excel, Copy, Print

**Substring Search Example:**
```
Search: "Homo"
    ↓
Matches: "Ensembl Homo sapiens" ✓
        "NCBI Homo sapiens" ✓
        "Homo neanderthalensis" ✓
        "Homozygous variant" ✓ (matches "Homo")
```

### Slide 17: Tool Context Preloading
**Smart UX Feature:**
```
Workflow:
1. User browses Bats group (selects 6 organisms)
2. Clicks "Sequence Extraction Tool"
3. Tool opens with Bats group pre-selected
4. User doesn't need to re-select organisms
```

**Implementation:**
- Tools receive URL parameters with selected organisms
- Pre-populate dropdowns based on context
- Faster workflows, better user experience

### Slide 18: Phylogenetic Tree Generation
**Auto-Generate Method:**
```
1. Read all organism directories
2. Extract taxon_id from organism.json
3. Query NCBI Taxonomy API for each organism
4. Fetch complete lineage (Kingdom → Species)
5. Build nested JSON tree structure
6. Save to taxonomy_tree_config.json
7. Homepage displays interactive tree
```

**Usage:**
- Click taxonomy level to filter organisms
- Drill down: Kingdom → Phylum → Class → Order → Family → Genus → Species
- Visual aid for comparative genomics

### Slide 19: File Organization
**Per-Organism Directory:**
```
Organism_Name/
├── organism.sqlite            (SQLite database with all features & annotations)
├── organism.json              (Organism metadata: taxon_id, common_name, etc.)
├── assembly_v1/
│   ├── genome.fa              (Reference genome sequences)
│   ├── transcript.nt.fa       (mRNA/transcript sequences)
│   ├── cds.nt.fa              (Coding sequence nucleotides)
│   ├── protein.aa.fa          (Protein sequences)
│   ├── genome.fa.n*           (BLAST indices for genome)
│   ├── transcript.nt.fa.n*    (BLAST indices for transcripts)
│   ├── cds.nt.fa.n*           (BLAST indices for CDS)
│   └── protein.aa.fa.p*       (BLAST indices for proteins)
└── GCA_00000000.1/
    ├── genome.fa
    ├── transcript.nt.fa
    ├── cds.nt.fa
    ├── protein.aa.fa
    └── [BLAST indices...]
```

**Key Points:**
- One `organism.sqlite` database per organism (contains all features, annotations, assemblies)
- One subdirectory per assembly (contains sequence files and BLAST indices)
- BLAST database files created with `makeblastdb` (extensions: .nhr, .nin, .nsq, etc. for nucleotide; .phr, .pin, .psq for protein)
- Sequence file patterns configurable in `config_editable.json` (not hardcoded)

**Scalability:**
- New organisms added independently
- Each organism has independent database
- Assemblies can be added/updated without affecting others
- FASTA and BLAST files organized per-assembly for easy management

### Slide 20: Admin Tools
**Available Functions:**
1. Organism Management - Add/configure organisms
2. User Management - Create accounts, set permissions
3. Annotation Sources - Configure types and sources
4. Phylogenetic Tree - Generate from NCBI or edit manually
5. Error Log Viewer - Debug and monitor system health
6. Group Management - Organize assemblies into groups

### Slide 21: Security Implementation
**Multiple Layers:**
1. **Authentication:** Bcrypt password hashing, session tokens
2. **Authorization:** Role-based access, permission checks at every data access
3. **Input Validation:** Prepared statements, XSS protection
4. **IP Whitelisting:** Internal networks auto-authenticated
5. **Error Handling:** Sensitive errors logged, generic errors shown to users
6. **Admin-Only Pages:** Require login + admin role check

**Result:** No SQL injection, no unauthorized data access, no sensitive info leaks

### Slide 22-23: Technology Stack

**Backend:**
- PHP 7.4+ with modules: `pdo_sqlite`, `sqlite3`, `curl` (application logic)
- SQLite 3 (system package + PHP extensions for data storage)
- NCBI BLAST+ (sequence searching)
- NCBI Taxonomy API (called via PHP HTTP functions for phylogenetic tree data)

**Frontend:**
- HTML5/CSS3 (semantic markup)
- Bootstrap 5 (responsive UI)
- jQuery (DOM manipulation)
- DataTables 1.13 (interactive tables, export)
- Font Awesome (icons)

**Deployment:**
- Apache 2.4+ or Nginx
- Linux/Unix server
- Symlinks for data mounting

**System Dependencies:**
- Curl (for NCBI API calls)
- Git (for version control)
- Make (for builds)

### Slide 24: Code Quality Principles
**System Goals:**
1. **Clear Code** - Variables, functions describe their purpose
2. **Easy to Maintain** - Related code grouped, DRY principle, no duplication
3. **Secure System** - Session-based auth, prepared statements, comprehensive logging
4. **Clean CSS** - Centralized styles, no inline CSS, semantic class names
5. **No Duplication** - Extract common patterns into helper functions

**Result:** Easy to understand, debug, modify, and extend

### Slide 25: Deployment Checklist
- [ ] Configure `site_config.php` (paths, titles, settings)
- [ ] Set up user accounts in `/var/www/html/users.json`
- [ ] Create `organism_assembly_groups.json` with organism/assembly mapping
- [ ] Configure IP ranges in `access_control.php` for internal access
- [ ] Create organism directories and symlinks
- [ ] Load SQLite databases with feature and annotation data
- [ ] Create BLAST databases for each assembly
- [ ] Generate phylogenetic tree (admin tool)
- [ ] Test access controls (public, private, admin)

### Slide 26: Questions & Discussion

---

## Presentation Outline 3: Research Team / Collaborators (20 min)

### Slide 1: Finding Your Data
- **Browse by organism** - All available organisms listed
- **Search by taxonomy** - Click phylogenetic tree to filter
- **Search by group** - Pre-defined collections (e.g., "Bats", "Lab_Project")

### Slide 2: Accessing Genomes
- **Public data** - No login required, freely available
- **Your data** - Login to access your project assemblies
- **Contact admin** - If you need access to specific data

### Slide 3: Downloading Sequences
**Method 1: By Feature ID**
1. Go to "Download FASTA"
2. Enter feature accessions (comma-separated)
3. Select format (full genome, CDS, protein, etc.)
4. Download file

**Method 2: From Feature Page**
1. Find feature (search or browse)
2. Click feature name
3. View all annotations
4. Download sequences from page

### Slide 4: Running BLAST Searches
1. Go to "BLAST Search"
2. Paste your sequence (FASTA format)
3. Select target organism and assembly
4. Set search parameters (e-value, etc.)
5. Results include links to features

### Slide 5: Viewing Annotations
**On each feature page, see:**
- All homologous sequences (BLAST hits)
- Protein domains and functions
- Orthologous genes in other species
- Gene Ontology terms and pathways
- Links to external databases

### Slide 6: Multi-Organism Searching
1. Select organisms or group (e.g., "All Bats")
2. Enter search term (gene name, ID, etc.)
3. See results aggregated by organism
4. Download results as CSV/Excel

### Slide 7: Exporting Results
**Export options on all tables:**
- Copy to clipboard
- CSV (for spreadsheets)
- Excel (.xlsx)
- PDF/Print
- FASTA (sequence tables)

### Slide 8: Getting Help
- Hover over information icons for tooltips
- Click "?" buttons for explanations
- Contact: [admin contact info]

---

## Written Document Outline 1: System Administrators

### Chapter 1: System Overview
- Purpose and use cases
- Architecture and components
- Technology stack
- Deployment requirements

### Chapter 2: Installation & Configuration
- Server setup requirements
- PHP and dependencies
- Database initialization
- BLAST database setup

### Chapter 3: User Management
- Creating user accounts
- Setting permissions (users.json)
- IP-based auto-login configuration
- Password management and security

### Chapter 4: Organism & Data Management
- Adding new organisms
- Creating assemblies
- Loading FASTA sequences
- Building BLAST databases
- Creating SQLite databases
- Updating annotations

### Chapter 5: Backup & Recovery
- Database backup procedures
- FASTA file backup
- Configuration file backup
- Recovery from backups
- Disaster recovery planning

### Chapter 6: Monitoring & Maintenance
- Error log monitoring and cleanup
- Database optimization
- Disk space management
- Performance tuning
- Regular maintenance tasks

### Chapter 7: Security & Access Control
- Authentication mechanisms
- Authorization hierarchy
- IP whitelisting
- Session management
- Audit logging

### Chapter 8: Troubleshooting
- Common issues and solutions
- Debugging techniques
- Error log interpretation
- Performance issues
- Data inconsistencies

---

## Written Document Outline 2: For Publications/Grants

### Abstract
*One paragraph summarizing MOOP and its capabilities*

### 1. Introduction
- The problem: Managing multiple genome databases is cumbersome
- Traditional solutions and their limitations
- MOOP as a unified solution
- Key innovations

### 2. System Architecture
- Multi-organism database design (SQLite per organism)
- Scalable file organization
- Role-based access control
- Phylogenetic tree integration
- Web-based interface

### 3. Key Features
- Multi-organism search
- Comparative genomics tools
- Sequence extraction and analysis
- BLAST integration
- Annotation management
- Access control and collaboration support

### 4. Use Cases
- Internal research team collaboration
- Cross-species comparative studies
- Public genome data dissemination
- Collaborative research projects
- Education and training

### 5. Technical Details
- Database schema
- Permission system
- Search implementation
- Annotation types
- API integration (NCBI Taxonomy)

### 6. Performance & Scalability
- Query performance (in billions of BP)
- Search speed across multiple organisms
- BLAST database indexing efficiency
- Concurrent user support
- Scaling to additional organisms

### 7. Security & Compliance
- Authentication and authorization
- Data privacy and access control
- Error handling and logging
- Audit trails
- GDPR/privacy compliance (if applicable)

### 8. Deployment
- Hardware requirements
- Software dependencies
- Installation steps
- Configuration options
- Example deployments

### 9. Future Directions
- Planned enhancements
- API development
- Advanced search capabilities
- Machine learning integration (if planned)
- Community features

### 10. Conclusion
- Summary of contributions
- Impact on research workflows
- Availability and support

### References
- Technologies used
- Data sources (NCBI, etc.)
- Related work

---

## Quick Fact Sheets

### Fact Sheet 1: For IT/Systems Teams

**System Requirements**
- Server: Linux/Unix, 4+ GB RAM, 500GB+ storage
- Software: PHP 7.4+, SQLite 3, Apache/Nginx
- Tools: ncbi-blast+, curl
- Network: Internet access for NCBI APIs (optional)

**Administration**
- User management through /admin/
- Organism management via admin tools
- Error monitoring and logging
- Permission configuration via JSON files

**Scalability**
- Can handle 100+ organisms
- Supports billions of base pairs
- Independent databases per organism
- Multi-assembly per organism support

---

### Fact Sheet 2: For End Users

**What Can You Do?**
- Search across multiple organisms
- Download FASTA sequences in various formats
- Run BLAST searches
- View functional annotations (domains, homologs, etc.)
- Browse phylogenetic relationships

**Access Levels**
- **Public:** Browse public genomes (no login)
- **Collaborator:** Login for project-specific data
- **Admin:** Full system access

**Common Workflows**
1. Find gene: Browse organism → Search by name
2. Get sequence: Click feature → Download FASTA
3. Find homologs: Use BLAST tool → View results
4. Compare species: Multi-organism search → Export results

---

### Fact Sheet 3: For Grant Applications

**Unique Capabilities**
- Single interface for multiple organisms
- Fine-grained access control
- Comprehensive annotation integration
- Comparative genomics support
- Role-based collaboration

**Research Advantages**
- Fast access to comparative data
- Reduced data management burden
- Improved collaboration efficiency
- Support for large-scale studies
- Audit trail for reproducibility

**Impact**
- *[Your research outcomes with MOOP]*
- *[Collaborations enabled]*
- *[Publications using MOOP]*
- *[Data availability]*

---

## Presentation Tips

### For Executive/Non-Technical Audience
- Focus on capabilities, not technical details
- Use real examples from your research
- Show statistics (number of organisms, data volume)
- Emphasize ease of use and collaboration
- Demonstrate workflow on actual system

### For Technical Audience
- Show architecture diagrams
- Explain design decisions
- Discuss technology choices
- Address scalability and performance
- Handle technical questions in depth

### For Research Collaborators
- Show practical examples of their use cases
- Demonstrate time savings vs. manual data gathering
- Show export/analysis capabilities
- Emphasize ease of access to shared data
- Discuss access control for private data

### For Administrators
- Focus on management tools
- Discuss monitoring and maintenance
- Address security and compliance
- Show troubleshooting approaches
- Highlight ease of adding new organisms

---

## Customization Guide

All outlines above can be customized:

1. **Tailor statistics** - Replace `[X organisms, Y assemblies, Z bp]` with your actual numbers
2. **Add examples** - Include real features from your organisms
3. **Emphasize strengths** - Highlight what's unique about your deployment
4. **Include contacts** - Add your admin/support contact information
5. **Add logos** - Include institutional branding
6. **Customize colors** - Adapt to your presentation theme
7. **Add results** - Include plots/charts from your research using MOOP

---

**End of Document**

For the comprehensive technical overview, see `MOOP_COMPREHENSIVE_OVERVIEW.md`
