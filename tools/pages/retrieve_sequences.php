<?php
/**
 * RETRIEVE SEQUENCES - Content File
 * 
 * Variables available (extracted from $data array by render_display_page):
 * - $accessible_sources
 * - $sources_by_group
 * - $context_organism
 * - $context_assembly
 * - $context_group
 * - $filter_organisms
 * - $download_error_msg
 * - $uniquenames
 * - $found_ids
 * - $selected_organism
 * - $selected_assembly_accession
 * - $selected_assembly_name
 * - $uniquenames_string
 * - $displayed_content
 * - $sequence_types
 * - $available_sequences
 * - $selected_source
 * - $examples  ['ids', 'gene', 'transcript'] -- moop_retrieval_examples()
 * - $sequence_notes, $upstream_bp, $downstream_bp
 * - $parent_to_children
 * - $site
 */
?>

<div class="container mt-5">

    <!-- Page header -->
    <div class="card shadow-sm mb-4">
      <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
        <?php /* This page's title, and the document had none at all until audit #9 --
                 which was then fixed by writing the eyebrow classes out by hand here.
                 page_title() emits the same markup from one place; measured identical. */ ?>
        <?= page_title('Sequence Retrieval', 'fa fa-dna') ?>
        <?= help_modal_trigger('retrieve-help', '', 'How to use Sequence Retrieval') ?>
          <?= page_purpose('Look up sequences by feature ID across any accessible assembly. Enter gene or transcript IDs — whole, or a range of positions — and retrieve their protein, mRNA, CDS and genomic sequences, with flanking sequence if you want it.', '', false, false) ?>
      </div>
    </div>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong><?= t('No accessible assemblies found.') ?></strong>
            <p class="mb-0"><?= t('You do not have access to any organism assemblies, or the data directory is misconfigured.') ?></p>
        </div>
    <?php else: ?>

        <?php if (!empty($download_error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> <?= t('Error:') ?></strong> <?= htmlspecialchars($download_error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('Close') ?>"></button>
            </div>
        <?php endif; ?>

        <?php foreach ($sequence_notes as $note): ?>
            <div class="alert alert-info py-2 small"><i class="fa fa-info-circle me-1"></i><?= htmlspecialchars($note) ?></div>
        <?php endforeach; ?>

        <form method="POST" id="downloadForm">
            <input type="hidden" name="organism" value="<?= htmlspecialchars($selected_organism) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($selected_assembly_accession) ?>">
            <input type="hidden" name="gene_set" value="<?= htmlspecialchars($selected_gene_set ?? '') ?>">
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_gene_set" value="<?= htmlspecialchars($context_gene_set ?? '') ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" id="expandedUniqueames" value="<?= htmlspecialchars(json_encode($uniquenames ?? [])) ?>">
            <input type="hidden" id="foundIds" value="<?= htmlspecialchars(json_encode($found_ids ?? [])) ?>">

            <!-- Step 1: Select organism -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">1</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Select organism and assembly') ?></span>
                </div>
                <div class="card-body py-3">
                    <?php
                    $clear_filter_function = 'clearSourceFilter';
                    include __DIR__ . '/../../includes/source-list.php';
                    ?>
                    <div class="mt-3 p-3 bg-light border rounded">
                        <strong><?= t('Currently selected:') ?></strong>
                        <div id="currentSelection" style="margin-top:8px; font-size:14px;">
                            <span style="color:#999;"><?= t('None selected') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Enter IDs -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">2</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Enter feature or gene IDs') ?></span>
                </div>
                <div class="card-body py-3">
                    <textarea
                        class="form-control moop-input textarea-ids"
                        id="featureIds"
                        name="uniquenames"
                        rows="6"
                        placeholder="Enter feature IDs — one per line or comma-separated.&#10;Add a range for part of a sequence: ID:1-60"
                    ><?= htmlspecialchars($uniquenames_string) ?></textarea>

<?php
                    // Shown in the help with the selected gene set's own IDs, so every example in it
                    // works if pasted. sequence-retrieval.js refills .ex-gene / .ex-tx on a new selection.
                    $ex_gene = $examples['gene'] !== '' ? $examples['gene'] : 'GENE_ID';
                    $ex_tx   = $examples['transcript'] !== '' ? $examples['transcript'] : 'TRANSCRIPT_ID';
                    ?>
                    <?php // Always rendered: sequence-retrieval.js refills it when the selected source changes. ?>
                    <div id="sampleIdsRow" class="mt-2 d-flex gap-2 align-items-center flex-wrap"<?= empty($examples['ids']) ? ' style="display:none;"' : '' ?>>
                        <small class="text-muted"><?= t('Examples:') ?> <span id="sampleIdsText"><?= htmlspecialchars(implode(' · ', $examples['ids'])) ?></span></small>
                        <button type="button" class="btn btn-sm fw-semibold text-white ms-auto btn-moop-teal" onclick="loadSampleIds()">
                            <i class="fa fa-bookmark me-1"></i><?= t('Load examples') . "\n" ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFeatureIds()">
                            <i class="fa fa-times me-1"></i><?= t('Clear') . "\n" ?>
                        </button>
                    </div>

                    <?php /* Ranges were documented only in the placeholder (gone once you type) and the
                             (i) modal, so few users found them. This line stays visible. */ ?>
                    <div class="small text-muted mt-2">
                        <i class="fa fa-cut me-1"></i><?= t('Want part of a sequence? Add a range:') . "\n" ?>
                        <code><span class="ex-tx"><?= htmlspecialchars($ex_tx) ?></span>:1-60</code>.
                        <a href="#idInfoCollapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="idInfoCollapse"><?= t('How IDs and ranges work') ?></a>
                    </div>

                    <div id="searchIdsDisplay" class="mt-3 p-3 bg-light border rounded" style="display:none;">
                        <small class="text-muted d-block mb-2"><strong><?= t('IDs to search:') ?></strong></small>
                        <div id="searchIdsContent"></div>
                    </div>

                    <div class="collapse mt-2" id="idInfoCollapse">
                        <div class="p-3 bg-light border rounded small">
                            <p class="fw-semibold mb-1"><?= t('IDs') ?></p>
                            <ul class="mb-2 ps-3">
                                <li><?= t('One per line, or separated by commas.') ?></li>
                                <li><?= t_markup("A gene ID such as %1\$s gives every %2\$s, CDS and protein of that gene, and the gene's genomic region.",
                                             '<code class="ex-gene">' . htmlspecialchars($ex_gene) . '</code>', gloss('transcript')) . "\n" ?>
                                    <?= t_markup("A transcript ID such as %1\$s gives just that %2\$s's, and the transcript's own genomic span (%3\$s included).",
                                             '<code class="ex-tx">' . htmlspecialchars($ex_tx) . '</code>', gloss('isoform'), gloss('intron', 'introns')) . "\n" ?>
                                    <?= t("A protein or CDS ID gives its transcript's genomic span, and the header names the gene and transcript.") ?></li>
                            </ul>
                            <p class="fw-semibold mb-1"><?= t('Ranges — part of a sequence') ?></p>
                            <ul class="mb-2 ps-3">
                                <li><?= t('Add positions after the ID. These are all the same:') . "\n" ?>
                                    <code class="text-nowrap"><span class="ex-tx"><?= htmlspecialchars($ex_tx) ?></span>:1-60</code> ·
                                    <code class="text-nowrap"><span class="ex-tx"><?= htmlspecialchars($ex_tx) ?></span>:1..60</code> ·
                                    <code class="text-nowrap"><span class="ex-tx"><?= htmlspecialchars($ex_tx) ?></span> 1-60</code> ·
                                    <code class="text-nowrap"><span class="ex-tx"><?= htmlspecialchars($ex_tx) ?></span> 1..60</code></li>
                                <li><?= t_markup('The range applies to %s sequence of that ID — protein, mRNA, CDS and genomic.', '<em>' . t('every') . '</em>') ?> <?= t('On a gene ID, to all of its transcripts.') ?></li>
                                <li><strong><?= t("Positions count in each sequence's own units:") ?></strong> <?= t('amino acids in a protein, nucleotides in mRNA and CDS.') . "\n" ?>
                                    <?= t_markup('So %s is amino acids 1–60 of the protein but only the first 60 nucleotides of the CDS, which encode amino acids 1–20.', '<code>1-60</code>') . "\n" ?>
                                    <?= t_markup('For the CDS that matches amino acids 1–60, ask for %s.', '<code>1-180</code>') ?> <?= t_markup('On the genomic region, %s is the first base of the gene or transcript, introns count.', '<code>1</code>') ?></li>
                                <li><?= t('Want several pieces of one sequence? Put each range on its own line.') ?></li>
                                <li><?= t('Positions start at 1, and the start cannot be after the end. A range running past the end of a sequence stops at its end.') ?></li>
                            </ul>
                            <p class="fw-semibold mb-1"><?= t('Genomic region and flanks') ?></p>
                            <ul class="mb-2 ps-3">
                                <li><?= t_markup('Every genomic sequence reads 5′ to 3′ along the gene: a gene on the minus %s is reverse-complemented.', gloss('strand')) ?> <?= t_markup('The header gives the location and strand, e.g. %s.', '<code>chr2:16003283-16019242(-)</code>') ?></li>
                                <li><?= (moop_tool_on('moopmart') ? t('Set an upstream or downstream flank in step 3 to add that many bases before the 5′ end or after the 3′ end, each as its own sequence — the same sequences the gene page and the Data Exporter give.') : t('Set an upstream or downstream flank in step 3 to add that many bases before the 5′ end or after the 3′ end, each as its own sequence — the same sequences the gene page gives.')) . "\n" ?>
                                    <?= t("For a transcript, CDS or protein ID that is the isoform's own start and end; for a gene ID, the gene's.") ?></li>
                                <li><?= t_markup('Genomic sequence needs the gene set to be placed on a genome; %s-only gene sets have none.', gloss('transcriptome')) ?></li>
                            </ul>
                            <p class="fw-semibold mb-1"><?= t('After you retrieve') ?></p>
                            <p class="mb-0"><?= t_markup('The "IDs to search" box shows which IDs were found (%1$s) and which were not (%2$s).',
                                '<span style="background:#d4edda; padding:2px 4px; border-radius:2px;">' . t('green') . '</span>',
                                '<span style="background:#f8d7da; padding:2px 4px; border-radius:2px;">' . t('red') . '</span>') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Retrieve -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center tool-header">
                    <span class="step-badge me-2">3</span>
                    <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Retrieve sequences') ?></span>
                </div>
                <div class="card-body py-3">
                    <?php /* Flanks go with the genomic region only. Same rules (and limit) as the gene
                             page and the Data Exporter: lib/genomic_regions.php. */ ?>
                    <?php /* Shown only for a gene set on a genome (data-has-genome on the selected
                             source); js/sequence-retrieval.js toggles it and disables the inputs. */ ?>
                    <div id="flankControls" class="d-flex flex-wrap align-items-end gap-3 mb-3">
                        <div>
                            <label for="upstreamBp" class="form-label small mb-1"><?= t('Upstream flank (bp)') ?></label>
                            <input type="number" class="form-control form-control-sm moop-input" id="upstreamBp" name="upstream_bp"
                                   min="0" max="<?= MOOP_MAX_FLANK_BP ?>" step="1" value="<?= (int)$upstream_bp ?>" style="width:9rem;">
                        </div>
                        <div>
                            <label for="downstreamBp" class="form-label small mb-1"><?= t('Downstream flank (bp)') ?></label>
                            <input type="number" class="form-control form-control-sm moop-input" id="downstreamBp" name="downstream_bp"
                                   min="0" max="<?= MOOP_MAX_FLANK_BP ?>" step="1" value="<?= (int)$downstream_bp ?>" style="width:9rem;">
                        </div>
                        <small class="text-muted pb-1"><?= t('Optional. Added as separate sequences to the genomic region; 0 = none. A protein, CDS or transcript ID gets its own isoform\'s flanks; a gene ID, the gene\'s.') ?></small>
                    </div>
                    <p id="flankNoGenome" class="small text-muted mb-3" style="display:none;">
                        <i class="fa fa-info-circle me-1"></i><?= t('This gene set is not placed on a genome, so there is no genomic sequence or flanks to retrieve.') . "\n" ?>
                    </p>
                    <button type="submit" class="btn btn-lg fw-semibold text-white w-100 btn-moop-action">
                        <i class="fa fa-eye me-1"></i><?= t('Retrieve Sequences') . "\n" ?>
                    </button>
                    <?php /* "uh-oh, do this first" for a missed assembly. Inline and soft, in place
                             of a jarring browser alert(); js/sequence-retrieval.js toggles it. */ ?>
                    <div id="retrieve-select-hint" class="tools-select-hint small mt-2" style="display:none;">
                        <?php /* Step number, not a direction — see the note on the same hint
                                 in blast.php. */ ?>
                        <i class="fa fa-exclamation-circle me-1"></i> <?= t('Choose an organism and assembly in step 1 before retrieving.') . "\n" ?>
                    </div>
                </div>
            </div>

        </form>

        <?php if (!empty($retrieval_summary)): ?>
            <?php
            // A long list: what was found, and each type as a download -- not drawn on the page
            // (MOOP_RETRIEVAL_SHOW_MAX_IDS / _BYTES in lib/extract_search_helpers.php). The
            // buttons submit the main form, like the ones above the drawn results.
            $rs_missing = $retrieval_summary['missing'];
            $rs_found   = $retrieval_summary['ids'] - count($rs_missing);
            $rs_shown   = 50;
            ?>
            <hr class="my-5" id="sequences-section">
            <div class="card shadow-sm mb-4" id="retrievalSummary">
                <div class="card-body">
                    <p class="mb-2">
                        <strong><?php
                        $__ids = number_format($retrieval_summary['ids']);
                        if ($rs_missing) echo $retrieval_summary['ids'] === 1
                            ? t('%1$s ID: %2$s found, %3$s not found.', $__ids, number_format($rs_found), number_format(count($rs_missing)))
                            : t('%1$s IDs: %2$s found, %3$s not found.', $__ids, number_format($rs_found), number_format(count($rs_missing)));
                        else echo $retrieval_summary['ids'] === 1
                            ? t('%1$s ID: %2$s found.', $__ids, number_format($rs_found))
                            : t('%1$s IDs: %2$s found.', $__ids, number_format($rs_found)); ?></strong>
                    </p>
                    <p class="small text-muted mb-3">
                        <?php if ($retrieval_summary['reason'] === 'count'): ?>
                            <?= t('Lists of more than %d IDs are available as a download only, so the page stays quick.', (int)$show_max_ids) ?> <?= t('Every ID is included.') . "\n" ?>
                        <?php else: ?>
                            <?= t('These sequences are too long to show on the page, so they are available as a download only.') ?> <?= t('Every ID is included.') . "\n" ?>
                        <?php endif; ?>
                        <?= t('Ranges and flanks apply to the download as they would on the page.') . "\n" ?>
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-2" id="retrieveDownloads">
                        <span class="small text-muted me-1"><i class="fa fa-download me-1"></i><?= t('Download as FASTA:') ?></span>
                        <?php foreach ($retrieval_summary['types'] as $dl_type): ?>
                            <button type="submit" form="downloadForm" name="download_type" value="<?= htmlspecialchars($dl_type) ?>"
                                    class="btn btn-sm fw-semibold text-white btn-moop-teal">
                                <?= htmlspecialchars($sequence_types[$dl_type]['label'] ?? $dl_type) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($rs_missing): ?>
                        <div class="mt-3 small">
                            <span class="fw-semibold"><?= t('Not found in this gene set:') ?></span>
                            <?= htmlspecialchars(implode(', ', array_slice($rs_missing, 0, $rs_shown))) ?><?= count($rs_missing) > $rs_shown ? ', …' : '' ?>
                            <?php if (count($rs_missing) > $rs_shown): ?>
                                <details class="mt-2">
                                    <summary><?= t('All %s not found, to copy', number_format(count($rs_missing))) ?></summary>
                                    <textarea class="form-control form-control-sm font-monospace mt-2" rows="6" readonly><?= htmlspecialchars(implode("\n", $rs_missing)) ?></textarea>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sequences Display Section -->
        <?php if (!empty($displayed_content)): ?>
            <hr class="my-5" id="sequences-section">
            <?php /* One FASTA file per type, for the IDs in the box (ranges and flanks included).
                     The buttons submit the main form (form="downloadForm"), so the ID list is
                     not copied into the page once per button; the server streams the one type
                     asked for. This page had no download at all before: the shared sequence
                     card only draws its buttons when given a gene name, which it is not here. */ ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="retrieveDownloads">
                <span class="small text-muted me-1"><i class="fa fa-download me-1"></i><?= t('Download as FASTA:') ?></span>
                <?php foreach ($sequence_types as $dl_type => $dl_config): ?>
                    <?php if (empty($displayed_content[$dl_type])) continue; ?>
                    <button type="submit" form="downloadForm" name="download_type" value="<?= htmlspecialchars($dl_type) ?>"
                            class="btn btn-sm fw-semibold text-white btn-moop-teal">
                        <?= htmlspecialchars($dl_config['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php
            // For retrieve_sequences.php, we have pre-extracted sequences with ranges
            // Don't set $gene_name to prevent sequences_display.php from re-extracting
            $gene_name = '';
            $organism_name = $selected_organism;
            $assembly_name = $selected_assembly_accession;
            $enable_downloads = true;
            
            include_once __DIR__ . '/../sequences_display.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// How-to help, opened by the (i) on the page header: the four steps. The ID and range
// rules used to be a second section here; they now live once, under the ID box ("How IDs
// and ranges work"), next to where they are used and with the selected gene set's own IDs.
echo help_modal(
    'retrieve-help',
    'How to use Sequence Retrieval',
    [
        [
            'heading' => 'Steps',
            'cards'   => [
                // 'num' and labels match the page's numbered step headers; the last card is
                // about the results, which have no step number on the page, so none here.
                ['num' => '1', 'label' => 'Select organism and assembly', 'text' => 'Select an organism and one of its assemblies.'],
                ['num' => '2', 'label' => 'Enter feature or gene IDs',    'text' => 'Gene or feature IDs, one per line or comma-separated, optionally with a range for part of a sequence. "How IDs and ranges work", under the box, has the details.'],
                ['num' => '3', 'label' => 'Retrieve sequences',           'text' => 'Results show the protein, mRNA, CDS and genomic sequence of each ID, plus upstream and downstream sequence if you set a flank.'],
                [              'label' => 'Take what you need',           'text' => 'Copy or download individual sequences from the results.'],
            ],
        ],
    ],
    ['intro' => 'Look up sequences by feature ID across any accessible assembly.']
);
?>

<script>
// Make sample feature IDs available to JavaScript
window.sampleFeatureIds = <?= json_encode(array_values($examples['ids'])) ?>;

// Pass found IDs to JavaScript for coloring
window.foundIds = <?= json_encode($found_ids ?? []) ?>;

// Pass parent-to-children mapping for display
window.parentToChildren = <?= json_encode($parent_to_children ?? []) ?>;

// Longer lists are a download only; the ID box shows a count instead of every ID (same limit)
window.retrievalShowMax = <?= (int)$show_max_ids ?>;
// Pass auto-select flag to JavaScript
window.shouldAutoSelect = <?= json_encode($should_auto_select ?? true) ?>;
</script>
