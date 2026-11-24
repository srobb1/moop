<?php
/**
 * ABOUT - Content File
 *
 * Variables (about.php):
 * - $siteTitle:  site title from config
 * - $site_about: null, or ['title' => ..., 'html' => ...] -- this site's own "about" content page
 *                (Admin -> Pages, slug "about"), already rendered and cleaned by moop_page_render()
 *
 * The site section comes first: a visitor asking "what is this site" means the data, not the
 * software. "About MOOP" is shared by every MOOP site and translated through t(); the references
 * are bibliographic data, so they are printed as written, never translated.
 */

// Software MOOP runs, with the paper to cite. Every DOI checked against doi.org + Crossref, 2026-11-23.
$software = [
    ['JBrowse 2', t_plain('Genome browser (with the JBrowse command-line tools)'),
     'Diesh C, et al. (2023) JBrowse 2: a modular genome browser with views of synteny and structural variation. Genome Biology 24:66.',
     'https://doi.org/10.1186/s13059-023-02914-z'],
    ['jbrowse-plugin-mafviewer', t_plain('Multiple-genome alignments in the genome browser'),
     'Diesh C. jbrowse-plugin-mafviewer, a JBrowse 2 plugin.',
     'https://github.com/cmdcolin/jbrowse-plugin-mafviewer'],
    ['BLAST+', t_plain('BLAST searches and sequence retrieval'),
     'Camacho C, et al. (2009) BLAST+: architecture and applications. BMC Bioinformatics 10:421.',
     'https://doi.org/10.1186/1471-2105-10-421'],
    ['Primer3', t_plain('Primer design'),
     'Untergasser A, et al. (2012) Primer3 — new capabilities and interfaces. Nucleic Acids Research 40(15):e115.',
     'https://doi.org/10.1093/nar/gks596'],
    ['SAMtools', t_plain('Sequence lookup by position (faidx)'),
     'Danecek P, et al. (2021) Twelve years of SAMtools and BCFtools. GigaScience 10(2):giab008.',
     'https://doi.org/10.1093/gigascience/giab008'],
    ['HTSlib', t_plain('Compressed, indexed gene tracks (bgzip, tabix)'),
     'Bonfield JK, et al. (2021) HTSlib: C library for reading/writing high-throughput sequencing data. GigaScience 10(2):giab007.',
     'https://doi.org/10.1093/gigascience/giab007'],
    ['Plotly.js', t_plain('Interactive plots (expression, spatial atlas)'),
     'Plotly Technologies Inc. Plotly.js, open-source graphing library.',
     'https://plotly.com/javascript/'],
];

// Data from other resources that MOOP shows alongside each site's own data.
$data_sources = [
    ['KEGG', t_plain('Pathway maps and KEGG Orthology'),
     'Kanehisa M, et al. (2025) KEGG: biological systems database as a model of the real world. Nucleic Acids Research 53(D1):D672–D677.',
     'https://doi.org/10.1093/nar/gkae909'],
    // eggNOG-mapper is what places our genes in KEGG Orthology groups and pathways (lib/kegg.php), and the
    // source of the "EggNOG (EggNOG2GO)" GO terms -- credited next to KEGG because the maps depend on it.
    ['eggNOG-mapper', t_plain('Places genes in KEGG Orthology groups and pathways, and adds GO terms'),
     'Cantalapiedra CP, et al. (2021) eggNOG-mapper v2: functional annotation, orthology assignments, and domain prediction at the metagenomic scale. Molecular Biology and Evolution 38(12):5825–5829.',
     'https://doi.org/10.1093/molbev/msab293'],
    ['Gene Ontology', t_plain('GO terms and the ontology browser'),
     'Ashburner M, et al. (2000) Gene Ontology: tool for the unification of biology. Nature Genetics 25:25–29. — The Gene Ontology Consortium (2023) The Gene Ontology knowledgebase in 2023. Genetics 224(1):iyad031.',
     'https://doi.org/10.1093/genetics/iyad031'],
    ['PLANA', t_plain('Planarian anatomy terms'),
     'Nowotarski SH, et al. (2021) Planarian Anatomy Ontology: a resource to connect data within and across experimental platforms. Development 148(15):dev196097.',
     'https://doi.org/10.1242/dev.196097'],
    ['NCBI Taxonomy', t_plain('Organism names and lineages'),
     'Schoch CL, et al. (2020) NCBI Taxonomy: a comprehensive update on curation, resources and tools. Database 2020:baaa062.',
     'https://doi.org/10.1093/database/baaa062'],
    ['Wikipedia', t_plain('Organism summaries where a site has not written its own (CC BY-SA 4.0)'),
     'Wikipedia contributors. Text used under the Creative Commons Attribution-ShareAlike 4.0 licence.',
     'https://creativecommons.org/licenses/by-sa/4.0/'],
];

