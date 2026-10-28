<?php
/**
 * Sequences Display Component for MOOP
 * Displays sequences for parent feature and all child features grouped by sequence type
 * 
 * Expected variables from parent_display.php:
 * - $organism_name: Name of the organism
 * - $organism_data: Path to organism data directory
 * - $gene_name: Comma-separated list of feature uniquenames (parent and children)
 * - $sequence_types: Array of sequence type configurations (from site_config.php)
 * - $admin_email: Administrator email (from site_config.php)
 * 
 * Optional variables for download functionality:
 * - $enable_downloads: (bool) Set to true to show download buttons
 * - $assembly_name: Name of assembly (required if enable_downloads is true)
 * - $download_script_url: URL to download script (default: for parent_display context)
 */

// Ensure config is initialized (should be from parent_display.php, but double-check)
if (!class_exists('ConfigManager')) {
    include_once __DIR__ . '/../includes/config_init.php';
}

// Get config if not already set by parent
if (!isset($sequence_types)) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    $sequence_types = $config->getSequenceTypes();
    $admin_email = $config->getString('admin_email');
}

// Include error logging (logError function)
include_once __DIR__ . '/../lib/moop_functions.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/fasta_index.php';
require_once __DIR__ . '/../lib/request_metrics.php';   // System Monitor; this endpoint skips access_control.php
moop_metrics_register();

// Initialize download settings
$enable_downloads = $enable_downloads ?? false;
$assembly_name = $assembly_name ?? '';
$download_script_url = $download_script_url ?? '';

// Check for download request (may come from POST)
$download_file_flag = $download_file_flag ?? (isset($_POST['download_file']) && $_POST['download_file'] == '1');
$sequence_type = $sequence_type ?? trim($_POST['sequence_type'] ?? '');

// The sequences arrive READY: every page that includes this card (gene page, Sequence
// Retrieval, Retrieve Selected Sequences) extracts them through extractSequencesForAllTypes()
// and passes $available_sequences, and handles its own Download buttons -- so what the card
// shows is what the download serves. This card used to run a second FASTA reader of its own,
// and its own download handler; that is how the gene page once showed soft-masked lowercase
// that its download did not (2026-10-13).
if (!isset($available_sequences)) {
    $available_sequences = [];
}

