# SIMRbase MOOP Edition - User Walkthrough

## Getting Started: Home Landing Page

When you first visit SIMRbase, you'll see the home landing page. Here's what you can do:

### Access Control

The organisms you see depends on your access level:

- **Anonymous (no login):** 
  - See all organisms available to your IP address (if your IP is within the configurable allowed range)
  - Read-only access to public data
  
- **Logged-in Users:** 
  - See all **Public** organisms
  - See any private organisms you've been specifically configured to access
  - Your credentials are secure: passwords are hashed with bcrypt, authentication is session-based, and access is validated on every protected page (see [Security & Encryption](SECURITY_IMPLEMENTATION.md) for details)

### Selecting Organisms to Analyze

You have **two main ways** to select organisms for your searches:

---

## Option 1: Group Cards

### Browse Pre-defined Organism Groups

Click on any **GROUP CARD** to view organisms organized by:
- **Research Project** - organisms grouped by collaborative project
- **Taxonomic Family** - organisms grouped by biological classification  
- **Data Collection** - organisms grouped by when/how they were sequenced
- **Custom Groups** - any other organizational scheme your administrator created

**What happens when you select a group:**
1. You'll see all organisms in that group
2. You can perform searches across the entire group
3. Or dive into individual organism details

---

## Option 2: Taxonomy Tree Browser

### Browse by Taxonomic Classification

Click on the **TAXONOMY TREE** to explore organisms hierarchically:

**The tree is built from NCBI Taxonomy data** included in each organism's configuration file (`organism.json`):
- Expand/collapse nodes by clicking
- Navigate from Kingdom → Phylum → Class → Order → Family → Genus → Species
- See organism images and assembly counts at each level

**Interactive features:**
- Download taxonomic images from NCBI Taxonomy database
- Filter by assembly availability
- Zoom to focus on specific clades

---

## Typical User Workflows

### Workflow 1: Search Within a Single Organism

**The Path:** Group Card → Group Page → Select Organism → Search

**Step-by-step:**

1. **Start at a Group Card** (e.g., "Bats")
   - Click on the group card from the home page
   
2. **Go to the Group Page**
   - See all organisms in that group
   - View group-level information and statistics
   
3. **Select a Specific Organism**
   - Click on one of the organisms listed at the bottom of the page
   - This takes you to the single organism page
   
4. **Search Within That Organism** - Now you have three search options:

   **Option A: Gene Search**
   - Search by Gene ID (exact match)
   - Text search across gene names, descriptions, and annotations
   - Results show matching features with location, sequence, and metadata

   **Option B: BLAST Search**
   - This organism is selected by default for the search
   - Submit your sequence (upload file or paste)
   - BLAST against this organism's databases
   - To search across all available organisms, clear the organism filter
   
   **Option C: Retrieve Sequences**
   - Download specific sequences from this organism
   - Export by gene ID, region, or feature type
   - Available formats: FASTA, GenBank, GFF3

---

### Workflow 2: Compare Genes Across Multiple Organisms

**The Path:** Group Card → Group Page → Search Across Group

**Step-by-step:**

1. **Click a Group Card** (e.g., "Bats")
   - Click on the group card from the home page
   
2. **Go to the Group Page**
   - See all organisms in that group listed together
   
3. **Refine Your Selection** (Optional)
   - Deselect individual organisms if you want to search a subset
   - Only selected organisms will be included in search results
   
4. **Search Across All Selected Organisms** - Choose one of three options:

   **Option A: Gene ID / Annotation Search**
   - Same search as Workflow 1 (gene ID, name, description, annotations)
   - Results show matching features across ALL selected organisms in the group
   - See which genes/features appear in which organisms
   - Quickly identify orthologs across the group

   **Option B: BLAST Search**
   - Click the BLAST tool link from the group page
   - Submit your sequence
   - BLAST automatically runs against all selected organisms in the group
   
   **Option C: Retrieve Sequences**
   - Click the retrieve sequences link from the group page
   - Download matching sequences from all selected organisms
   - Useful for collecting orthologs or conserved regions

