<?php
/**
 * TAXONOMY TREE MANAGEMENT & USAGE - Help Tutorial
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-4"><i class="fa fa-tree"></i> Taxonomy Tree: Organization and Usage</h1>

      <!-- Overview Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">What is the Taxonomy Tree?</h3>
          <p class="text-muted mb-3">
            The Taxonomy Tree is the organizational backbone of MOOP. It structures all organisms in your database according to their evolutionary relationships, making it easy to:
          </p>
          <ul class="text-muted mb-0">
            <li><strong>Visualize relationships:</strong> See how organisms are related evolutionarily</li>
            <li><strong>Select custom groups:</strong> Choose specific organisms or entire branches for analysis</li>
            <li><strong>Navigate large databases:</strong> Find organisms quickly in databases with many species</li>
            <li><strong>Run cross-organism queries:</strong> Simultaneously search across related organisms</li>
          </ul>
        </div>
      </div>

      <!-- Tree Structure Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Understanding the Tree Structure</h3>
          <p class="text-muted mb-3">
            The taxonomy tree is hierarchically organized, typically following standard taxonomic ranks:
          </p>
          
          <div class="bg-light p-3 rounded mb-3">
            <p class="mb-2"><strong>Typical Tree Hierarchy:</strong></p>
            <pre class="mb-0" style="font-size: 0.9em;">Kingdom
  ├── Phylum
  │   ├── Class
  │   │   ├── Order
  │   │   │   └── Family
  │   │   │       ├── Genus
  │   │   │       │   ├── Species (Organism 1)
  │   │   │       │   └── Species (Organism 2)
  │   │   │       └── Genus 2
  │   │   │           └── Species (Organism 3)</pre>
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Key Terms:</h5>
          <ul class="text-muted">
            <li><strong>Parent nodes:</strong> Higher-level taxonomic groups (Kingdom, Phylum, etc.). Selecting a parent automatically selects all organisms below it.</li>
            <li><strong>Leaf nodes:</strong> Individual organisms (Species). These are the actual databases you can search and analyze.</li>
            <li><strong>Branches:</strong> Collections of related organisms at any hierarchical level.</li>
          </ul>
        </div>
      </div>

      <!-- Using Tree Select Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Using Tree Select on the Home Page</h3>
          <p class="text-muted mb-3">
            The "Tree Select" button on the MOOP home page provides an interactive way to build custom organism selections:
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">How to Use Tree Select:</h5>
          <ol class="text-muted">
            <li><strong>Access the tree:</strong> Click the "Tree Select" button on the home page</li>
            <li><strong>View the taxonomy:</strong> Explore the hierarchical tree of all available organisms</li>
            <li><strong>Click to select:</strong> Click any node (branch or organism) to toggle selection on/off
              <ul>
                <li>Selecting a parent node selects all organisms below it</li>
                <li>Deselecting a parent node deselects all organisms below it</li>
                <li>Individual organism selections override parent selections</li>
              </ul>
            </li>
            <li><strong>Filter the tree:</strong> Use the search box to quickly find organisms by name</li>
            <li><strong>Review selections:</strong> The right sidebar shows your selected organisms in real-time</li>
            <li><strong>Choose a tool:</strong> Select a tool from the Tool Box to begin your analysis with the selected organisms</li>
          </ol>

          <div class="bg-info bg-opacity-10 p-3 rounded mt-3 mb-0">
            <i class="fa fa-info-circle text-info"></i> <strong>Pro Tip:</strong> You can select organisms from different branches. For example, you could select all mammals AND all insects to compare them.
          </div>
        </div>
      </div>

      <!-- Multi-Organism Search Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Multi-Organism Search with the Tree</h3>
          <p class="text-muted mb-3">
            The taxonomy tree enables powerful cross-organism queries:
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Workflow:</h5>
          <ol class="text-muted">
            <li>Use "Tree Select" to choose your organisms (e.g., "All Drosophila species")</li>
            <li>Select the <strong>Multi-Organism Search</strong> tool from the Tool Box</li>
            <li>Enter your search query (gene ID or annotation keywords)</li>
            <li>Results show matching genes and annotations across all selected organisms</li>
            <li>Compare sequences, annotations, and data across species</li>
          </ol>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Why This Is Powerful:</h5>
          <ul class="text-muted mb-0">
            <li>Find orthologous genes across multiple species at once</li>
            <li>Compare annotations and gene structures across an entire genus</li>
            <li>Identify genes with similar functions across evolutionary distances</li>
            <li>Quickly determine gene presence/absence in related organisms</li>
          </ul>
        </div>
      </div>

      <!-- Managing the Tree Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Managing the Taxonomy Tree (Administrator)</h3>
          <p class="text-muted mb-3">
            Administrators can manage the taxonomy tree through the "Manage Taxonomy Tree" admin tool.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">How the Tree is Generated:</h5>
          <p class="text-muted mb-3">
            The tree is automatically generated by querying the NCBI Taxonomy Database:
          </p>
          <ol class="text-muted">
            <li>MOOP reads metadata from all organisms in the database</li>
            <li>Each organism must have a <strong>taxon_id</strong> in its <code>organism.json</code> file</li>
            <li>The system queries the NCBI Taxonomy API for each organism's complete lineage</li>
            <li>NCBI returns the full taxonomic hierarchy (Kingdom → Phylum → Class → Order → Family → Genus → Species)</li>
            <li>MOOP builds a hierarchical tree structure by merging organisms that share taxonomic branches</li>
            <li>The tree is saved as a JSON configuration file for fast retrieval</li>
          </ol>

          <div class="bg-success bg-opacity-10 p-3 rounded mt-3 mb-3">
            <i class="fa fa-leaf text-success"></i> <strong>Note:</strong> Both general and technical users benefit from proper tree organization. The tree enables powerful cross-organism searches for all user types.
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Regenerating the Tree:</h5>
          <p class="text-muted mb-3">
            When you add new organisms to your MOOP database, regenerate the taxonomy tree:
          </p>
          <ol class="text-muted">
            <li>Navigate to the Admin panel</li>
            <li>Select "Manage Taxonomy Tree"</li>
            <li>Click "Generate Tree from NCBI"</li>
            <li>The system will scan all organisms and query NCBI for their taxonomic information</li>
            <li>This may take several seconds depending on the number of organisms (approximately 350ms per organism)</li>
            <li>You'll see confirmation with the number of organisms processed</li>
          </ol>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Manual Tree Customization:</h5>
          <p class="text-muted mb-3">
            Advanced administrators can manually edit the tree JSON for custom organization:
          </p>
          <ul class="text-muted mb-0">
            <li>Edit the raw JSON in the "Tree Configuration" section</li>
            <li>Create custom groupings not based on standard taxonomy</li>
            <li>Organize organisms by research project, data quality, or other criteria</li>
            <li>Save your custom tree configuration</li>
          </ul>
        </div>
      </div>

      <!-- Organism Setup Requirements -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Organism Setup Requirements for the Tree</h3>
          <p class="text-muted mb-3">
            For organisms to appear in the taxonomy tree, each must have proper metadata configuration:
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Required Files:</h5>
          <p class="text-muted mb-3">
            Each organism directory must contain an <code>organism.json</code> file with this structure:
          </p>

          <div class="bg-light p-3 rounded mb-3">
            <pre class="text-muted" style="font-size: 0.85em; overflow-x: auto;">
{
  "genus": "Drosophila",
  "species": "melanogaster",
  "taxon_id": "7227",
  "common_name": "Fruit Fly",
  "assembly_version": "R6.32",
  "data_source": "FlyBase"
}
            </pre>
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">NCBI Taxon ID:</h5>
          <p class="text-muted mb-3">
            The <strong>taxon_id</strong> is the key to tree generation. It must be:
          </p>
          <ul class="text-muted">
            <li>A valid numeric ID from the NCBI Taxonomy Database</li>
            <li>Unique for each organism (don't reuse taxon_ids)</li>
            <li>Retrieved from <a href="https://www.ncbi.nlm.nih.gov/taxonomy" target="_blank">NCBI Taxonomy Browser</a></li>
          </ul>

          <div class="bg-success bg-opacity-10 p-3 rounded mt-3 mb-0">
            <i class="fa fa-leaf text-success"></i> <strong>Pro Tip:</strong> Keep a master list of all organisms with their taxon_ids. This makes it easy to add new organisms or troubleshoot tree generation issues.
          </div>
        </div>
      </div>

      <!-- Tree Configuration Details -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Tree Configuration File Format</h3>
          <p class="text-muted mb-3">
            The taxonomy tree is stored in a JSON file that can be viewed and manually edited by administrators:
          </p>

          <div class="bg-light p-3 rounded mb-3">
            <p class="text-muted mb-2"><strong>Example JSON Structure:</strong></p>
            <pre class="text-muted" style="font-size: 0.85em; overflow-x: auto;">
{
  "label": "Root",
  "expanded": true,
  "children": [
    {
      "label": "Animalia",
      "expanded": true,
      "children": [
        {
          "label": "Metazoa",
          "expanded": true,
          "children": [
            {
              "label": "Insecta",
              "expanded": false,
              "children": [
                {
                  "label": "Drosophila melanogaster",
                  "organism": true,
                  "name": "Drosophila melanogaster"
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
            </pre>
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Key JSON Properties:</h5>
          <ul class="text-muted">
            <li><strong>label:</strong> Display name for the node</li>
            <li><strong>expanded:</strong> Whether the branch is expanded by default (true/false)</li>
            <li><strong>organism:</strong> Marks this as a selectable organism (true for leaf nodes)</li>
            <li><strong>name:</strong> Internal identifier matching the organism database name</li>
            <li><strong>children:</strong> Array of child nodes</li>
          </ul>
        </div>
      </div>

      <!-- Best Practices Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Best Practices for Taxonomy Organization</h3>
          <ul class="text-muted">
            <li><strong>Keep metadata current:</strong> Ensure organism metadata is accurate and complete, with valid taxon_ids</li>
            <li><strong>Regenerate after changes:</strong> Regenerate the tree whenever you add, remove, or modify organisms</li>
            <li><strong>Use standard taxonomy:</strong> When possible, use recognized taxonomic classifications for consistency</li>
            <li><strong>Group logically:</strong> If using custom groupings, use clear naming conventions</li>
            <li><strong>Document customizations:</strong> Note any non-standard groupings for users and administrators</li>
            <li><strong>Test before deploying:</strong> Verify the tree works as expected before users rely on it</li>
            <li><strong>Respect access controls:</strong> Remember that users will only see organisms they have permission to access in the tree</li>
          </ul>
        </div>
      </div>

      <!-- Troubleshooting Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Troubleshooting</h3>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Tree not updating after adding organisms?</h5>
          <p class="text-muted mb-3">
            Regenerate the tree through the Manage Taxonomy Tree admin tool. The tree doesn't automatically update when organisms are added.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Organisms not appearing in the tree?</h5>
          <p class="text-muted mb-3">
            Check that organism metadata files are properly formatted. Ensure:
            <ul class="text-muted">
              <li>Each organism has an <code>organism.json</code> file in its directory</li>
              <li>The <code>organism.json</code> contains a valid <strong>taxon_id</strong> field</li>
              <li>The <code>taxon_id</code> matches a valid NCBI Taxonomy ID</li>
              <li>Organisms have been loaded into the database</li>
            </ul>
          </p>
          <p class="text-muted mb-0">
            <strong>Finding a taxon_id:</strong> Search for your organism on the <a href="https://www.ncbi.nlm.nih.gov/taxonomy" target="_blank">NCBI Taxonomy Browser</a> and copy the numeric ID from the URL.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Tree is not expanding/collapsing properly?</h5>
          <p class="text-muted mb-3">
            Try clearing your browser cache or using a different browser. Check that JavaScript is enabled and that the taxonomy-tree.js file is loading correctly.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Can't find organisms using tree search?</h5>
          <p class="text-muted mb-3">
            The search filters by exact name matching. Try different search terms or check the organism's metadata for alternative names.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">NCBI query failed during tree generation?</h5>
          <p class="text-muted mb-0">
            NCBI has rate limits (3 requests/second without an API key). If you have many organisms, the process may time out. Try regenerating again, or contact your system administrator if issues persist. Verify that all taxon_ids are valid by checking them on <a href="https://www.ncbi.nlm.nih.gov/taxonomy" target="_blank">NCBI Taxonomy Browser</a>.
          </p>
        </div>
      </div>

      <!-- Summary Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Key Takeaways</h3>
          <ul class="text-muted mb-0">
            <li>The Taxonomy Tree organizes organisms hierarchically based on evolutionary relationships</li>
            <li>Use "Tree Select" on the home page to build custom organism selections</li>
            <li>Selected trees enable powerful cross-organism searches and comparisons</li>
            <li>Administrators can regenerate the tree from metadata or customize it manually</li>
            <li>The tree is automatically generated from organism metadata files</li>
            <li>Proper tree organization enhances usability for all MOOP users</li>
          </ul>
        </div>
      </div>

      <div class="mb-4">
        <a href="help.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left"></i> Back to Help
        </a>
      </div>
    </div>
  </div>
</div>
