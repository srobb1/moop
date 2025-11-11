<?php
/**
 * FASTA Download Tool
 * Allows users to download sequences (protein, CDS, mRNA) for selected features
 * Uses blastdbcmd to extract from FASTA BLAST databases
 */

ob_end_clean();
ob_implicit_flush(true);
session_start();

// Get parameters before including site_config to avoid output before headers
$sequence_type = trim($_POST['sequence_type'] ?? '');

include_once __DIR__ . '/../../site_config.php';
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/head.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: /$site/login.php");
    exit;
}

// Get all parameters
$organism_name = trim($_POST['organism'] ?? $_GET['organism'] ?? '');
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// If sequence_type is set, this is the download request - process it
if (!empty($sequence_type)) {
    // Validate inputs
    if (empty($organism_name) || empty($uniquenames_string)) {
        die('Error: Missing required parameters.');
    }

    // Check access
    if (!is_public_organism($organism_name) && !has_access('Collaborator', $organism_name)) {
        header("Location: /$site/access_denied.php");
        exit;
    }

    // Parse feature IDs
    $uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));
    if (empty($uniquenames)) {
        die('Error: No feature IDs provided.');
    }

    // Find FASTA file for selected sequence type
    $organism_dir = "$organism_data/$organism_name";
    $fasta_file = null;

    if (is_dir($organism_dir)) {
        $dirs = array_diff(scandir($organism_dir), ['.', '..']);
        foreach ($dirs as $item) {
            $full_path = "$organism_dir/$item";
            if (is_dir($full_path) && !in_array(basename($full_path), ['fasta_files'])) {
                $assembly_dir = $full_path;
                break;
            }
        }

        if (isset($assembly_dir) && isset($sequence_types[$sequence_type])) {
            $files = glob("$assembly_dir/*{$sequence_types[$sequence_type]['pattern']}");
            if (!empty($files)) {
                $fasta_file = $files[0];
            }
        }
    }

    if (!$fasta_file || !file_exists($fasta_file)) {
        die("Error: FASTA file not found for $sequence_type sequences.");
    }

    // Extract sequences using blastdbcmd
    $cmd = "blastdbcmd -db " . escapeshellarg($fasta_file) . " -entry " . escapeshellarg(implode(',', $uniquenames));
    
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        die("Error: Failed to execute blastdbcmd");
    }

    fclose($pipes[0]);
    $content = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $return_var = proc_close($process);

    // Check for errors
    if ($return_var > 1 || empty(trim($content))) {
        $error = "No sequences found for the requested feature IDs.\n";
        $error .= "Requested IDs: " . implode(", ", array_slice($uniquenames, 0, 5));
        if (count($uniquenames) > 5) {
            $error .= " ... and " . (count($uniquenames) - 5) . " more";
        }
        die($error);
    }

    // Send download
    $file_format = $_POST['file_format'] ?? 'fasta';
    $ext = ($file_format === 'txt') ? 'txt' : 'fasta';
    $filename = "sequences_{$sequence_type}_" . date("Y-m-d_His") . ".{$ext}";
    $content = trim($content);

    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

// Display form
$uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));

if (empty($organism_name)) {
    die('Error: Organism not specified.');
}

if (!is_public_organism($organism_name) && !has_access('Collaborator', $organism_name)) {
    header("Location: /$site/access_denied.php");
    exit;
}

if (empty($uniquenames)) {
    die('Error: No feature IDs provided.');
}

// Find available FASTA files
$available_types = [];
$organism_dir = "$organism_data/$organism_name";

if (is_dir($organism_dir)) {
    $dirs = array_diff(scandir($organism_dir), ['.', '..']);
    foreach ($dirs as $item) {
        $full_path = "$organism_dir/$item";
        if (is_dir($full_path) && !in_array(basename($full_path), ['fasta_files'])) {
            $assembly_dir = $full_path;
            break;
        }
    }

    if (isset($assembly_dir)) {
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob("$assembly_dir/*{$config['pattern']}");
            if (!empty($files)) {
                $available_types[$seq_type] = $config['label'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>FASTA Download - <?= htmlspecialchars($siteTitle) ?></title>
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 700px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .sequence-option { padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 10px; cursor: pointer; }
        .sequence-option:hover { background-color: #f8f9fa; border-color: #0d6efd; }
        .sequence-option input[type="radio"] { margin-right: 10px; }
        .selected-ids { background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 150px; overflow-y: auto; }
        .badge-custom { display: inline-block; background-color: #0d6efd; color: white; padding: 5px 10px; margin: 3px; border-radius: 3px; font-size: 0.85em; }
    </style>
</head>
<body>
<div class="container">
    <div class="mb-4">
        <a href="javascript:history.back();" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    </div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> Download FASTA Sequences</h2>

    <div class="alert alert-info">
        <strong>Organism:</strong> <em><?= htmlspecialchars($organism_name) ?></em><br>
        <strong>Selected Features:</strong> <span class="badge bg-secondary"><?= count($uniquenames) ?></span>
    </div>

    <div class="mb-4">
        <h5>Selected Feature IDs</h5>
        <div class="selected-ids">
            <?php foreach (array_slice($uniquenames, 0, 10) as $id): ?>
                <span class="badge-custom"><?= htmlspecialchars($id) ?></span>
            <?php endforeach; ?>
            <?php if (count($uniquenames) > 10): ?>
                <span class="badge-custom">+<?= count($uniquenames) - 10 ?> more</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($available_types)): ?>
        <div class="alert alert-danger">
            <strong>No FASTA files available</strong>
            <p class="mb-0">No sequence files were found for this organism.</p>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="organism" value="<?= htmlspecialchars($organism_name) ?>">
            <input type="hidden" name="uniquenames" value="<?= htmlspecialchars($uniquenames_string) ?>">

            <div class="mb-4">
                <h5>Select Sequence Type</h5>
                <?php foreach ($available_types as $seq_type => $label): ?>
                    <div class="sequence-option">
                        <label>
                            <input type="radio" name="sequence_type" value="<?= htmlspecialchars($seq_type) ?>" required>
                            <strong><?= htmlspecialchars($label) ?></strong>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-download"></i> Download Sequences
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
$(document).ready(function() {
    $('input[name="sequence_type"]:first').prop('checked', true);
});
</script>
</body>
</html>
