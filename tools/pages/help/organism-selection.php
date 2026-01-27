<?php
/**
 * ORGANISM SELECTION TUTORIAL - Content File
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
    <div class="col-lg-8">
      <h1 class="fw-bold mb-4"><i class="fa fa-dna"></i> Selecting Organisms</h1>

      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Two Ways to Select Organisms</h3>
          <p class="text-muted mb-4">
            MOOP provides two flexible methods for selecting which organisms you want to work with.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Method 1: Group Select</h4>
          <p class="text-muted mb-3">
            The fastest way to start exploring. The home page displays pre-organized organism groups as cards.
          </p>
          <ul class="text-muted">
            <li>Click on any group card to view organisms in that group</li>
            <li>Each group has a description explaining what organisms it contains</li>
            <li>Groups are created by administrators based on common research interests</li>
            <li>Perfect for quick exploration or common analyses</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Refining Group Searches with Organism Selection</h5>
          <p class="text-muted mb-3">
            When viewing a group, you can choose which specific organisms to include in your searches:
          </p>
          <ul class="text-muted">
            <li><strong>Selection bars:</strong> Each organism card displays a selection bar at the top</li>
            <li><strong>Check/uncheck organisms:</strong> Click the checkbox icon to toggle individual organisms on or off</li>
            <li><strong>Select All / Deselect All buttons:</strong> Quickly select or deselect all organisms in the group at once</li>
            <li><strong>Visual feedback:</strong> The bar turns blue with a checkmark (✓) when an organism is selected, gray when deselected</li>
            <li><strong>Targeted searches:</strong> Your search will only run on the organisms you have selected</li>
            <li><strong>Default:</strong> All organisms are selected by default when you first view a group</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Viewing Individual Organisms</h5>
          <p class="text-muted mb-3">
            Click on an organism card (anywhere except the checkbox) to visit that organism's dedicated page:
          </p>
          <ul class="text-muted">
            <li><strong>Organism-specific page:</strong> Shows detailed information about that single organism</li>
            <li><strong>Single-organism search:</strong> Search will only run on that one organism, not the group</li>
            <li><strong>Organism details:</strong> View images, annotations, and other organism-specific data</li>
            <li><strong>Dedicated tools:</strong> Access tools tailored for single-organism analysis</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Method 2: Tree Select (Custom Selection)</h4>
          <p class="text-muted mb-3">
            For more precise control, use the interactive taxonomy tree to build a custom set of organisms.
          </p>
          
          <div class="bg-light p-3 rounded mb-3">
            <strong>How to use the Tree Select:</strong>
            <ul class="mb-0 mt-2">
              <li><strong>Click any node</strong> to select or deselect organisms</li>
              <li><strong>Selecting a parent node</strong> automatically selects all organisms below it</li>
              <li><strong>Filter the tree</strong> using the search box to find specific organisms</li>
              <li><strong>View selected organisms</strong> in the sidebar on the right</li>
              <li><strong>Choose a tool</strong> from the Tool Box when ready</li>
            </ul>
          </div>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Understanding the Taxonomy Tree</h4>
          <p class="text-muted mb-3">
            The taxonomy tree is organized hierarchically:
          </p>
          <ul class="text-muted">
            <li><strong>Root level:</strong> Highest taxonomic classifications</li>
            <li><strong>Branches:</strong> Sub-groups organized by evolutionary relationships</li>
            <li><strong>Leaf nodes:</strong> Individual organisms or strains</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Tips for Selection</h4>
          <ul class="text-muted">
            <li>Start with a group card if you're unsure what to select</li>
            <li>Use tree filtering to find organisms by name</li>
            <li>You can select organisms across different branches</li>
            <li>Your selection is shown in the sidebar and persists while you browse</li>
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
