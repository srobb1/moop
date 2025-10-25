<?php
$groups_file = '/var/www/html/moop/organisms/groups.txt';
$json_file = '/var/www/html/moop/organisms/groups.json';
$backup_file = '/var/www/html/moop/organisms/groups.txt.bak';

$groups_data = [];
if (file_exists($groups_file)) {
    $lines = file($groups_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', $line, 3);
        $groups = explode(',', $parts[0]);
        $organism = $parts[1];
        $assembly = $parts[2];
        $groups_data[] = [
            'organism' => $organism,
            'assembly' => $assembly,
            'groups' => $groups
        ];
    }
}

if (copy($groups_file, $backup_file)) {
    if (file_put_contents($json_file, json_encode($groups_data, JSON_PRETTY_PRINT))) {
        echo "Successfully converted groups.txt to groups.json.";
    } else {
        echo "Error writing to groups.json.";
    }
} else {
    echo "Error creating backup file.";
}