if (!empty($available_sequences) && array_reduce($available_sequences, function($carry, $item) {
    return $carry || !empty($item['sequences']);
}, false)) {
    // Display Sequences Section - only if no errors and sequences exist
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="collapse-section d-flex align-items-center" data-bs-toggle="collapse" data-bs-target="#sequencesSection" aria-expanded="true">
                <i class="fas fa-minus toggle-icon text-primary"></i>
                <?php // The same eyebrow as every other section header on the gene page. ?>
                <span class="ms-2 text-uppercase fw-semibold section-eyebrow">Sequences</span>
            </div>
            <?php /* "Back to Top" removed: the gene page's section nav returns you there from
                     anywhere, so this duplicated it in one spot. It was also an href="#",
                     which on a page with smooth scrolling crawls the full height rather
                     than jumping. */ ?>
        </div>
        <div id="sequencesSection" class="collapse show">
            <div class="card-body">
                <?php
                $seq_count = 0;
                foreach ($sequence_types as $seq_type => $seq_config) {
                    $seq_count++;
                    $seq_data = $available_sequences[$seq_type] ?? [];
                    $sequences = $seq_data['sequences'] ?? [];
                    
                    if (!empty($sequences)) {
                        echo '<div class="card annotation-card border-info mb-3">';
                        echo '  <div class="card-header card-header-light-info">';
                        echo '    <div class="collapse-section" data-bs-toggle="collapse" data-bs-target="#seq_' . $seq_type . '" aria-expanded="true">';
                        echo '      <i class="fas fa-minus toggle-icon text-info"></i>';
                        echo '      <strong class="ms-2 text-dark">';
                        // Badge colour from config: a class (bg-info) OR a hex value (#26d984) -- the
                        // admin page accepts both and previews both through getColorClassOrStyle().
                        // This used the value as a class only, so a hex colour gave NO background:
                        // white text on the pale header ("Genomic region" was unreadable).
                        $badge = getColorClassOrStyle($seq_config['color'] ?? 'bg-secondary');
                        // gloss() the LABEL, not the config key: the key is 'transcript'/'genome' while the
                        // glossary defines 'mRNA'/'CDS'/'protein' — the words actually shown. These headers
                        // are mode selectors the reader chooses between to get a sequence, which is the
                        // "gloss labels and controls, not prose" case in notes/GLOSSARY_TERM_CANDIDATES.md.
                        // One wrap covers every sequence type, including any added to config later, and
                        // gloss() returns plain text for an undefined term so 'Genome' degrades safely.
                        echo '        <span class="text-white px-2 py-1 rounded ' . $badge['class'] . '"'
                           . ($badge['style'] !== '' ? ' style="' . $badge['style'] . '"' : '') . '>' . gloss($seq_config['label']) . '</span>';
                        echo '        (' . count($sequences) . ' sequence' . (count($sequences) > 1 ? 's' : '') . ')';
                        echo '      </strong>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '  <div id="seq_' . $seq_type . '" class="collapse show">';
                        echo '    <div class="card-body">';
                        
                        // Concatenate all sequences for this type
                        $concatenated_sequences = implode("\n\n", $sequences);
                        
                        // Hand off to Primer Maker by ID, not by shipping the sequence.
                        //
                        // An earlier version POSTed the whole sequence in a hidden field. That
                        // put a copy of every sequence type into the page a second time — 12 kB
                        // per transcript here — for data the maker can fetch itself from the
                        // same FASTA. A link also survives being bookmarked or shared, which a
                        // hidden POST field does not.
                        //
                        // Only for types fetchable BY ID: genome.fa holds chromosomes, so a
                        // gene id is not a record in it, and protein is not what primers are
                        // made of. Offering a button that leads nowhere is worse than no button.
                        //
                        // Opt-in via $enable_primer_design, because this component is shared
                        // with Retrieve Sequences and Primer Maker is a gene-page tool.
                        // require_once, not function_exists: guarding on the latter meant
                        // the button silently did not render on the one page that enables
                        // it, because nothing there had included the file. A missing
                        // dependency should be loaded, or fail loudly — not quietly turn a
                        // feature off.
                        require_once __DIR__ . '/../lib/gene_isoforms.php';
                        if (!empty($enable_primer_design)
                            && in_array($seq_type, moop_primer_sequence_types(), true)) {
                            $pd_ctx = $primer_design_context ?? [];
                            // One link per sequence in this box. With several (a gene with several
                            // isoforms), the button is a picker, like Format sequence's: the link
                            // used to name only the gene, so Primer Maker opened empty and asked
                            // again -- and from a CDS box it could never load anything, because a
                            // CDS id (X-RA:cds, cds-XP_..., ...) cannot be derived from the
                            // transcript id it was given. seq_id is the exact id in this box.
                            $pd_links = [];
                            foreach ($sequences as $pd_header => $pd_fasta) {
                                $pd_id  = strtok((string)$pd_header, " \t");
                                $pd_len = strlen(preg_replace('/\s+/', '', implode('', array_slice(explode("\n", $pd_fasta), 1))));
                                $pd_links[] = [
                                    'id'  => $pd_id,
                                    'len' => $pd_len,
                                    'url' => '/' . $config->getString('site') . '/tools/primer_maker.php?' . http_build_query([
                                        'organism' => $pd_ctx['organism'] ?? '',
                                        'assembly' => $pd_ctx['assembly'] ?? '',
                                        'gene_set' => $pd_ctx['gene_set'] ?? '',
                                        'feature'  => $pd_ctx['feature'] ?? '',
                                        'isoform'  => $pd_ctx['transcript_of'][$pd_id] ?? $pd_id,
                                        'seq_id'   => $pd_id,
                                        'seq_type' => $seq_type,
                                        // A transcript is what RT-PCR needs; anything else is ordinary PCR.
                                        'primer_type' => $seq_type === 'transcript' ? 'rtpcr' : 'standard',
                                    ]),
                                ];
                            }
                            if (count($pd_links) === 1) {
                                echo '      <a href="' . htmlspecialchars($pd_links[0]['url']) . '" class="btn btn-sm btn-tool-indigo mb-2">'
                                   . '<i class="fa fa-magic me-1"></i>Design primers from this sequence</a>';
                            } elseif ($pd_links) {
                                echo '      <div class="dropdown d-inline-block mb-2">';
                                echo '        <button class="btn btn-sm btn-tool-indigo dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">'
                                   . '<i class="fa fa-magic me-1"></i>Design primers from…</button>';
                                echo '        <ul class="dropdown-menu">';
                                foreach ($pd_links as $pd) {
                                    echo '          <li><a class="dropdown-item small" href="' . htmlspecialchars($pd['url']) . '">'
                                       . htmlspecialchars($pd['id']) . ' <span class="text-muted">· ' . number_format($pd['len']) . ' bp</span></a></li>';
                                }
                                echo '        </ul>';
                                echo '      </div>';
                            }
                        }

                        echo '      <div class="card bg-light">';
                        echo '        <div class="card-body copyable font-monospace-small cursor-pointer preserve-whitespace">';
                        echo htmlspecialchars($concatenated_sequences);
                        echo '        </div>';
                        echo '      </div>';
                        
                        // Add download button if enabled
                        if ($enable_downloads && !empty($assembly_name) && !empty($organism_name) && !empty($gene_name)) {
                            echo '      <div class="margin-top">';
                            echo '        <form method="POST" action="" class="display-inline">';
                            echo '          <input type="hidden" name="organism" value="' . htmlspecialchars($organism_name) . '">';
                            echo '          <input type="hidden" name="assembly" value="' . htmlspecialchars($assembly_name) . '">';
                            echo '          <input type="hidden" name="sequence_type" value="' . htmlspecialchars($seq_type) . '">';
                            echo '          <input type="hidden" name="uniquenames" value="' . htmlspecialchars($gene_name) . '">';
                            echo '          <input type="hidden" name="download_file" value="1">';
                            echo '          <button type="submit" class="btn btn-sm btn-success">';
                            echo '            <i class="fa fa-download"></i> Download ' . htmlspecialchars($seq_config['label']);
                            echo '          </button>';
                            echo '        </form>';
                            echo '      </div>';
                        }
                        
                        echo '    </div>';
                        echo '  </div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php
?>


<?php /* Versioned like every other script (?v=mtime), so browsers may cache them long-term
         and still pick up an edit. These two were the only unversioned scripts on a gene page. */
require_once __DIR__ . '/../includes/asset_url.php'; ?>
<script src="<?= moop_asset_url('js/modules/copy-to-clipboard.js') ?>"></script>
<script src="<?= moop_asset_url('js/modules/collapse-handler.js') ?>"></script>
