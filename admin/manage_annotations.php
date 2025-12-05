<?php
include_once __DIR__ . '/admin_init.php';

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$config_file = "$metadata_path/annotation_config.json";

// Load config using helper - use different variable name to avoid overwriting $config
$annotation_config = loadJsonFile($config_file, []);

// Check if file is writable
$file_write_error = getFileWriteError($config_file);

// Transform annotation_types to analysis_order and descriptions if needed
if (!empty($annotation_config) && isset($annotation_config['annotation_types']) && !isset($annotation_config['analysis_order'])) {
    $analysis_order = [];
    $analysis_descriptions = [];
    
    foreach ($annotation_config['annotation_types'] as $key => $annotation) {
        $analysis_order[] = $key;
        $analysis_descriptions[$key] = [
            'display_name' => $annotation['display_name'] ?? $key,
            'description' => $annotation['description'] ?? '',
            'color' => $annotation['color'] ?? 'secondary',
            'enabled' => $annotation['enabled'] ?? true
        ];
    }
    
    // Sort by order field
    usort($analysis_order, function($a, $b) use ($annotation_config) {
        $orderA = $annotation_config['annotation_types'][$a]['order'] ?? 999;
        $orderB = $annotation_config['annotation_types'][$b]['order'] ?? 999;
        return $orderA - $orderB;
    });
    
    $annotation_config['analysis_order'] = $analysis_order;
    $annotation_config['analysis_descriptions'] = $analysis_descriptions;
}

// Initialize if empty
if (empty($annotation_config) || !isset($annotation_config['analysis_order'])) {
    $annotation_config = [
        'analysis_order' => [],
        'analysis_descriptions' => [],
        'annotation_type_order' => [],
        'organisms' => []
    ];
}

// Create annotation_type_order if it doesn't exist, sorted by order field
if (empty($annotation_config['annotation_type_order']) && !empty($annotation_config['annotation_types'])) {
    $type_order = [];
    foreach ($annotation_config['annotation_types'] as $type_name => $type_config) {
        $type_order[] = [
            'name' => $type_name,
            'order' => $type_config['order'] ?? 999
        ];
    }
    usort($type_order, function($a, $b) {
        return $a['order'] - $b['order'];
    });
    $annotation_config['annotation_type_order'] = array_map(function($item) { return $item['name']; }, $type_order);
}

$message = "";
$messageType = "";

// PHASE 2: Query databases and sync annotation types
// Only query databases if counts are empty or SQLite files have been modified
$all_db_annotation_types = [];
$sync_status = [];

