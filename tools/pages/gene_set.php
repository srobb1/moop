<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <div class="card-header text-white d-flex align-items-center justify-content-between" style="background-color:#e11d48;">
          <span class="fw-semibold" style="letter-spacing:0.04em; font-size:0.85rem;"><?= htmlspecialchars($gene_set_name) ?></span>
          <span class="badge bg-white text-gene-set" style="font-size:0.65em; opacity:0.9;">search limited to this gene set</span>
        </div>
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-search me-1"></i> Search Gene IDs and Annotations <?= help_modal_trigger('search-help', '', 'How to search') ?></div>
          <form id="geneSetSearchForm">
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
      $context = createToolContext('gene_set', [
          'organism'     => $organism_name,
          'assembly'     => $genome_accession,
          'gene_set'     => $gene_set_name,
          'display_name' => $gene_set_name,
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

  <!-- Gene Set Header -->
  <?php
  /* The organism's picture, as on the organism and assembly pages. It is the same image on
     all three, which is the point: it says WHICH organism you are inside without reading a
     word, and the three pages are otherwise near-identical slabs of metadata. */
  $image_data = getOrganismImageWithCaption($organism_info, $images_path, $absolute_images_path);
  $image_src  = $image_data['image_path'];
  $image_info = ['caption' => $image_data['caption'], 'link' => $image_data['link']];
  $show_image = !empty($image_src);
  $image_alt  = htmlspecialchars($organism_info['common_name'] ?? $organism_name);
  ?>
  <div class="row mb-4" id="geneSetHeader">
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
      <div class="feature-header gene-set-header-custom shadow">
        <h1><?= htmlspecialchars($gene_set_name) ?> <span class="badge bg-white text-gene-set ms-1" style="font-size:0.7em; vertical-align:middle; opacity:0.85;">Gene Set</span></h1>
        <div class="feature-overview-body" style="padding:0;">
          <div class="d-flex flex-column flex-md-row align-items-stretch">

            <!-- Info -->
            <div class="flex-grow-1" style="padding:0.5rem 1.1rem 0.6rem;">
              <?php if (!empty($gene_set_info['gene_set_description'])): ?>
              <p class="text-muted small mb-2"><?= htmlspecialchars($gene_set_info['gene_set_description']) ?></p>
              <?php endif; ?>
              <dl class="feature-info-grid mb-0">
                <dt>Assembly</dt>
                <dd><a href="/<?= $site ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>"><?= htmlspecialchars(assembly_label($genome_name, $genome_accession)) ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                <dt>Organism</dt>
                <dd><a href="/<?= $site ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>"><em class="sci-name"><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em><?php if (!empty($organism_info['common_name'])): ?> (<?= htmlspecialchars($organism_info['common_name']) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                <?php if (!empty($gene_set_meta['source'])): ?>
                <dt>Source</dt>
                <dd><?= htmlspecialchars($gene_set_meta['source']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($gene_set_meta['date_added'])): ?>
                <dt>Date added</dt>
                <dd><?= htmlspecialchars($gene_set_meta['date_added']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($gene_set_meta['note'])): ?>
                <dt>Note</dt>
                <dd><?= htmlspecialchars($gene_set_meta['note']) ?></dd>
                <?php endif; ?>
              </dl>
              <?php /* Counts live in the flow of the card, above Downloads, not in a panel
                       pinned to the right edge. As a right-hand panel they were vertically
                       centred against whatever the left column happened to be, so they
                       drifted as the info grid and downloads changed height -- and they
                       read as a separate widget rather than as a property of the gene set,
                       which is what they are. */ ?>
              <div class="mt-4 pt-3 border-top">
                <h6 class="text-muted mb-3" style="font-weight: 600;">
                  Feature Counts
                  <?= field_help(
                      'How many genes this gene set contains, and how many transcripts they '
                      . 'produce. One gene can have several transcripts, so transcripts are '
                      . 'usually the larger number.',
                      'Feature counts'
                  ) ?>
                </h6>
                <div class="d-flex flex-wrap gap-4">
                  <div>
                    <span class="fw-bold feature-color-gene" style="font-size:1.5rem; line-height:1;"><?= number_format($gene_set_info['gene_count'] ?? 0) ?></span>
                    <span class="text-muted fw-semibold ms-1" style="font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase;">Genes</span>
                  </div>
                  <div>
                    <span class="fw-bold feature-color-mrna" style="font-size:1.5rem; line-height:1;"><?= number_format($gene_set_info['mrna_count'] ?? 0) ?></span>
                    <span class="text-muted fw-semibold ms-1" style="font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase;">Transcripts</span>
                  </div>
                </div>
              </div>

              <?php
              $gs_dir = $config->getPath('organism_data') . "/$organism_name/$genome_accession/$gene_set_name";
              $gff_name = genes_gff_filename();
              $has_gff = file_exists("$gs_dir/$gff_name") && filesize("$gs_dir/$gff_name") > 0;

              // The ASSEMBLY's genome FASTA, offered here as well as on the assembly page.
              // Someone working with a gene set usually wants the sequence it was called
              // against, and making them navigate up a level to get it is a step for
              // nothing. Same file, same handler -- gene_set='' is what marks it
              // assembly-level, which is also how the assembly page finds it.
              $genome_file = null;
              $genome_files = getAssemblyFastaFiles($organism_name, $genome_name ?? $genome_accession);
              if (empty($genome_files)) {
                  $genome_files = getAssemblyFastaFiles($organism_name, $genome_accession);
              }
              foreach ($genome_files as $gtype => $ginfo) {
                  if (($ginfo['gene_set'] ?? '') === '') {
                      $genome_file = ['type' => $gtype, 'info' => $ginfo];
                      break;
                  }
              }
              $has_downloads = !empty($fasta_files) || $has_gff || $genome_file;
              ?>
              <?php if ($has_downloads): ?>
              <?php /* Divider + heading + content, the shape the organism and assembly pages
                       use. Without it these four buttons sat under a bare rule with nothing
                       saying what they are -- and "Protein / mRNA / CDS / GFF3" names the
                       formats without saying they are downloads for THIS gene set. */ ?>
              <div class="mt-4 pt-3 border-top">
                <h6 class="text-muted mb-3" style="font-weight: 600;">
                  Downloads
                  <?= field_help(
                      'Sequence and annotation files for this gene set. Protein, mRNA and CDS '
                      . 'are FASTA; GFF3 carries the gene models and their coordinates.',
                      'Downloads'
                  ) ?>
                </h6>
                <div class="d-flex flex-wrap gap-2">
                <?php foreach ($fasta_files as $f):
                    $colorInfo = getColorClassOrStyle($f['color'] ?? '');
                ?>
                <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&type=<?= urlencode($f['seq_type']) ?>"
                   class="btn btn-sm <?= $colorInfo['class'] ?> fw-semibold text-white"
                   style="border-radius: 16px; <?= $colorInfo['style'] ?>"
                   download>
                  <i class="fa fa-download me-1"></i><?= htmlspecialchars($f['label']) ?>
                </a>
                <?php endforeach; ?>
                <?php if ($has_gff): ?>
                <a href="/<?= $site ?>/api/download_file.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&filename=<?= urlencode(genes_gff_filename()) ?>"
                   class="btn btn-sm fw-semibold text-white"
                   style="border-radius: 16px; background-color: #475569; border-color: #475569;"
                   download>
                  <i class="fa fa-download me-1"></i>GFF3
                </a>
                <?php endif; ?>
                <?php if ($genome_file):
                    $gColor = getColorClassOrStyle($genome_file['info']['color'] ?? '');
                    $genome_directory = !empty($genome_files) && isset($genome_name) ? $genome_name : $genome_accession;
                ?>
                <?php /* Last, and labelled with the assembly, because it belongs to the
                         assembly rather than to this gene set -- the ordering says so
                         without needing a second heading. */ ?>
                <a href="/<?= $site ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&genome_directory=<?= urlencode($genome_directory) ?>&gene_set=&type=<?= urlencode($genome_file['info']['seq_type'] ?? $genome_file['type']) ?>"
                   class="btn btn-sm <?= $gColor['class'] ?> fw-semibold text-white"
                   style="border-radius: 16px; <?= $gColor['style'] ?>"
                   title="The assembled genome sequence this gene set was called against"
                   download>
                  <i class="fa fa-download me-1"></i>Genome
                </a>
                <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Annotation Summary -->
  <?php if (!empty($annot_type_totals)): ?>
  <div class="row mb-5" id="annotationSummary">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center" style="background-color:#e11d48;">
          <i class="fas fa-tag me-2 text-white"></i>
          <span class="text-uppercase fw-semibold text-white" style="letter-spacing:0.1em; font-size:0.8rem;">Annotation Summary</span>
          <?php /* Nothing on the page connected these numbers to the search box above them.
                   A reader sees 129,866 homologs and has no reason to know the text of those
                   annotations is what the search box searches, nor that the filter icon can
                   narrow a search to one kind. The sources in that filter are grouped by
                   exactly these types (getAnnotationSourcesGrouped), so the two line up. */ ?>
          <span class="ms-2"><?= field_help(
              'These are the annotations attached to this gene set, counted by type. The '
              . 'search box above searches their text, so a search for "kinase" looks '
              . 'through all of them. To search just one kind, open the filter beside the '
              . 'search box and pick its sources.',
              'Annotation summary'
          ) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="d-flex flex-wrap">
            <?php /* flex-fill so the cells SHARE the row. Fixed-width cells left the row
                     short of the card edge, and the last cell's right border then read as
                     the start of an empty eighth column -- a cell that looked like missing
                     data rather than leftover space. */ ?>
            <?php foreach ($annot_type_totals as $type => $total): ?>
            <div class="text-center p-3 flex-fill" style="min-width:120px; border-right:1px solid #dee2e6; border-bottom:1px solid #dee2e6;">
              <div class="fw-bold text-secondary" style="font-size:1.2rem; line-height:1;"><?= number_format($total) ?></div>
              <div class="text-muted fw-semibold mt-1" style="font-size:0.7rem; letter-spacing:0.06em; text-transform:uppercase;"><?= htmlspecialchars($type) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Moopmart tip -->
  <div class="row mb-5">
    <div class="col-12">
      <p class="text-muted small mb-0">
        <i class="fas fa-lightbulb me-1" style="color:#0891b2;"></i>
        <strong>Tip:</strong> To download some or all of the annotations for this gene set, visit
        <a href="/<?= $site ?>/tools/moopmart.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>">MOOPmart</a>.
        Build a gene list from this gene set and then select the annotations you want to bulk download.
      </p>
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