---

### Workflow 3: Search a Custom Organism Selection

**The Path:** Taxonomy Tree → Select Organisms → Choose Tool

**Step-by-step:**

1. **Go to Home Landing Page**
   - Click on the Taxonomy Tree browser
   
2. **Build Your Custom Selection**
   - Navigate the tree (Kingdom → Phylum → Class → etc.)
   - Click to select individual organisms you want
   - Multiple selections build your custom grouping
   - Can select organisms from different parts of the tree
   
3. **Apply Your Tool** - With your selection active, choose one of three options:

   **Option A: Search**
   - Gene ID / Annotation search across all selected organisms
   - See results from your custom organism set
   
   **Option B: BLAST**
   - Click the BLAST tool link
   - BLAST against your custom selection
   
   **Option C: Retrieve Sequences**
   - Click the retrieve sequences link
   - Download from your custom organism set

---

## The Search Feature

A search box is found at the top of every group, organism, and assembly page. Searches are **scoped to the context**:

- **Group search** - searches all genes and annotations across all organisms in the group
- **Organism search** - searches the single organism
- **Assembly search** - searches one specific assembly of an organism

### Search Basics

**How to search:**
1. Type your search terms in the search box
2. Click the search icon (magnifying glass) to activate the search
3. Optionally click the filter icon to refine results by annotation source

**Search syntax:**
- **Multi-word search** - returns results containing each word, in any order (e.g., "insulin receptor" finds "receptor insulin antagonist")
- **Quoted search terms** - used for exact matching (e.g., `"insulin receptor"` finds exact phrase only)
- **Short words** - words with fewer than 3 letters are ignored unless quoted
  - Example: `histone deacetylase 1` searches for "histone deacetylase" (the "1" is ignored)
  - To include short words: `"histone deacetylase 1"` includes the 1 in the exact match
- **Result limit** - searches return a maximum of 2,500 results

### Search Filtering

**Filter by annotation source:**
- Click the filter icon to the right of the search box
- Select which annotation databases to include:
  - **Homologs** → Ensembl Human, other databases
  - **Domains** → Pfam, InterPro, others
  - **Gene Ontology** → Biological Process, Molecular Function, etc.
  - Any other configured annotation sources
- Only checked sources will be searched

### Understanding Results

**Result sorting:**
- Results are sorted by **relevance** - most relevant matches appear first
- Relevance scoring considers:
  - **Match location**: Matches in sequence names/descriptions rank higher than matches in annotations (more specific = higher rank)
  - **Match type**: Exact matches rank higher than partial matches; matches at the start of a word rank higher than mid-word matches
  - **Annotation type**: Direct feature matches (genes, proteins) rank higher than annotation hits (functional predictions, homology data)
- You can click the "Score" column header to re-sort results if needed

**Progress messages:**
- The search will query each included database one at a time
- Progress messages show which database is being searched
- Shows percentage of searches completed (e.g., if searching 4 organism databases and 1 is complete: 25% (1/4) completed)

**Result summary:**
Once complete, you'll see:
- Total number of features found across all searched organisms
- Count of annotations matched
- Breakdown by organism (if searching a group)

**Exploring results:**

- **Jump to organism** - click to view results from a specific organism in the group
- **Expand details** - click to see detailed annotation information with search terms highlighted
- **View sequences** - click a feature to see its full sequence and location

### Managing Results

**Sort results:**
- Click the arrow icon at the top of any column to sort
- Click again to reverse sort order (ascending/descending)
- Sort by any field: name, description, organism, annotation type, score, etc.

**Filter results:**
- Type into the filter box at the top of any column
- Filter works interactively as you type (results update in real-time)
- Combine multiple column filters for more precise searches
- Example: Filter Annotation Source to "OMA" and Annotation Description to "G1" to see only OMA homologs with G1 designation