// Check for update needed on EVERY request (GET, POST, etc.)
try {
    $organisms_path = $config->getPath('organism_data');
    $newest_mod_info = getNewestSqliteModTime($organisms_path);
    
    // Check if we need to update counts
    $need_update = shouldUpdateAnnotationCounts($annotation_config, $newest_mod_info);
    
    if ($need_update) {
        // Query all organism databases
        $organisms = getOrganismsWithAssemblies($organisms_path);
        
        foreach ($organisms as $organism => $assemblies) {
            $organism_path = "$organisms_path/$organism";
            $db_file = "$organism_path/organism.sqlite";
            
            if (file_exists($db_file)) {
                $db_types = getAnnotationTypesFromDB($db_file);
                foreach ($db_types as $type => $counts) {
                    if (!isset($all_db_annotation_types[$type])) {
                        $all_db_annotation_types[$type] = $counts;
                    } else {
                        $all_db_annotation_types[$type]['annotation_count'] += $counts['annotation_count'];
                        $all_db_annotation_types[$type]['feature_count'] += $counts['feature_count'];
                    }
                }
            }
        }
        
        // Sync annotation types if we're using the new schema
        if (!empty($annotation_config) && isset($annotation_config['annotation_types'])) {
            $annotation_config = syncAnnotationTypes($annotation_config, $all_db_annotation_types);
            
            // Update the SQLite modification timestamp
            if ($newest_mod_info !== null) {
                $annotation_config['sqlite_mod_time'] = $newest_mod_info['unix_time'];
            }
            
            // Save the updated config
            saveJsonFile($config_file, $annotation_config);
            
            $sync_status = [
                'total_db_types' => count($all_db_annotation_types),
                'total_config_entries' => count($annotation_config['annotation_types'] ?? []),
                'in_database' => array_filter($annotation_config['annotation_types'] ?? [], function($c) { return $c['in_database'] ?? false; }),
                'not_in_database' => array_filter($annotation_config['annotation_types'] ?? [], function($c) { return !($c['in_database'] ?? false); }),
                'new_types' => array_filter($annotation_config['annotation_types'] ?? [], function($c) { return $c['new'] ?? false; })
            ];
            
            // Check if any synonyms have become DB annotation types
            $deactivated_synonyms = [];
            foreach ($annotation_config['annotation_types'] as $type_name => $type_config) {
                if (!empty($type_config['synonyms'])) {
                    foreach ($type_config['synonyms'] as $key => $synonym) {
                        if (isset($all_db_annotation_types[$synonym])) {
                            // This synonym is now a DB type - remove it and track it
                            unset($annotation_config['annotation_types'][$type_name]['synonyms'][$key]);
                            $deactivated_synonyms[] = [
                                'synonym' => $synonym,
                                'type' => $type_name
                            ];
                        }
                    }
                    // Re-index array
                    if (!empty($annotation_config['annotation_types'][$type_name]['synonyms'])) {
                        $annotation_config['annotation_types'][$type_name]['synonyms'] = array_values($annotation_config['annotation_types'][$type_name]['synonyms']);
                    }
                }
            }
            
            // If synonyms were deactivated, save config and show warning
            if (!empty($deactivated_synonyms)) {
                saveJsonFile($config_file, $annotation_config);
                $deactivated_list = implode(', ', array_map(function($d) { return "'{$d['synonym']}'"; }, $deactivated_synonyms));
                $message = "⚠️ Warning: The following synonyms were deactivated because they are now annotation types in the database: $deactivated_list";
                $messageType = "warning";
            }
        }
    }
} catch (Exception $e) {
    error_log("Error querying annotation types from databases: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$file_write_error) {
    
    if (isset($_POST['add_section'])) {
        $section_name = trim($_POST['section_name'] ?? '');
        $section_desc = trim($_POST['section_description'] ?? '');
        
        if (!empty($section_name)) {
            if (!in_array($section_name, $annotation_config['analysis_order'])) {
                $annotation_config['analysis_order'][] = $section_name;
                if (!empty($section_desc)) {
                    $annotation_config['analysis_descriptions'][$section_name] = $section_desc;
                }
                
                saveJsonFile($config_file, $annotation_config);
                $message = "Section added successfully!";
                $messageType = "success";
            } else {
                $message = "Section already exists.";
                $messageType = "warning";
            }
        }
    }
    
    if (isset($_POST['update_order'])) {
        $new_order = json_decode($_POST['order_data'], true);
        if ($new_order) {
            $annotation_config['analysis_order'] = $new_order;
            saveJsonFile($config_file, $annotation_config);
            $message = "Order updated successfully!";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['update_description'])) {
        $section = $_POST['section'] ?? '';
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($section)) {
            $annotation_config['analysis_descriptions'][$section] = $description;
            saveJsonFile($config_file, $annotation_config);
            $message = "Description updated successfully!";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['delete_section'])) {
        $section = $_POST['section'] ?? '';
        
        if (!empty($section)) {
            $annotation_config['analysis_order'] = array_values(array_diff($annotation_config['analysis_order'], [$section]));
            unset($annotation_config['analysis_descriptions'][$section]);
            
            // Remove from all organisms
            foreach ($annotation_config['organisms'] as $org => &$org_config) {
                if (isset($org_config['enabled_sections'])) {
                    $org_config['enabled_sections'] = array_values(array_diff($org_config['enabled_sections'], [$section]));
                }
            }
            
            saveJsonFile($config_file, $annotation_config);
            $message = "Section deleted successfully!";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['update_type_order'])) {
        $new_type_order = json_decode($_POST['type_order_data'], true);
        
        if ($new_type_order && is_array($new_type_order)) {
            // Update the 'order' field for each annotation type based on new order
            foreach ($new_type_order as $index => $type_name) {
                if (isset($annotation_config['annotation_types'][$type_name])) {
                    $annotation_config['annotation_types'][$type_name]['order'] = $index + 1;
                }
            }
            
            // Also update annotation_type_order for display purposes
            $annotation_config['annotation_type_order'] = $new_type_order;
            
            saveJsonFile($config_file, $annotation_config);
            $message = "Annotation type order updated successfully!";
            $messageType = "success";
        }
    }
}

