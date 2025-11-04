<?php
// File paths

$siteTitle = "SIMRbase";
$root_path = "/var/www/html"; //use absolute path
$site = "moop";
$site_path = "$root_path/$site";
$images_dir = "images";
$images_path = "$site/$images_dir";
$absolute_images_path = "$root_path/$site/$images_dir";
$favicon_path = "/$site/$images_dir/favicon.ico";
$header_img = "header_img.png";
$users_file = "/var/www/html/users.json";
$organism_data = "$root_path/$site/organisms";
$admin_email = "admin@example.com"; // Contact email for access issues

//$json_files_path = "$root_path/$egdb_files_folder/json_files";

// Custom css file
$custom_css_path = "$site_path/css/custom.css";

// Sequence type configuration for FASTA file discovery
// Maps sequence types to their filename patterns and display labels
$sequence_types = [
    'protein' => [
        'pattern' => 'protein.aa.fa',
        'label' => 'Protein',
    ],
    'transcript' => [
        'pattern' => 'transcript.nt.fa',
        'label' => 'mRNA',
    ],
    'cds' => [
        'pattern' => 'cds.nt.fa',
        'label' => 'CDS',
    ]
];

?>