**Download results:**
- Click the checkbox at the left of a result line to select it
- Select multiple results by checking multiple boxes
- Choose your download format:
  - **Excel** (.xlsx) - spreadsheet with all fields
  - **CSV** (.csv) - comma-separated values for analysis tools
  - **FASTA** (.fa) - sequence format for bioinformatics tools
- Downloaded file contains only selected results with all their details

**Navigate results:**
- If more than 25 results are returned, a pager appears at the bottom
- Click page numbers to navigate through results
- Pager shows current page and total number of pages
- Each page displays up to 25 results

---

### Search Examples

| What you want | How to search |
|---------------|---------------|
| Find the insulin gene | `insulin` |
| Find "insulin receptor" exactly | `"insulin receptor"` |
| Find insulin in any member of receptor family | `insulin receptor` |
| Find all Pfam domains | Use filter: select only Pfam source |
| Find genes by GO ID | `GO:0004407` |
| Find homologs to human protein | Search in organism, use Homologs filter |

---

### Organisms vs. Assemblies

- **Organism** = A species (e.g., *Anoura caudifer*)
  - Can have multiple assemblies (different genome versions)
  - One `organism.json` metadata file
  - One `organism.sqlite` database per organism

- **Assembly** = A specific genome build/version (e.g., GCA_004027475.1)
  - Contains actual sequence files (genome.fa, cds.nt.fa, etc.)
  - Each assembly can have different features and quality

### Groups

Groups are administrator-defined collections of organisms. Examples:
- **"Bat Research Project"** - all bat species in the system
- **"Complete Genomes"** - only organisms with fully sequenced genomes
- **"Chiroptera"** - all organisms in the bat order

Your administrator controls:
- Which organisms belong to each group
- Which users can see which groups
- Whether groups are public or private

### Taxonomy Tree

The taxonomy tree is a hierarchical browser showing evolutionary relationships:
- **Based on NCBI Taxonomy** - standardized, consistent across databases
- **Updated when you add organisms** - new taxa automatically appear
- **Visual cues** - images and assembly counts at each level

---

## Common Searches

Once you've selected an organism or group, here are typical searches you might do:

### BLAST Search
Find genes in your organism that match a sequence you provide
- Upload your own sequence
- Or paste a sequence directly
- Get back matching genes sorted by similarity

### Annotation Search
Find all genes matching certain criteria:
- By gene name (e.g., "insulin")
- By feature type (e.g., "exon", "promoter")
- By sequence properties (length, GC content, etc.)

### Feature Explorer
Browse and explore all features in the genome:
- View genes, proteins, regulatory regions
- Click for detailed information
- Export subsets for analysis

---

## Help & Documentation

Throughout SIMRbase, you'll see links to detailed help:

- **[Organism Selection](organism-selection.php)** - choosing organisms and groups
- **[Multi-Organism Analysis](multi-organism-analysis.php)** - comparing across organisms
- **[Taxonomy Tree Management](taxonomy-tree-management.php)** - working with the taxonomy browser
- **[Search and Filter](search-and-filter.php)** - detailed search syntax and options
- **[Data Export](data-export.php)** - downloading data in various formats
- **[BLAST Tutorial](blast-tutorial.php)** - step-by-step BLAST search guide

---

## Security & Privacy

Your data and searches are protected:

- **Encrypted credentials** - usernames/passwords are securely hashed
- **Role-based access control** - see only organisms you're authorized for
- **IP-based access** - can restrict access by network location
- **Activity logging** - administrators can audit user actions
- **Per-organism permissions** - fine-grained control over who sees what

See [Security Implementation](SECURITY_IMPLEMENTATION.md) for technical details.

---

## Questions or Issues?

- Click the **Help** menu in the top navigation
- Check the **[Getting Started Guide](getting-started.php)**
- Contact your system administrator for access issues or feature requests
