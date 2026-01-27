<?php
/**
 * ORGANISM SETUP AND SEARCH CONFIGURATION - Technical Help Documentation
 * 
 * Technical guide covering organism setup process, metadata configuration, and search mechanics.
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-cogs"></i> Organism Setup & Search Configuration</h2>
  <p class="lead text-muted">Technical guide for setting up new organisms and understanding how searches and the parent page work.</p>

  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <!-- Quick Navigation -->
  <div class="alert alert-light border">
    <strong>On this page:</strong>
    <ul class="mb-0">
      <li><a href="#setup-overview">Setup Process Overview</a></li>
      <li><a href="#setup-steps">Detailed Setup Steps</a></li>
      <li><a href="#metadata-files">Metadata Configuration Files</a></li>
      <li><a href="#search-mechanics">Search Mechanics</a></li>
      <li><a href="#parent-page">Feature Detail Page (Parent Page)</a></li>
      <li><a href="#hierarchical-queries">Querying Hierarchies</a></li>
    </ul>
  </div>

  <!-- Section 1: Setup Overview -->
  <section id="setup-overview" class="mt-5">
    <h3><i class="fa fa-tasks"></i> Setup Process Overview</h3>

    <p>Adding a new organism to MOOP involves coordinating several systems. Here's the flow:</p>

    <div class="card">
      <div class="card-body">
        <svg viewBox="0 0 1000 600" class="w-100" style="max-height: 500px;">
          <!-- Title -->
          <text x="500" y="25" font-size="18" font-weight="bold" text-anchor="middle">Organism Setup Flow</text>

          <!-- Step 1: Files -->
          <rect x="50" y="60" width="160" height="100" fill="#cfe2ff" stroke="#0d6efd" stroke-width="2" rx="5"/>
          <text x="130" y="85" font-weight="bold" text-anchor="middle" font-size="14">Step 1</text>
          <text x="130" y="105" font-weight="bold" text-anchor="middle" font-size="12">Copy Files</text>
          <text x="60" y="130" font-size="11">• organism.sqlite</text>
          <text x="60" y="145" font-size="11">• FASTA files</text>
          <text x="60" y="160" font-size="11">• organism.json</text>

          <!-- Step 2: Permissions -->
          <rect x="280" y="60" width="160" height="100" fill="#d1e7dd" stroke="#198754" stroke-width="2" rx="5"/>
          <text x="360" y="85" font-weight="bold" text-anchor="middle" font-size="14">Step 2</text>
          <text x="360" y="105" font-weight="bold" text-anchor="middle" font-size="12">Verify</text>
          <text x="360" y="120" font-weight="bold" text-anchor="middle" font-size="12">Permissions</text>
          <text x="290" y="145" font-size="11">Web server access</text>
          <text x="290" y="160" font-size="11">to all files</text>

          <!-- Step 3: Status -->
          <rect x="510" y="60" width="160" height="100" fill="#fff3cd" stroke="#ff9800" stroke-width="2" rx="5"/>
          <text x="590" y="85" font-weight="bold" text-anchor="middle" font-size="14">Step 3</text>
          <text x="590" y="105" font-weight="bold" text-anchor="middle" font-size="12">Check Status</text>
          <text x="590" y="120" font-weight="bold" text-anchor="middle" font-size="12">&amp; Metadata</text>
          <text x="520" y="145" font-size="11">Verify FASTA</text>
          <text x="520" y="160" font-size="11">Complete metadata</text>

          <!-- Step 4: Taxonomy -->
          <rect x="740" y="60" width="160" height="100" fill="#d1ecf1" stroke="#0c5460" stroke-width="2" rx="5"/>
          <text x="820" y="85" font-weight="bold" text-anchor="middle" font-size="14">Step 4</text>
          <text x="820" y="105" font-weight="bold" text-anchor="middle" font-size="12">Add to Tree</text>
          <text x="750" y="130" font-size="11">Enable discovery</text>
          <text x="750" y="145" font-size="11">on homepage</text>
          <text x="750" y="160" font-size="11">selector</text>

          <!-- Arrows -->
          <defs>
            <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="5" refY="5" orient="auto">
              <polygon points="0 0, 10 5, 0 10" fill="#333"/>
            </marker>
          </defs>
          <line x1="210" y1="110" x2="280" y2="110" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <line x1="440" y1="110" x2="510" y2="110" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>
          <line x1="670" y1="110" x2="740" y2="110" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>

          <!-- Step 5 & 6 (below) -->
          <text x="500" y="210" font-weight="bold" text-anchor="middle" font-size="13">↓</text>

          <rect x="200" y="240" width="160" height="100" fill="#f8d7da" stroke="#dc3545" stroke-width="2" rx="5"/>
          <text x="280" y="265" font-weight="bold" text-anchor="middle" font-size="14">Step 5</text>
          <text x="280" y="285" font-weight="bold" text-anchor="middle" font-size="12">Assign to Groups</text>
          <text x="210" y="310" font-size="11">Organize organisms</text>
          <text x="210" y="325" font-size="11">Public or Private</text>

          <line x1="360" y1="240" x2="520" y2="240" stroke="#333" stroke-width="2" marker-end="url(#arrowhead)"/>

          <rect x="520" y="240" width="160" height="100" fill="#e7d4f5" stroke="#6f42c1" stroke-width="2" rx="5"/>
          <text x="600" y="265" font-weight="bold" text-anchor="middle" font-size="14">Step 6</text>
          <text x="600" y="285" font-weight="bold" text-anchor="middle" font-size="12">Manage Access</text>
          <text x="530" y="310" font-size="11">If non-public:</text>
          <text x="530" y="325" font-size="11">Add user accounts</text>

          <!-- Result -->
          <text x="500" y="420" font-weight="bold" text-anchor="middle" font-size="14">✓ Organism Ready for Users</text>
          <rect x="350" y="440" width="300" height="80" fill="#e8f5e9" stroke="#27ae60" stroke-width="2" rx="5"/>
          <text x="500" y="465" text-anchor="middle" font-size="12">• Searchable across site</text>
          <text x="500" y="485" text-anchor="middle" font-size="12">• Browsable via homepage tree</text>
          <text x="500" y="505" text-anchor="middle" font-size="12">• Users can view details &amp; download sequences</text>
        </svg>
      </div>
    </div>
  </section>

  <!-- Section 2: Detailed Setup Steps -->
  <section id="setup-steps" class="mt-5">
    <h3><i class="fa fa-list-check"></i> Detailed Setup Steps</h3>

    <h4 class="mt-4">Step 1: Prepare & Copy Files</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Location:</strong> <code>/data/moop/organisms/Genus_species/</code></p>

        <h6 class="mt-3">Required Files:</h6>
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>File</th>
              <th>Purpose</th>
              <th>Required</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>organism.sqlite</code></td>
              <td>SQLite database with features, annotations, metadata</td>
              <td><span class="badge bg-danger">Yes</span></td>
            </tr>
            <tr>
              <td><code>organism.json</code></td>
              <td>Metadata file (genus, species, images, descriptions)</td>
              <td><span class="badge bg-danger">Yes</span></td>
            </tr>
            <tr>
              <td><code>Assembly_ID/</code></td>
              <td>Directory with FASTA files (one per assembly)</td>
              <td><span class="badge bg-danger">Yes</span></td>
            </tr>
            <tr>
              <td>FASTA files</td>
              <td>Sequence files (.fa, .fasta) - patterns configurable</td>
              <td><span class="badge bg-danger">Yes</span></td>
            </tr>
            <tr>
              <td>BLAST indices</td>
              <td>Pre-built BLAST databases (.nhr, .nin, etc.)</td>
              <td><span class="badge bg-warning">Optional</span></td>
            </tr>
          </tbody>
        </table>

        <h6 class="mt-4">Example Directory Structure:</h6>
        <div class="alert alert-light border">
          <pre><code>/data/moop/organisms/
└── Homo_sapiens/
    ├── organism.sqlite          ← Database with all genes/features
    ├── organism.json            ← Metadata (genus, species, images, etc.)
    ├── GRCh38/                  ← Assembly directory
    │   ├── genome.fa            ← Reference genome
    │   ├── transcript.nt.fa     ← Transcript sequences
    │   ├── cds.nt.fa            ← Coding sequences
    │   ├── protein.aa.fa        ← Protein sequences
    │   ├── genome.fa.nhr        ← BLAST indices
    │   ├── genome.fa.nin
    │   └── [more BLAST files...]
    └── GRCh37/                  ← Alternative assembly
        ├── [FASTA files...]
        └── [BLAST indices...]</code></pre>
        </div>

        <h6 class="mt-3">organism.json Format:</h6>
        <pre class="bg-light p-3 rounded"><code>{
  "genus": "Homo",
  "species": "sapiens",
  "common_name": "Human",
  "taxon_id": "9606",
  "images": [
    {
      "file": "homo_sapiens.jpg",
      "caption": "Image of a human"
    }
  ],
  "html_p": [
    {
      "text": "Humans are primates...",
      "style": "",
      "class": "fs-5"
    }
  ]
}</code></pre>

        <p class="mt-3"><small class="text-muted"><strong>Note:</strong> You can edit organism.json through the "Manage Organisms" admin interface instead of manually editing the file.</small></p>
      </div>
    </div>

    <h4 class="mt-5">Step 2: Verify File Permissions</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Why:</strong> The web server must be able to read all organism files to query the database and serve sequences.</p>

        <p><strong>Check:</strong></p>
        <ul>
          <li><code>organism.sqlite</code> - readable by web server user (typically <code>www-data</code>)</li>
          <li><code>organism.json</code> - readable by web server user</li>
          <li>Directories - executable by web server user (allows traversal)</li>
          <li>FASTA files - readable by web server user</li>
        </ul>

        <p class="mt-3"><strong>Typical permissions:</strong></p>
        <pre class="bg-light p-3 rounded"><code>drwxr-xr-x  organism_directory/
-rw-r--r--  organism.sqlite
-rw-r--r--  organism.json
-rw-r--r--  FASTA files</code></pre>

        <p class="mt-3"><strong>Use admin interface:</strong> Go to <strong>Manage Filesystem Permissions</strong> to check and fix permissions.</p>
      </div>
    </div>

    <h4 class="mt-5">Step 3: Check Status & Configure Metadata</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Where:</strong> <strong>Manage Organisms</strong> admin interface</p>

        <p><strong>What to verify:</strong></p>
        <ul>
          <li>✓ Organism appears in the list</li>
          <li>✓ All assemblies are detected</li>
          <li>✓ FASTA files are found and counted</li>
          <li>✓ Feature count from database (if > 0, database is readable)</li>
          <li>✓ BLAST indices are present (or note which are missing)</li>
        </ul>

        <p class="mt-3"><strong>What to configure:</strong></p>
        <ul>
          <li><code>Genus</code> - Scientific genus name</li>
          <li><code>Species</code> - Scientific species epithet</li>
          <li><code>Common Name</code> - User-friendly display name</li>
          <li><code>Taxon ID</code> - NCBI taxonomy ID (used for tree generation)</li>
          <li><code>Images</code> - Images to display on organism page (optional)</li>
          <li><code>Descriptions</code> - HTML content about the organism (optional)</li>
        </ul>

        <div class="alert alert-info mt-3">
          <strong><i class="fa fa-lightbulb"></i> Tip:</strong> The Status button shows which files are detected. Use this to diagnose missing or misnamed files.
        </div>
      </div>
    </div>

    <h4 class="mt-5">Step 4: Add to Taxonomy Tree</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Where:</strong> <strong>Manage Taxonomy Tree</strong> admin interface</p>

        <p><strong>Purpose:</strong> Makes the organism discoverable on the homepage tree selector.</p>

        <p><strong>Options:</strong></p>
        <ul>
          <li><strong>Auto-generate:</strong> System queries NCBI by taxon_id, builds full tree automatically</li>
          <li><strong>Manually edit:</strong> After auto-generation, you can customize the tree structure</li>
        </ul>

        <h6 class="mt-3">Tree Structure:</h6>
        <pre class="bg-light p-3 rounded"><code>Life
├── Eukaryota
│   └── Metazoa
│       └── Chordata
│           └── Mammalia
│               ├── Chiroptera (Bats)
│               │   └── Anoura_caudifer (Genus_species)
│               └── Primates
│                   └── Homo_sapiens (Genus_species)</code></pre>

        <p class="mt-3"><small class="text-muted"><strong>Note:</strong> Each organism must have a <code>taxon_id</code> in organism.json for auto-generation to work.</small></p>
      </div>
    </div>

    <h4 class="mt-5">Step 5: Assign to Groups</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Where:</strong> <strong>Manage Groups</strong> admin interface</p>

        <p><strong>Purpose:</strong> Organize organisms into categories for discovery and access control.</p>

        <h6 class="mt-3">Key Concepts:</h6>
        <div class="row">
          <div class="col-lg-6">
            <div class="card bg-light">
              <div class="card-header">
                <strong>"Public" Group</strong>
              </div>
              <div class="card-body">
                <p><small>Affects <strong>Access Control</strong>:</p>
                <ul class="mb-0">
                  <li>If assembly in "Public" group → publicly accessible (no login)</li>
                  <li>If assembly NOT in "Public" group → restricted access</li>
                </ul>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card bg-light">
              <div class="card-header">
                <strong>Other Groups</strong>
              </div>
              <div class="card-body">
                <p><small>Affects <strong>Organization</strong> only:</p>
                <ul class="mb-0">
                  <li>Pure UI/discovery - no access control</li>
                  <li>Examples: "Bats", "Mammals", "Primates"</li>
                  <li>One assembly can be in multiple groups</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <h6 class="mt-3">Configuration File:</h6>
        <p><code>/data/moop/metadata/organism_assembly_groups.json</code></p>
        <pre class="bg-light p-3 rounded"><code>[
  {
    "organism": "Homo_sapiens",
    "assembly": "GRCh38",
    "groups": ["Primates", "Reference_Genomes", "Public"]
  },
  {
    "organism": "Homo_sapiens",
    "assembly": "GRCh37",
    "groups": ["Primates", "Legacy", "Public"]
  }
]</code></pre>

        <p class="mt-3"><small><strong>Result:</strong> GRCh38 and GRCh37 both appear in Primates and Public groups. Visitors can access both (Public). Collaborators with permission can access additional non-public assemblies.</small></p>
      </div>
    </div>

    <h4 class="mt-5">Step 6: Manage User Access (If Non-Public)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Where:</strong> <strong>Manage Users</strong> admin interface</p>

        <p><strong>Only needed if:</strong> Assembly is NOT in "Public" group</p>

        <p><strong>What to do:</strong></p>
        <ul>
          <li>Create user account(s) for collaborators</li>
          <li>Assign specific organism/assembly combinations to each user</li>
          <li>Users will need to login to access restricted assemblies</li>
        </ul>

        <h6 class="mt-3">Configuration File:</h6>
        <p><code>/var/www/html/users.json</code></p>
        <pre class="bg-light p-3 rounded"><code>{
  "collaborator_name": {
    "password": "$2y$10$...",
    "access": {
      "Homo_sapiens": ["GRCh38"],
      "Lasiurus_cinereus": ["GCA_011751095.1"]
    }
  }
}</code></pre>

        <p class="mt-3"><small><strong>Note:</strong> Passwords must be bcrypt hashed. Use the admin interface to create accounts securely.</small></p>
      </div>
    </div>
  </section>

  <!-- Section 3: Metadata Files -->
  <section id="metadata-files" class="mt-5">
    <h3><i class="fa fa-file-invoice"></i> Metadata Configuration Files</h3>

    <h4 class="mt-4">organism_assembly_groups.json</h4>
    <div class="card mb-4">
      <div class="card-header bg-info bg-opacity-10">
        <strong>Purpose:</strong> Maps assemblies to groups and controls public/private visibility
      </div>
      <div class="card-body">
        <p><strong>Location:</strong> <code>/data/moop/metadata/organism_assembly_groups.json</code></p>

        <h6>Structure:</h6>
        <pre class="bg-light p-3 rounded"><code>[
  {
    "organism": "Organism_Name",
    "assembly": "Assembly_ID",
    "groups": ["Group1", "Group2", "Public"]
  }
]</code></pre>

        <h6 class="mt-3">Rules:</h6>
        <ul>
          <li>Each entry = one organism + assembly combination</li>
          <li><code>organism</code> - Must match directory name under /organisms/</li>
          <li><code>assembly</code> - Must match subdirectory name</li>
          <li><code>groups</code> - Array of group names (can be empty)</li>
          <li><strong>"Public" group</strong> - Only special group that affects access control</li>
          <li>Multiple entries for same organism but different assemblies allowed</li>
        </ul>

        <h6 class="mt-3">Example: Multiple Assemblies</h6>
        <pre class="bg-light p-3 rounded"><code>[
  {
    "organism": "Homo_sapiens",
    "assembly": "GRCh38",
    "groups": ["Primates", "Reference", "Public"]
  },
  {
    "organism": "Homo_sapiens",
    "assembly": "GRCh37",
    "groups": ["Primates", "Legacy", "Public"]
  },
  {
    "organism": "Homo_sapiens",
    "assembly": "Draft_v1",
    "groups": ["Primates", "Research"]
  }
]</code></pre>

        <p class="mt-3"><strong>Result:</strong> GRCh38 and GRCh37 are public (no login). Draft_v1 is private (requires login).</p>
      </div>
    </div>

    <h4 class="mt-5">group_descriptions.json</h4>
    <div class="card mb-4">
      <div class="card-header bg-info bg-opacity-10">
        <strong>Purpose:</strong> Metadata and display information for each group
      </div>
      <div class="card-body">
        <p><strong>Location:</strong> <code>/data/moop/metadata/group_descriptions.json</code></p>

        <h6>Structure:</h6>
        <pre class="bg-light p-3 rounded"><code>[
  {
    "group_name": "Group_Name",
    "images": [
      {
        "file": "image_filename.jpg",
        "caption": "Image caption text"
      }
    ],
    "html_p": [
      {
        "text": "&lt;p&gt;HTML content...&lt;/p&gt;",
        "style": "",
        "class": "fs-5"
      }
    ],
    "in_use": true
  }
]</code></pre>

        <h6 class="mt-3">Fields:</h6>
        <ul>
          <li><code>group_name</code> - Must match group name in organism_assembly_groups.json</li>
          <li><code>images</code> - Optional array of images to display</li>
          <li><code>html_p</code> - Optional array of HTML paragraphs about the group</li>
          <li><code>in_use</code> - Boolean: is this group currently used</li>
        </ul>

        <h6 class="mt-3">Example:</h6>
        <pre class="bg-light p-3 rounded"><code>{
  "group_name": "Bats",
  "images": [
    {
      "file": "bat_image.jpg",
      "caption": "An example bat species"
    }
  ],
  "html_p": [
    {
      "text": "Bats are the second largest order of mammals...",
      "style": "",
      "class": "fs-5"
    }
  ],
  "in_use": true
}</code></pre>
      </div>
    </div>

    <h4 class="mt-5">taxonomy_tree_config.json</h4>
    <div class="card mb-4">
      <div class="card-header bg-info bg-opacity-10">
        <strong>Purpose:</strong> Hierarchical taxonomy tree for organism discovery
      </div>
      <div class="card-body">
        <p><strong>Location:</strong> <code>/data/moop/metadata/taxonomy_tree_config.json</code></p>

        <p><strong>Generated by:</strong> "Manage Taxonomy Tree" admin interface (auto-generate from NCBI)</p>

        <h6>Structure (Nested Hierarchy):</h6>
        <pre class="bg-light p-3 rounded"><code>{
  "tree": {
    "name": "Life",
    "children": [
      {
        "name": "Eukaryota",
        "children": [
          {
            "name": "Metazoa",
            "children": [
              {
                "name": "Chordata",
                "children": [
                  {
                    "name": "Mammalia",
                    "children": [
                      {
                        "name": "Anoura",
                        "children": [
                          {
                            "name": "Anoura caudifer",
                            "organism": "Anoura_caudifer",
                            "common_name": "Tailed tailless bat",
                            "image": "images/ncbi_taxonomy/27642.jpg"
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
}</code></pre>

        <h6 class="mt-3">Leaf Nodes (Species):</h6>
        <ul>
          <li><code>name</code> - Scientific name (e.g., "Anoura caudifer")</li>
          <li><code>organism</code> - Directory name (e.g., "Anoura_caudifer")</li>
          <li><code>common_name</code> - Display name</li>
          <li><code>image</code> - Optional image file path</li>
        </ul>

        <h6 class="mt-3">Non-Leaf Nodes (Ranks):</h6>
        <ul>
          <li><code>name</code> - Taxonomic rank name (e.g., "Mammalia", "Primates")</li>
          <li><code>children</code> - Array of child nodes (recursive)</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-5">annotation_config.json</h4>
    <div class="card mb-4">
      <div class="card-header bg-info bg-opacity-10">
        <strong>Purpose:</strong> Define annotation types displayed on feature pages
      </div>
      <div class="card-body">
        <p><strong>Location:</strong> <code>/data/moop/metadata/annotation_config.json</code></p>

        <h6>Structure:</h6>
        <pre class="bg-light p-3 rounded"><code>{
  "annotation_types": {
    "Homologs": {
      "display_name": "Homologs",
      "color": "info",
      "order": 3,
      "description": "Similar sequences...",
      "enabled": true,
      "in_database": true,
      "annotation_count": 1009358,
      "feature_count": 138934
    }
  }
}</code></pre>

        <h6 class="mt-3">Fields:</h6>
        <ul>
          <li><code>display_name</code> - User-facing label</li>
          <li><code>color</code> - Bootstrap color class (primary, info, success, warning, danger, etc.)</li>
          <li><code>order</code> - Display order on page (lower = earlier)</li>
          <li><code>description</code> - HTML explanation of annotation type</li>
          <li><code>enabled</code> - Whether to show this annotation type</li>
          <li><code>in_database</code> - Whether data exists in database</li>
          <li><code>annotation_count</code> - Total annotations of this type (informational)</li>
          <li><code>feature_count</code> - How many features have this type (informational)</li>
        </ul>

        <p class="mt-3"><strong>Note:</strong> Annotation types must exist in the SQLite database to display. This configuration just controls which types are shown and how.</p>
      </div>
    </div>
  </section>

  <!-- Section 4: Search Mechanics -->
  <section id="search-mechanics" class="mt-5">
    <h3><i class="fa fa-search"></i> How Searches Work</h3>

    <h4 class="mt-4">Multi-Organism Search</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Purpose:</strong> Search for features across multiple organisms/assemblies simultaneously</p>

        <h6>Search Flow:</h6>
        <div class="alert alert-light border">
          <pre><code>User selects organisms/assemblies
    ↓
User enters search term (feature name, ID, or description)
    ↓
System validates search term
    ↓
For each selected organism:
    ├─ Connect to organism.sqlite
    ├─ Query feature table:
    │  WHERE feature_name LIKE '%term%'
    │     OR feature_uniquename LIKE '%term%'
    │     OR feature_description LIKE '%term%'
    ├─ Filter by accessible genome_ids (permissions check)
    └─ Add results to aggregated list
        ↓
Display results organized by organism
    ↓
User can export (CSV, Excel, FASTA)</code></pre>
        </div>

        <h6 class="mt-3">Database Query (Simplified):</h6>
        <pre class="bg-light p-3 rounded"><code>SELECT f.*, g.genome_name FROM feature f
JOIN genome g ON f.genome_id = g.genome_id
WHERE (f.feature_name LIKE ?
   OR f.feature_uniquename LIKE ?
   OR f.feature_description LIKE ?)
  AND f.genome_id IN (?, ?, ...)  -- Accessible genomes only
  AND g.genome_id IN (?, ?, ...)  -- Accessible genomes only
ORDER BY f.feature_name</code></pre>

        <h6 class="mt-3">Key Points:</h6>
        <ul>
          <li><strong>Substring matching:</strong> Searches use LIKE with wildcards (e.g., %insulin%)</li>
          <li><strong>Permission filtering:</strong> Only returns results from accessible assemblies</li>
          <li><strong>Case-insensitive:</strong> SQLite LIKE is case-insensitive by default</li>
          <li><strong>Multiple fields:</strong> Searches feature_name, feature_uniquename, and feature_description</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-5">Feature Detail Search (on Parent Page)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Purpose:</strong> Real-time search within annotation tables on a feature page</p>

        <h6>How It Works:</h6>
        <ul>
          <li><strong>Client-side filtering:</strong> Uses DataTables JavaScript library</li>
          <li><strong>Substring matching:</strong> Searches across all table columns</li>
          <li><strong>Real-time:</strong> Results filter as user types (no page reload)</li>
          <li><strong>Not case-sensitive:</strong> Searches are case-insensitive</li>
        </ul>

        <h6 class="mt-3">Example:</h6>
        <p>On a feature page with annotations table:</p>
        <table class="table table-sm" style="font-size: 0.9em;">
          <thead class="table-light">
            <tr>
              <th>Annotation ID</th>
              <th>Description</th>
              <th>Score</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>NP_000207.1</td>
              <td>Homo sapiens insulin</td>
              <td>1e-45</td>
            </tr>
            <tr>
              <td>NP_112345.2</td>
              <td>Mus musculus insulin</td>
              <td>1e-42</td>
            </tr>
            <tr>
              <td>XP_987654.3</td>
              <td>Glycosyltransferase domain</td>
              <td>1e-20</td>
            </tr>
          </tbody>
        </table>

        <p class="mt-3"><strong>Search "Homo":</strong> → Shows row 1 only (matches "Homo sapiens insulin")</p>
        <p><strong>Search "insulin":</strong> → Shows rows 1 & 2 (both have "insulin")</p>
        <p><strong>Search "1e-4":</strong> → Shows rows 1 & 2 (score column match)</p>
      </div>
    </div>

    <h4 class="mt-5">BLAST Search</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Purpose:</strong> Find homologous sequences in a target assembly</p>

        <h6>BLAST Search Flow:</h6>
        <pre class="bg-light p-3 rounded"><code>User uploads or pastes sequence
    ↓
System validates sequence format (FASTA)
    ↓
Select target organism and assembly
    ↓
System validates user has access to assembly
    ↓
Run BLAST command:
  blastn -db /path/to/assembly/genome.fa \
         -query /tmp/user_sequence.fa \
         -evalue 1e-5 \
         -outfmt 6
    ↓
Parse BLAST results
    ↓
For each hit:
  ├─ Extract sequence coordinates
  ├─ Query SQLite for feature at coordinates
  └─ Link to feature detail page
    ↓
Display results with:
  ├─ Subject sequence info
  ├─ E-value and bit score
  ├─ Query/subject coverage
  └─ Links to feature pages</code></pre>

        <h6 class="mt-3">Database Query for Linking BLAST Hits:</h6>
        <pre class="bg-light p-3 rounded"><code>SELECT f.* FROM feature f
WHERE f.genome_id = ?
  AND f.feature_start <= ?  -- Subject end
  AND f.feature_end >= ?    -- Subject start
ORDER BY f.feature_start</code></pre>

        <p class="mt-3"><small><strong>Note:</strong> Requires BLAST indices to exist. If missing, user sees helpful message with makeblastdb command.</small></p>
      </div>
    </div>
  </section>

  <!-- Section 5: Parent Page -->
  <section id="parent-page" class="mt-5">
    <h3><i class="fa fa-circle-nodes"></i> Feature Detail Page (Parent Page)</h3>

    <h4 class="mt-4">What is the Parent Page?</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>URL:</strong> <code>parent.php?organism=ORGANISM_NAME&uniquename=FEATURE_ID</code></p>

        <p><strong>Purpose:</strong> Display complete information about a single genomic feature (gene, mRNA, exon, etc.)</p>

        <h6 class="mt-3">What's Displayed:</h6>
        <ul>
          <li><strong>Feature hierarchy:</strong> Parents, siblings, and children (gene → mRNA → exon structure)</li>
          <li><strong>Annotations:</strong> All functional hits organized by type (BLAST, domains, GO terms, etc.)</li>
          <li><strong>Sequences:</strong> Available sequence formats for download</li>
          <li><strong>Linked features:</strong> Related features in the hierarchy</li>
        </ul>

        <h6 class="mt-3">Example URL:</h6>
        <pre class="bg-light p-3 rounded"><code>/moop/tools/parent.php?organism=Homo_sapiens&uniquename=ENSG00000254647</code></pre>
      </div>
    </div>

    <h4 class="mt-5">Data Loading Process</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>Step-by-step:</h6>
        <div class="alert alert-light border">
          <pre><code>1. Validate user has access to organism
     └─ Check IP-based access or login credentials
     └─ Check assembly access permissions

2. Load feature from database
     └─ Query organism.sqlite for feature by uniquename
     └─ Fetch all feature properties (name, type, description, etc.)

3. Load feature hierarchy
     └─ Get parents (ancestors)
     └─ Get children (descendants)
     └─ Build tree structure for display

4. Load annotations
     └─ For each annotation type:
        ├─ Query database for all features_annotations
        ├─ Group by annotation_source
        └─ Sort by score

5. Get sequence availability
     └─ Check which sequence files exist
     └─ Determine download formats available

6. Render page
     └─ Display hierarchy at top
     └─ Display annotation tables
     └─ Display sequence download options</code></pre>
        </div>

        <h6 class="mt-3">Permission Filtering:</h6>
        <p>All queries filter by accessible genome_ids:</p>
        <pre class="bg-light p-3 rounded"><code>// Only show features from accessible genomes
$genome_ids = getAccessibleGenomeIds($organism_name, $user);

$feature = getFeatureByUniquename($uniquename, $db, $genome_ids);
$parents = getAncestors($uniquename, $db, $genome_ids);
$children = getChildren($feature_id, $db, $genome_ids);</code></pre>

        <p class="mt-3"><strong>Result:</strong> Users only see features from assemblies they have access to.</p>
      </div>
    </div>

    <h4 class="mt-5">Page Sections</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>1. Feature Header</h6>
        <ul>
          <li>Feature name and type</li>
          <li>Organism and assembly</li>
          <li>Feature ID (uniquename)</li>
          <li>Breadcrumb navigation</li>
        </ul>

        <h6 class="mt-3">2. Hierarchy Section</h6>
        <ul>
          <li><strong>Ancestors:</strong> Chain to root feature (gene → parent genes)</li>
          <li><strong>Descendants:</strong> Tree of all child features</li>
          <li>Clickable links to navigate hierarchy</li>
        </ul>

        <h6 class="mt-3">3. Annotation Sections (by type)</h6>
        <ul>
          <li>One section per annotation type (Homologs, Domains, GO Terms, etc.)</li>
          <li>Badge showing annotation count</li>
          <li>Sortable, searchable table</li>
          <li>Export buttons (CSV, Excel, PDF)</li>
          <li>Linked annotation IDs (click to external resource)</li>
        </ul>

        <h6 class="mt-3">4. Sequence Section</h6>
        <ul>
          <li>Available sequence formats</li>
          <li>Download buttons for each format</li>
          <li>Format options (full sequence, CDS, promoter, etc.)</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-5">Implementation Details</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>File Structure:</h6>
        <pre class="bg-light p-3 rounded"><code>/moop/tools/parent.php (controller)
    ├─ Validate access
    ├─ Load feature data
    ├─ Configure page layout
    └─ Call render_display_page()
            ↓
    /moop/tools/pages/parent.php (view)
        ├─ Display feature hierarchy
        ├─ Loop through annotation types
        ├─ Generate annotation tables
        └─ Show sequence options</code></pre>

        <h6 class="mt-3">Key Functions (in lib/parent_functions.php):</h6>
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Function</th>
              <th>Purpose</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>getFeatureByUniquename()</code></td>
              <td>Load feature from database by ID</td>
            </tr>
            <tr>
              <td><code>getAncestors()</code></td>
              <td>Get all parent features (recursive)</td>
            </tr>
            <tr>
              <td><code>getChildren()</code></td>
              <td>Get all child features (recursive)</td>
            </tr>
            <tr>
              <td><code>getChildrenHierarchical()</code></td>
              <td>Get children with structure preserved</td>
            </tr>
            <tr>
              <td><code>getAnnotations()</code></td>
              <td>Get all annotations for a feature</td>
            </tr>
            <tr>
              <td><code>generateAnnotationTableHTML()</code></td>
              <td>Create HTML table for annotation type</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Section 6: Hierarchical Queries -->
  <section id="hierarchical-queries" class="mt-5">
    <h3><i class="fa fa-sitemap"></i> Querying Feature Hierarchies</h3>

    <p>Features can have parent-child relationships. Understanding how to query these is important for understanding how MOOP traverses the data.</p>

    <h4 class="mt-4">Feature Hierarchy Structure</h4>
    <div class="card mb-4">
      <div class="card-body">
        <pre class="bg-light p-3 rounded"><code>GENE (parent_feature_id = NULL)
├── mRNA_001 (parent_feature_id = GENE.feature_id)
│   ├── Exon_001
│   ├── Exon_002
│   └── CDS
│       └── Protein
│
└── mRNA_002 (parent_feature_id = GENE.feature_id)
    ├── Exon_003
    ├── Exon_004
    └── CDS
        └── Protein</code></pre>

        <p class="mt-3"><code>parent_feature_id</code> column stores the feature_id of the parent feature.</p>
      </div>
    </div>

    <h4 class="mt-5">Common Queries</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>1. Get a feature by uniquename:</h6>
        <pre class="bg-light p-3 rounded"><code>SELECT * FROM feature
WHERE feature_uniquename = 'ENSG00000254647'
LIMIT 1;</code></pre>

        <h6 class="mt-3">2. Get parent of a feature:</h6>
        <pre class="bg-light p-3 rounded"><code>SELECT parent.* FROM feature
WHERE feature_id = ? AND parent_feature_id IS NOT NULL;</code></pre>

        <h6 class="mt-3">3. Get all children of a feature (one level only):</h6>
        <pre class="bg-light p-3 rounded"><code>SELECT * FROM feature
WHERE parent_feature_id = ?
ORDER BY feature_type, feature_name;</code></pre>

        <h6 class="mt-3">4. Get all descendants (recursive in PHP):</h6>
        <pre class="bg-light p-3 rounded"><code>// Pseudocode:
function getAllDescendants($feature_id) {
    $direct_children = query("SELECT * FROM feature
                             WHERE parent_feature_id = ?", $feature_id);
    
    $all_descendants = $direct_children;
    
    foreach ($direct_children as $child) {
        $grandchildren = getAllDescendants($child['feature_id']);
        $all_descendants = merge($all_descendants, $grandchildren);
    }
    
    return $all_descendants;
}</code></pre>

        <h6 class="mt-3">5. Get all ancestors (up to root):</h6>
        <pre class="bg-light p-3 rounded"><code>// Pseudocode:
function getAllAncestors($feature_id) {
    $feature = query("SELECT * FROM feature WHERE feature_id = ?", $feature_id);
    
    if (!feature.parent_feature_id) {
        return [$feature];  // Root feature
    }
    
    $parent = getFeatureById(feature.parent_feature_id);
    return [$feature] + getAllAncestors(parent.feature_id);
}</code></pre>

        <h6 class="mt-3">6. Get all exons for a gene (regardless of mRNA):</h6>
        <pre class="bg-light p-3 rounded"><code>SELECT * FROM feature
WHERE feature_type = 'exon'
  AND parent_feature_id IN (
      SELECT feature_id FROM feature
      WHERE parent_feature_id = ? AND feature_type = 'mRNA'
  )
ORDER BY feature_name;</code></pre>
      </div>
    </div>

    <h4 class="mt-5">Why Hierarchies Matter</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>Biological Relationships:</h6>
        <ul>
          <li><strong>Alternative splicing:</strong> One gene → multiple mRNAs → different exons → different proteins</li>
          <li><strong>Annotation inheritance:</strong> Annotations on a gene may apply to all transcripts</li>
          <li><strong>Sequence extraction:</strong> May want full gene, single transcript, or specific exon</li>
        </ul>

        <h6 class="mt-3">MOOP Applications:</h6>
        <ul>
          <li><strong>Display:</strong> Show feature tree on parent page</li>
          <li><strong>Search:</strong> Click from gene → view all transcripts → view specific exon</li>
          <li><strong>Download:</strong> Extract sequences at any level of hierarchy</li>
          <li><strong>Validation:</strong> Ensure features belong to expected assembly</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Summary -->
  <section id="summary" class="mt-5 mb-5">
    <div class="alert alert-success">
      <h5><i class="fa fa-lightbulb"></i> Key Takeaways</h5>
      <ul class="mb-0">
        <li><strong>Setup is 6 steps:</strong> Copy files → Check perms → Configure metadata → Add to tree → Assign to groups → Manage users</li>
        <li><strong>Metadata files control:</strong> organism_assembly_groups.json (what's public), group_descriptions.json (group info), taxonomy_tree_config.json (discovery)</li>
        <li><strong>Searches are SQL queries:</strong> Using LIKE wildcards, filtered by user permissions, aggregated across organisms</li>
        <li><strong>Parent page uses hierarchy:</strong> parent_feature_id column enables tree traversal of gene → mRNA → exon structure</li>
        <li><strong>Permissions everywhere:</strong> Every query filters by accessible genomes to respect user access levels</li>
      </ul>
    </div>

    <!-- Back to Help Link -->
    <div class="mt-4">
      <a href="help.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Help
      </a>
    </div>
  </section>

</div>

<style>
.card {
  margin-bottom: 1rem;
}

code {
  background-color: #f5f5f5;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: 'Courier New', monospace;
}

pre {
  overflow-x: auto;
}

table.table-sm {
  font-size: 0.9rem;
}
</style>
