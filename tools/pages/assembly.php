<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#d97706;">
          <span class="fw-semibold" style="letter-spacing:0.04em; font-size:0.85rem;"><?= htmlspecialchars(assembly_label($assembly_info['genome_name'] ?? '', $assembly_info['genome_accession'] ?? '')) ?></span>
          <span class="badge bg-white text-assembly" style="font-size:0.65em; opacity:0.9;">search limited to this <?= gloss('assembly') ?></span>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-1"></i> Search Gene IDs and Annotations <?= help_modal_trigger('search-help', '', 'How to search') ?></div>
          <form id="assemblySearchForm">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex gap-2 align-items-center">
                  <input type="text" class="form-control moop-input" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
                  <button type="submit" class="btn btn-icon btn-search" id="searchBtn" title="Search" data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Tools Column -->
    <div class="col-lg-4">
      <?php
      $context = createToolContext('assembly', [
          'organism'     => $organism_name,
          'assembly'     => $assembly_accession,
          'display_name' => $assembly_info['genome_name'],
      ]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-search-results">
        <span class="fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-list me-1"></i> Search Results <?= help_modal_trigger('search-results-help', '', 'Understanding your search results') ?></span>
      </div>

      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <!-- Assembly Header Section with Info -->
  <?php
  list($genome_id, $genome_name, $genome_accession) = getAssemblyInfo($assembly_accession, $db_path);
  $fasta_files     = getAssemblyFastaFiles($organism_name, $genome_name);
  $genome_directory = $genome_name;
  if (empty($fasta_files)) {
      $fasta_files      = getAssemblyFastaFiles($organism_name, $genome_accession);
      $genome_directory = $genome_accession;
  }
  // Pick out the genome.fa (assembly-level, gene_set = '')
  $genome_file = null;
  foreach ($fasta_files as $ftype => $finfo) {
      if (($finfo['gene_set'] ?? '') === '') {
          $genome_file = ['type' => $ftype, 'info' => $finfo];
          break;
      }
  }
  $image_data  = getOrganismImageWithCaption($organism_info, $images_path, $absolute_images_path);
  $image_src   = $image_data['image_path'];
  $image_info  = ['caption' => $image_data['caption'], 'link' => $image_data['link']];
  $show_image  = !empty($image_src);
  $image_alt   = htmlspecialchars($organism_info['common_name'] ?? $organism_name);
  ?>

  <div class="row mb-4" id="assemblyHeader">
    <?php if ($show_image): ?>
      <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
          <img src="<?= $image_src ?>" class="card-img-top" alt="<?= $image_alt ?>">
          <?php if (!empty($image_info['caption'])): ?>
            <div class="card-body">
              <p class="card-text small text-muted">
                <?php if (!empty($image_info['link'])): ?>
                  <a href="<?= $image_info['link'] ?>" target="_blank" class="text-decoration-none">
                    <?= $image_info['caption'] ?> <i class="fa fa-external-link-alt fa-xs"></i>
                  </a>
                <?php else: ?>
                  <?= $image_info['caption'] ?>
                <?php endif; ?>
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="<?= $show_image ? 'col-md-8' : 'col-12' ?>">
      <div class="feature-header assembly-header-custom shadow">
        <h1><?= htmlspecialchars($assembly_info['genome_name']) ?> <span class="badge bg-white text-assembly ms-1" style="font-size:0.7em; vertical-align:middle; opacity:0.85;">Assembly</span></h1>
        <div class="feature-overview-body">
          <dl class="feature-info-grid mb-0">
            <dt>Accession</dt>
            <dd><?= htmlspecialchars($assembly_info['genome_accession']) ?></dd>
            <dt>Organism</dt>
            <?php /* Identical shape to the gene set page's Organism row: binomial in
                     .sci-name, common name after it in parentheses and NOT italic (a common
                     name is not a scientific name), then the external-link icon. The icon
                     used to sit inside the <em>, so it was being italicised too. This was
                     the last page that named an organism without its common name. */ ?>
            <dd><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>"><em class="sci-name"><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em><?php if (!empty($organism_info['common_name'])): ?> (<?= htmlspecialchars($organism_info['common_name']) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
            <?php if (!empty($assembly_meta['source'])): ?>
            <dt>Source</dt>
            <dd><?= htmlspecialchars($assembly_meta['source']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($assembly_meta['date_added'])): ?>
            <dt>Date added</dt>
            <dd><?= htmlspecialchars($assembly_meta['date_added']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($assembly_meta['note'])): ?>
            <dt>Note</dt>
            <dd><?= htmlspecialchars($assembly_meta['note']) ?></dd>
            <?php endif; ?>
            <?php /* Gene sets live HERE, in the overview, not in a full-width card of their
                     own below. Measured across all 85 organisms: no assembly has more than
                     one gene set -- six organisms have 2-3, but each on a DIFFERENT
                     assembly. So the card was a list-group, a count badge and a chevron
                     built for a list that is always one row, and being full width it put
                     ~800px between the gene set's name and its own gene count. A chip
                     keeps the two together and scales to several without changing shape. */ ?>
          </dl>

          <?php /* Divider + section heading + chips -- the same shape the organism page uses
                   for Taxonomy Lineage, Member of Groups and Assemblies, so a reader moving
                   organism -> assembly meets one pattern rather than two. */ ?>
          <?php if (!empty($gene_sets)): ?>
          <div class="mt-4 pt-3 border-top">
            <h6 class="text-muted mb-3" style="font-weight: 600;">
              <?= count($gene_sets) === 1 ? 'Gene Set' : 'Gene Sets' ?>
              <?= field_help(
                  'A gene set is one round of gene predictions for this assembly. The counts '
                  . 'are how many genes and transcripts it contains. Click one to open it.',
                  'Gene sets'
              ) ?>
            </h6>
            <div class="chip-container" id="assemblyGeneSets">
              <?php foreach ($gene_sets as $gs): ?>
                <a href="/<?= $site ?>/tools/gene_set.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_info['genome_accession']) ?>&gene_set=<?= urlencode($gs['gene_set_name']) ?>"
                   class="gene-set-chip">
                  <span class="fw-semibold"><?= htmlspecialchars($gs['gene_set_name']) ?></span>
                  <span class="gene-set-chip-counts">
                    <strong class="feature-color-gene"><?= number_format($gs['gene_count']) ?></strong> genes
                    &middot;
                    <strong class="feature-color-mrna"><?= number_format($gs['mrna_count']) ?></strong> transcripts
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
            <?php foreach ($gene_sets as $gs): if (!empty($gs['gene_set_description'])): ?>
              <div class="small text-muted mt-1"><?= htmlspecialchars($gs['gene_set_description']) ?></div>
            <?php endif; endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if ($genome_file):
            $colorInfo = getColorClassOrStyle($genome_file['info']['color'] ?? '');
          ?>
          <div class="mt-4 pt-3 border-top">
            <h6 class="text-muted mb-3" style="font-weight: 600;">
              Genome FASTA
              <?= field_help(
                  'The assembled genome sequence for this build, as a FASTA file. It is the '
                  . 'sequence itself, without gene annotations -- those come from a gene set.',
                  'Genome FASTA'
              ) ?>
            </h6>
            <?php /* Labelled with the ASSEMBLY, not the file type. "Genome" told the user
                     what kind of thing it was, which the heading above now says; the name
                     and accession tell them WHICH build they are about to download -- the
                     thing that actually differs when an organism has several. Through
                     assembly_label() so it reads the same here as in the page title and
                     everywhere else an assembly is named. */ ?>
            <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_accession) ?>&genome_directory=<?= urlencode($genome_directory) ?>&gene_set=&type=<?= urlencode($genome_file['info']['seq_type'] ?? $genome_file['type']) ?>"
               class="btn btn-sm <?= $colorInfo['class'] ?> fw-semibold text-white"
               style="border-radius: 16px; <?= $colorInfo['style'] ?>"
               download>
              <i class="fa fa-download me-1"></i><?= htmlspecialchars(assembly_label($assembly_info['genome_name'] ?? '', $assembly_info['genome_accession'] ?? '')) ?>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>


</div>

<?php /* Shared results help — ONE home for the explanation, included by every page
        that renders a results table. Opened by the trigger on the section header above. */ ?>
<?php include_once __DIR__ . '/../../includes/search_results_modal.php'; ?>

<?php /* Shared search-box help — ONE home, included by every page with a search
        box. 'multi' pages search several organisms at once and get the organism
        selection card plus the per-organism phrasing of the result cap. */ ?>
<?php $search_help_scope = 'single';
      include __DIR__ . '/../../includes/search_help_modal.php'; ?>
