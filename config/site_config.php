<?php
/**
 * APPLICATION CONFIGURATION
 * 
 * This file defines all site-wide settings and paths.
 * Configuration is loaded by ConfigManager and accessed via ConfigManager::getInstance()
 * 
 * ⚠️  IMPORTANT FOR NEW ADMINS:
 * - Update values here, ConfigManager handles the rest
 * - 'site' is the directory name (e.g., 'moop', 'easy_gdb') - CHANGE THIS for different sites
 * - Derived paths are calculated automatically from root_path + site
 * - All absolute paths use root_path as base
 * - All URLs use /site as the web root (e.g., /moop/images, /easy_gdb/images)
 */

// Calculate derived paths from root_path and site (no need to edit these)
$root_path = '/var/www/html';
$site = 'moop';  // CHANGE THIS to deploy for a different site directory
$site_path = "$root_path/$site";
$images_dir = 'images';

return [
    // ======== REQUIRED: Server Paths ========
    // These must exist on your server, or the app won't function
    'root_path' => $root_path,
    'site' => $site,
    'site_path' => $site_path,
    'organism_data' => "$site_path/organisms",
    
    // ======== REQUIRED: Directory Names ========
    'images_dir' => $images_dir,
    'images_path' => "$site/$images_dir",  // Web path for <img src>
    'absolute_images_path' => "$site_path/$images_dir",  // Filesystem path
    
    // ======== REQUIRED: Metadata ========
    'metadata_path' => "$site_path/metadata",
    
    // ======== OPTIONAL: Appearance ========
    'siteTitle' => 'SIMRbase',
    'header_img' => 'header_img.png',
    'favicon_path' => "/$site/$images_dir/favicon.ico",
    'custom_css_path' => "$site_path/css/custom.css",
    
    // ======== OPTIONAL: Contact ========
    'admin_email' => 'admin@example.com',
    
    // ======== OPTIONAL: Files ========
    'users_file' => "$root_path/users.json",
    
    // ======== DATA CONFIGURATION ========
    // Maps sequence file patterns to display names
    // Admin: Add new sequence types here and they're automatically available
    'sequence_types' => [
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
        ],
        'genome' => [
            'pattern' => 'genome.fa',
            'label' => 'GENOME',
        ]
    ],
];