$credit_list = function (array $rows): string {
    $out = '';
    foreach ($rows as [$name, $role, $ref, $url]) {
        $out .= '<li class="mb-2"><strong>' . htmlspecialchars($name) . '</strong> — ' . htmlspecialchars($role)
              . '<br><small class="text-muted">' . htmlspecialchars($ref) . ' <a href="' . htmlspecialchars($url)
              . '" target="_blank" rel="noopener">' . htmlspecialchars(preg_replace('#^https://#', '', $url)) . '</a></small></li>';
    }
    return $out;
};
?>
<div class="container py-5">
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?= htmlspecialchars($siteTitle) ?></h1>
  </div>

  <div class="row g-4 justify-content-center mb-5">
    <div class="col-md-12 col-lg-9">

      <?php if ($site_about): ?>
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4 p-lg-5">
          <h2 class="card-title fw-bold text-dark mb-4"><?= htmlspecialchars($site_about['title']) ?></h2>
          <div class="content-page-body"><?= $site_about['html'] ?></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4 p-lg-5">
          <h2 class="card-title fw-bold text-dark mb-4"><?= t('About MOOP') ?></h2>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('What is MOOP?') ?></h5>
            <p class="text-muted">
              <?= t_markup('%s (Multi-Organism Omics Platform) is the software that runs this site. It shows genome assemblies, genes, transcripts and their functional annotations for many organisms side by side, and connects them to a genome browser, BLAST and other tools.', '<strong>MOOP</strong>') . "\n" ?>
            </p>
            <p class="text-muted">
              <?= t('Sofia Robb built MOOP from scratch at the Stowers Institute for Medical Research. The same software runs several sites, each with its own organisms and its own look.') . "\n" ?>
            </p>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('What you can do') ?></h5>
            <ul class="text-muted">
              <li><?= t('Search genes by name, ID or annotation, in one organism or across related species') ?></li>
              <li><?= t('Run BLAST against genomes, transcripts and proteins') ?></li>
              <li><?= t('Download sequences for a list of genes, or a whole gene set') ?></li>
              <li><?= t('Design primers and check them against the genome') ?></li>
              <li><?= t('Browse genomes, gene models and data tracks in JBrowse 2') ?></li>
              <li><?= t('Explore gene expression, KEGG pathways and ontology terms') ?></li>
            </ul>
          </div>

          <?php /* Access levels have one home, help.php?topic=access; this keeps only the pointer. */ ?>
          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('Who can see what') ?></h5>
            <p class="text-muted">
              <?= t('Not every organism is public, so the lists you see may be shorter than the full contents of the site.') . "\n" ?>
              <a href="<?= moop_url_base() ?>/help.php?topic=access" class="text-decoration-none"><?= t('Who can see what, and how to get access') ?></a>.
            </p>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('Software MOOP uses') ?></h5>
            <p class="text-muted small mb-2"><?= t('If a result from one of these tools is part of your work, please cite the tool as well.') ?></p>
            <ul class="list-unstyled"><?= $credit_list($software) ?></ul>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('Data from other resources') ?></h5>
            <ul class="list-unstyled"><?= $credit_list($data_sources) ?></ul>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('Questions?') ?></h5>
            <p class="text-muted">
              <?= t_markup('Use the %s — a person reads every one.', '<a href="' . moop_url('tools/feedback.php') . '" class="text-decoration-none">' . t('contact form') . '</a>') . "\n" ?>
            </p>
          </div>

          <div class="mt-5 pt-4 border-top">
            <h5 class="fw-semibold text-dark mb-2"><i class="fab fa-github"></i> <?= t('Open source') ?></h5>
            <p class="text-muted mb-0">
              <?= t_markup('MOOP is open source. Visit the %s to view the code or report a problem.',
                  '<a href="https://github.com/srobb1/moop" target="_blank" rel="noopener" class="text-decoration-none">' . t('GitHub repository') . '</a>') . "\n" ?>
            </p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