// PHASE 3: Handle synonym and display label management
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$file_write_error) {
    if (isset($_POST['_form_action']) && $_POST['_form_action'] === 'add_synonym') {
        $type_name = trim($_POST['type_name'] ?? '');
        $new_synonym = trim($_POST['new_synonym'] ?? '');
        
        if (!empty($type_name) && !empty($new_synonym) && isset($annotation_config['annotation_types'][$type_name])) {
            // Check if synonym is already a DB annotation type
            if (isset($all_db_annotation_types[$new_synonym])) {
                $message = "Cannot add '$new_synonym' as synonym - it's already an annotation type in the database";
                $messageType = "danger";
            } else if (!isset($annotation_config['annotation_types'][$type_name]['synonyms'])) {
                $annotation_config['annotation_types'][$type_name]['synonyms'] = [];
                
                if (!in_array($new_synonym, $annotation_config['annotation_types'][$type_name]['synonyms'])) {
                    $annotation_config['annotation_types'][$type_name]['synonyms'][] = $new_synonym;
                    saveJsonFile($config_file, $annotation_config);
                    $message = "Added '$new_synonym' as synonym for '$type_name'";
                    $messageType = "success";
                } else {
                    $message = "This synonym already exists for '$type_name'";
                    $messageType = "warning";
                }
            } else if (!in_array($new_synonym, $annotation_config['annotation_types'][$type_name]['synonyms'])) {
                $annotation_config['annotation_types'][$type_name]['synonyms'][] = $new_synonym;
                saveJsonFile($config_file, $annotation_config);
                $message = "Added '$new_synonym' as synonym for '$type_name'";
                $messageType = "success";
            } else {
                $message = "This synonym already exists for '$type_name'";
                $messageType = "warning";
            }
        }
    }
    
    if (isset($_POST['_form_action']) && $_POST['_form_action'] === 'remove_synonym') {
        $type_name = trim($_POST['type_name'] ?? '');
        $synonym_to_remove = trim($_POST['synonym_to_remove'] ?? '');
        
        if (!empty($type_name) && !empty($synonym_to_remove)) {
            if (isset($annotation_config['annotation_types'][$type_name]['synonyms'])) {
                $key = array_search($synonym_to_remove, $annotation_config['annotation_types'][$type_name]['synonyms']);
                if ($key !== false) {
                    unset($annotation_config['annotation_types'][$type_name]['synonyms'][$key]);
                    $annotation_config['annotation_types'][$type_name]['synonyms'] = array_values($annotation_config['annotation_types'][$type_name]['synonyms']);
                    saveJsonFile($config_file, $annotation_config);
                    $message = "Removed '$synonym_to_remove' as synonym of '$type_name'";
                    $messageType = "success";
                }
            }
        }
    }
    
    if (isset($_POST['_form_action']) && $_POST['_form_action'] === 'update_display_label') {
        $type_name = trim($_POST['type_name'] ?? '');
        $display_label = trim($_POST['display_label'] ?? '');
        
        if (!empty($type_name) && isset($annotation_config['annotation_types'][$type_name])) {
            $annotation_config['annotation_types'][$type_name]['display_label'] = $display_label;
            saveJsonFile($config_file, $annotation_config);
            $message = "Updated display label for '$type_name'";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['_form_action']) && $_POST['_form_action'] === 'update_type_description') {
        $type_name = trim($_POST['type_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($type_name) && isset($annotation_config['annotation_types'][$type_name])) {
            $annotation_config['annotation_types'][$type_name]['description'] = $description;
            saveJsonFile($config_file, $annotation_config);
            $message = "Updated description for '$type_name'";
            $messageType = "success";
        }
    }
    
    if (isset($_POST['delete_annotation_type'])) {
        $type_name = trim($_POST['type_name'] ?? '');
        
        if (!empty($type_name) && isset($annotation_config['annotation_types'][$type_name])) {
            $type_config = $annotation_config['annotation_types'][$type_name];
            
            // Only allow delete if 0 annotations and 0 features
            if (($type_config['annotation_count'] ?? 0) === 0 && ($type_config['feature_count'] ?? 0) === 0) {
                unset($annotation_config['annotation_types'][$type_name]);
                saveJsonFile($config_file, $annotation_config);
                $message = "Successfully deleted annotation type '$type_name'";
                $messageType = "success";
            } else {
                $message = "Cannot delete '$type_name' - it has annotations or features in the database";
                $messageType = "danger";
            }
        }
    }
}

// Reorganize includes
include_once __DIR__ . '/../includes/layout.php';

// Get config
$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

// Configure display
$display_config = [
    'title' => 'Manage Annotations - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_annotations.php',
];

// Prepare data for content file
$data = [
    'annotation_config' => $annotation_config,
    'all_db_annotation_types' => $all_db_annotation_types,
    'file_write_error' => $file_write_error,
    'message' => $message,
    'messageType' => $messageType,
    'config' => $config,
    'page_script' => '/' . $site . '/js/modules/manage-annotations.js',
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
