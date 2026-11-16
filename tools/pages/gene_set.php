<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <div class="card-header text-white d-flex align-items-center gap-3" style="background-color:#e11d48;">
          <span class="fw-semibold" style="letter-spacing:0.04em; font-size:0.85rem;"><?= htmlspecialchars($gene_set_name) ?></span>
          <span class="badge bg-white text-gene-set" style="font-size:0.65em; opacity:0.9;"><?= t('search limited to this gene set') ?></span>
        </div>
        <div class="card-body bg-search-light">
          <div class="mb-2 fw-semibold text-uppercase section-eyebrow"><i class="fa fa-search me-1"></i> <?= t('Search Gene IDs and Annotations') ?> <?= help_modal_trigger('search-help', '', 'How to search') ?></div>
          <form id="geneSetSearchForm">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex gap-2 align-items-center">
                  <input type="text" class="form-control moop-input" id="searchKeywords" placeholder="<?= t('Enter gene ID or annotation keywords (minimum 3 characters)...') ?>" required>
                  <button type="submit" class="btn btn-icon btn-search" id="searchBtn" title="<?= t('Search') ?>" data-bs-toggle="tooltip" data-bs-placement="bottom">
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
          'has_expression' => !empty($has_expression),
      ]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-search-results">
        <span class="fw-semibold text-uppercase section-eyebrow"><i class="fa fa-list me-1"></i> Search Results <?= help_modal_trigger('search-results-help', '', 'Understanding your search results') ?></span>
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
        <h1><?= htmlspecialchars($gene_set_name) ?> <span class="badge bg-white text-gene-set ms-1" style="font-size:0.7em; vertical-align:middle; opacity:0.85;"><?= t('Gene Set') ?></span></h1>
        <?php /* Declared, not shown — the heading and badges above already say where you
                 are; this exists so the page declares its PURPOSE for the router. */ ?>
        <?= page_purpose('Search genes within a single gene set, filtering the results by annotation source.', '', false, false) ?>
        <div class="feature-overview-body" style="padding:0;">
          <div class="d-flex flex-column flex-md-row align-items-stretch">

            <!-- Info -->
            <div class="flex-grow-1" style="padding:0.5rem 1.1rem 0.6rem;">
              <?php if (!empty($gene_set_info['gene_set_description'])): ?>
              <p class="text-muted small mb-2"><?= htmlspecialchars($gene_set_info['gene_set_description']) ?></p>
              <?php endif; ?>
              <dl class="feature-info-grid mb-0">
                <dt><?= t('Assembly') ?></dt>
                <dd><a href="<?= moop_url_base() ?>/tools/assembly.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>"><?= htmlspecialchars(assembly_label($genome_name, $genome_accession)) ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                <dt><?= t('Organism') ?></dt>
                <dd><a href="<?= moop_url_base() ?>/tools/organism.php?organism=<?= urlencode($organism_name) ?>"><em class="sci-name"><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em><?php if (!empty($organism_info['common_name'])): ?> (<?= htmlspecialchars($organism_info['common_name']) ?>)<?php endif; ?><i class="fa fa-external-link-alt link-icon"></i></a></dd>
                <?php if (!empty($gene_set_meta['source'])): ?>
                <dt><?= t('Source') ?></dt>
                <dd><?= htmlspecialchars($gene_set_meta['source']) ?></dd>
                <?php endif; ?>
                <?php /* Where the data came from, written in the JSON on the compute box (copy2moop ships it;
                         an edit made on this server is overwritten by the next copy). Free text: URLs link. */ ?>
                <?php if (!empty($gene_set_meta['provenance'])): ?>
                <dt><?= t('Provenance') ?></dt>
                <dd style="white-space: pre-line;"><?= moop_text_with_links((string)$gene_set_meta['provenance']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($gene_set_meta['date_added'])): ?>
                <dt><?= t('Date added') ?></dt>
                <dd><?= htmlspecialchars($gene_set_meta['date_added']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($gene_set_meta['note'])): ?>
                <dt><?= t('Note') ?></dt>
                <dd style="white-space: pre-line;"><?= moop_text_with_links((string)$gene_set_meta['note']) ?></dd>
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
                  <?= t('Feature Counts') . "\n" ?>
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
                    <span class="text-muted fw-semibold ms-1" style="font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase;"><?= t('Genes') ?></span>
                  </div>
                  <div>
                    <span class="fw-bold feature-color-mrna" style="font-size:1.5rem; line-height:1;"><?= number_format($gene_set_info['mrna_count'] ?? 0) ?></span>
                    <span class="text-muted fw-semibold ms-1" style="font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase;"><?= t('Transcripts') ?></span>
                  </div>
                </div>
              </div>

              <?php
              $has_downloads = !empty($fasta_files) || $has_gff || $genome_file;
              ?>
              <?php if ($has_downloads): ?>
              <?php /* Divider + heading + content, the shape the organism and assembly pages
                       use. Without it these four buttons sat under a bare rule with nothing
                       saying what they are -- and "Protein / mRNA / CDS / GFF3" names the
                       formats without saying they are downloads for THIS gene set. */ ?>
              <div class="mt-4 pt-3 border-top">
                <h6 class="text-muted mb-3" style="font-weight: 600;">
                  <?= t('Downloads') . "\n" ?>
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
                <a href="<?= moop_url_base() ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&type=<?= urlencode($f['seq_type']) ?>"
                   class="btn btn-sm <?= $colorInfo['class'] ?> fw-semibold text-white"
                   style="border-radius: 16px; <?= $colorInfo['style'] ?>"
                   download>
                  <i class="fa fa-download me-1"></i><?= htmlspecialchars($f['label']) ?>
                </a>
                <?php endforeach; ?>
                <?php if ($has_gff): ?>
                <a href="<?= moop_url_base() ?>/api/download_file.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&gene_set=<?= urlencode($gene_set_name) ?>&filename=<?= urlencode(genes_gff_filename()) ?>"
                   class="btn btn-sm fw-semibold text-white"
                   style="border-radius: 16px; background-color: #475569; border-color: #475569;"
                   download>
                  <i class="fa fa-download me-1"></i><?= t('GFF3') . "\n" ?>
                </a>
                <?php endif; ?>
                <?php if ($genome_file):
                    $gColor = getColorClassOrStyle($genome_file['info']['color'] ?? '');
                ?>
                <?php /* Last, and labelled with the assembly, because it belongs to the
                         assembly rather than to this gene set -- the ordering says so
                         without needing a second heading. */ ?>
                <a href="<?= moop_url_base() ?>/lib/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($genome_accession) ?>&genome_directory=<?= urlencode($genome_directory) ?>&gene_set=&type=<?= urlencode($genome_file['info']['seq_type'] ?? $genome_file['type']) ?>"
                   class="btn btn-sm <?= $gColor['class'] ?> fw-semibold text-white"
                   style="border-radius: 16px; <?= $gColor['style'] ?>"
                   title="The assembled genome sequence this gene set was called against"
                   download>
                  <i class="fa fa-download me-1"></i><?= t('Genome') . "\n" ?>
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

  <!-- BUSCO completeness: only when the pipeline shipped a busco.json for this gene set. -->
  <?php if (!empty($busco)):
      $busco_subject = 'gene set';
      $busco_color   = '#e11d48';
      $__tucked = ConfigManager::getInstance()->technicalPanelsTucked();
      if ($__tucked) include __DIR__ . '/../../includes/researchers_open.php';
      include __DIR__ . '/../../includes/busco_card.php';
      if ($__tucked) echo "</div></details>\n";
  endif; ?>

  <!-- Annotation Summary -->
  <?php if (!empty($annot_type_totals)): ?>
  <div class="row mb-5" id="annotationSummary">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center" style="background-color:#e11d48;">
          <i class="fas fa-tag me-2 text-white"></i>
          <span class="text-uppercase fw-semibold text-white section-eyebrow"><?= t('Gene Set Annotation Summary') ?></span>
          <span class="ms-2 small text-white-50"><?= t('genes per annotation type') ?></span>
          <?php /* Nothing on the page connected these numbers to the search box above them.
                   A reader sees 129,866 homologs and has no reason to know the text of those
                   annotations is what the search box searches, nor that the filter icon can
                   narrow a search to one kind. The sources in that filter are grouped by
                   exactly these types (getAnnotationSourcesGrouped), so the two line up. */ ?>
          <span class="ms-2"><?= field_help(
              'How many genes in this gene set carry at least one annotation of each type. The '
              . 'search box above searches the text of those annotations, so a search for "kinase" looks '
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
              <div class="fw-bold text-secondary" style="font-size:1.2rem; line-height:1;" title="<?= t('genes with this annotation type') ?>"><?= number_format($total) ?></div>
              <div class="text-muted fw-semibold mt-1" style="font-size:0.7rem; letter-spacing:0.06em; text-transform:uppercase;"><?= htmlspecialchars($type) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- KEGG pathways: genes and KOs of this gene set per pathway (lib/kegg.php) -->
  <?php if ($kegg_error || !empty($kegg['rows'])):
      $kegg_ctx = ['organism' => $organism_name, 'assembly' => $genome_accession, 'gene_set' => $gene_set_name]; ?>
  <div class="row mb-5" id="keggPathways">
    <div class="col-12">
      <?php /* Closed by default (Sofia, 2026-11-14): its title, or the sidebar, opens it */ ?>
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center flex-wrap" style="background-color:#e11d48;">
          <button type="button" class="btn btn-link p-0 text-white text-decoration-none kegg-card-toggle collapsed"
                  data-bs-toggle="collapse" data-bs-target="#keggPathwaysBody" aria-expanded="false" aria-controls="keggPathwaysBody">
            <i class="fa fa-chevron-right kegg-chevron me-1" aria-hidden="true"></i><i class="fas fa-project-diagram me-2"></i><span class="text-uppercase fw-semibold section-eyebrow"><?= t('KEGG Pathways') ?></span>
          </button>
          <?php if (!empty($kegg['rows'])): ?>
          <span class="ms-2 small text-white-50"><?= t('%d pathways', count($kegg['rows'])) ?></span>
          <?php endif; ?>
          <span class="ms-2"><?= field_help(
              'Each row is a KEGG pathway with at least one gene of this gene set in it. Genes: genes given the '
              . 'pathway by eggNOG-mapper. KOs found: of the pathway\'s KEGG Orthology groups, how many these genes '
              . 'have; coverage is that share. A low coverage in a fragmented gene set can mean "not annotated" '
              . 'rather than "absent" -- compare with the BUSCO score. "Map" opens KEGG\'s own drawing of the '
              . 'pathway with this gene set\'s KOs coloured; "Genes" lists them in the search.',
              'KEGG pathways'
          ) ?></span>
        </div>
        <div class="card-body collapse" id="keggPathwaysBody">
          <?php if ($kegg_error): ?>
            <div class="alert alert-warning mb-0"><?= t('The KEGG pathway data could not be read just now. Please try again later.') ?></div>
          <?php else: ?>
            <div class="small text-muted mb-2">
              <?= t('"Map" opens KEGG\'s map with the boxes coloured:') ?>
              <?= moop_kegg_map_legend_html(['this_set', 'other_set']) ?>
              <?php if (!$kegg['has_reference']): ?>
                <br><?= t('Coverage is not shown: this site has no copy of the KEGG pathway reference.') ?>
              <?php elseif ($kegg['reference_date'] !== ''): ?>
                <br><?= t('KEGG pathway reference of %s.', htmlspecialchars($kegg['reference_date'])) ?>
              <?php endif; ?>
            </div>
            <div class="mb-2">
              <label for="keggClassFilter" class="small text-muted me-1"><?= t('Class:') ?></label>
              <select id="keggClassFilter" data-kegg-filter="keggPathwaysTable" class="form-select form-select-sm d-inline-block w-auto">
                <option value=""><?= t('All classes') ?></option>
                <?php foreach (array_keys(moop_kegg_sort_classes(array_fill_keys(array_unique(array_column($kegg['rows'], 'class')), 1))) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="table-responsive">
            <table id="keggPathwaysTable" class="table table-sm table-striped table-hover kegg-pathways-dt" style="width:100%;">
              <thead><tr>
                <th class="kegg-class-col"><?= t('Class') ?></th>
                <th><?= t('Pathway') ?></th>
                <th class="text-end kegg-sort-col"><?= t('Genes') ?></th>
                <th class="text-end" title="<?= t('Genes eggNOG gave more than one possible KO; the pathway may come from any of them') ?>"><?= t('With several KOs') ?></th>
                <th class="text-end"><?= t('KOs found') ?></th>
                <?php if ($kegg['has_reference']): ?>
                <th class="text-end"><?= t('KOs in pathway') ?></th>
                <th class="text-end"><?= t('Coverage') ?></th>
                <?php endif; ?>
                <th class="no-sort"><?= t('Links') ?></th>
              </tr></thead>
              <tbody>
              <?php foreach ($kegg['rows'] as $r):
                  $found = count($r['kos_found']);
                  $cov   = $r['kos_total'] ? $found / $r['kos_total'] : null;
                  $map   = moop_url('api/kegg_map.php?' . http_build_query($kegg_ctx + ['pathway' => $r['pathway']]));
                  $genes = moop_url('tools/search.php?' . http_build_query($kegg_ctx + ['q' => $r['pathway'], 'source' => MOOP_KEGG_PATHWAY_SOURCE])); ?>
                <tr>
                  <td data-search="<?= htmlspecialchars($r['class']) ?>"><?= htmlspecialchars($r['class']) ?><?php if ($r['sub'] !== ''): ?><div class="small text-muted"><?= htmlspecialchars($r['sub']) ?></div><?php endif; ?></td>
                  <td><?= htmlspecialchars($r['name']) ?> <span class="small text-muted"><?= htmlspecialchars($r['pathway']) ?></span></td>
                  <td class="text-end" data-order="<?= (int)$r['genes'] ?>"><?= number_format($r['genes']) ?></td>
                  <td class="text-end text-muted"><?= (int)$r['ambiguous_genes'] ?></td>
                  <td class="text-end"><?= $found ?></td>
                  <?php if ($kegg['has_reference']): ?>
                  <td class="text-end"><?= $r['kos_total'] === null ? '—' : (int)$r['kos_total'] ?></td>
                  <td class="text-end" data-order="<?= $cov === null ? -1 : round($cov, 4) ?>"><?= $cov === null ? '—' : round($cov * 100) . '%' ?></td>
                  <?php endif; ?>
                  <td class="text-nowrap">
                    <a href="<?= htmlspecialchars($map) ?>" target="_blank" rel="noopener"><?= t('Map') ?> <i class="fa fa-external-link-alt link-icon"></i></a>
                    · <a href="<?= htmlspecialchars($genes) ?>"><?= t('Genes') ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Moopmart tip -->
<?php if (moop_tool_on('moopmart')): ?>
  <div class="row mb-5">
    <div class="col-12">
      <p class="text-muted small mb-0">
        <i class="fas fa-lightbulb me-1" style="color:var(--moop-tool-header);"></i>
        <strong><?= t('Tip:') ?></strong> <?= t_markup('To download some or all of the annotations for this gene set, visit %s.',
            '<a href="' . moop_url_base() . '/tools/moopmart.php?organism=' . urlencode($organism_name) . '&assembly=' . urlencode($genome_accession) . '&gene_set=' . urlencode($gene_set_name) . '">MOOPmart</a>') . "\n" ?>
        <?= t('Build a gene list from this gene set and then select the annotations you want to bulk download.') . "\n" ?>
      </p>
    </div>
  </div>
<?php endif; ?>


</div>

<?php /* Shared results help — ONE home for the explanation, included by every page
        that renders a results table. Opened by the trigger on the section header above. */ ?>
<?php include_once __DIR__ . '/../../includes/search_results_modal.php'; ?>

<?php /* Shared search-box help — ONE home, included by every page with a search
        box. 'multi' pages search several organisms at once and get the organism
        selection card plus the per-organism phrasing of the result cap. */ ?>
<?php $search_help_scope = 'single';
      include __DIR__ . '/../../includes/search_help_modal.php'; ?>
