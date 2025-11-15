<?php

function getFastaFile($gids,$dbPath) {
    $command = "blastdbcmd -db " . escapeshellarg($dbPath) . " -entry " . escapeshellarg(implode(",", $gids)) . " | sed 's/lcl|//'";
    $descriptors = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"],  // stderr
    ];

    $process = proc_open($command, $descriptors, $pipes);

    $ret = '';
    $stderr = '';

    if (is_resource($process)) {
        fclose($pipes[0]); // stdin
        $ret = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);
    }

    return ['stdout' => $ret, 'stderr' => trim($stderr)];
}

if(isset($_POST["gids"]) && isset($_POST["blast_db"])) {
    $gids = array_map('trim', explode("\n", $_POST["gids"]));
    $dbPath = $_POST["blast_db"];

    $result = getFastaFile($gids, $dbPath);

    // Detect AJAX request
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    } else {
        // Regular form submit -> force download
        header('Content-Type: application/octet-stream');
        $filename = "downloaded_sequences_" . date("Y-m-d.His") . ".fasta";
        header("Content-Disposition: attachment;filename={$filename}");
        echo $result['stdout'];
        exit;
    }
}
?>
