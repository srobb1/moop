<?php
/**
 * BLAST Results Visualizer
 * Parses BLAST XML/text output and creates interactive visualizations
 * Displays: summary table, coverage maps, alignment viewer
 * 
 * HSP visualization with connecting lines adapted from locBLAST
 * (https://github.com/cobilab/locBLAST)
 * Licensed under GNU General Public License v3.0 (GPL-3.0)
 */

/**
 * Parse BLAST results from XML output
 * Maintains Hit/HSP hierarchy
 * 
 * @param string $blast_xml Raw BLAST XML output
 * @return array Parsed results with hits array (each hit contains hsps)
 */
function parseBlastResults($blast_xml) {
    $results = [
        'hits' => [],
        'query_length' => 0,
        'query_name' => '',
        'query_desc' => '',
        'total_hits' => 0,
        'error' => ''
    ];
    
    // Parse XML
    try {
        $xml = simplexml_load_string($blast_xml);
        
        if ($xml === false) {
            $results['error'] = 'Failed to parse BLAST XML output';
            return $results;
        }
        
        // Get query info using XPath to handle hyphens in element names
        $query_len = $xml->xpath('//BlastOutput_query-len');
        if (!empty($query_len)) {
            $results['query_length'] = (int)$query_len[0];
        }
        
        $query_id = $xml->xpath('//BlastOutput_query-ID');
        if (!empty($query_id)) {
            $results['query_name'] = (string)$query_id[0];
        }
        
        $query_def = $xml->xpath('//BlastOutput_query-def');
        if (!empty($query_def)) {
            $results['query_desc'] = (string)$query_def[0];
        }
        
        // Parse each hit using XPath - maintain Hit/HSP hierarchy
        $iterations = $xml->xpath('//Iteration');
        foreach ($iterations as $iteration) {
            $hits = $iteration->xpath('.//Hit');
            foreach ($hits as $hit_node) {
                // Get hit info
                $hit_id = $hit_node->xpath('./Hit_id');
                $hit_def = $hit_node->xpath('./Hit_def');
                $hit_len = $hit_node->xpath('./Hit_len');
                
                $hit_id_str = !empty($hit_id) ? (string)$hit_id[0] : '';
                $hit_def_str = !empty($hit_def) ? (string)$hit_def[0] : '';
                $hit_len_int = !empty($hit_len) ? (int)$hit_len[0] : 0;
                
                // Get all HSPs (High-scoring Segment Pairs) for this hit
                $hsps = $hit_node->xpath('.//Hsp');
                if (!empty($hsps)) {
                    $hsps_array = [];
                    $best_evalue = PHP_FLOAT_MAX;
                    $cumulative_coverage = [];
                    
                    // Process each HSP for this hit
                    foreach ($hsps as $hsp) {
                        // Use XPath for HSP elements with hyphens
                        $identities = $hsp->xpath('./Hsp_identity');
                        $align_len = $hsp->xpath('./Hsp_align-len');
                        $evalue = $hsp->xpath('./Hsp_evalue');
                        $bit_score = $hsp->xpath('./Hsp_bit-score');
                        $score = $hsp->xpath('./Hsp_score');
                        $query_from = $hsp->xpath('./Hsp_query-from');
                        $query_to = $hsp->xpath('./Hsp_query-to');
                        $hit_from = $hsp->xpath('./Hsp_hit-from');
                        $hit_to = $hsp->xpath('./Hsp_hit-to');
                        $qseq = $hsp->xpath('./Hsp_qseq');
                        $hseq = $hsp->xpath('./Hsp_hseq');
                        $midline = $hsp->xpath('./Hsp_midline');
                        
                        $identities_int = !empty($identities) ? (int)$identities[0] : 0;
                        $align_len_int = !empty($align_len) ? (int)$align_len[0] : 0;
                        $evalue_float = !empty($evalue) ? (float)$evalue[0] : 0;
                        $hit_from_int = !empty($hit_from) ? (int)$hit_from[0] : 0;
                        $hit_to_int = !empty($hit_to) ? (int)$hit_to[0] : 0;
                        
                        // Track best (smallest) evalue
                        if ($evalue_float < $best_evalue) {
                            $best_evalue = $evalue_float;
                        }
                        
                        // Track coverage regions for cumulative calculation
                        $cumulative_coverage[] = [
                            'from' => min($hit_from_int, $hit_to_int),
                            'to' => max($hit_from_int, $hit_to_int)
                        ];
                        
                        // Count gaps and similarities with gap lengths
                        $query_seq = !empty($qseq) ? (string)$qseq[0] : '';
                        $hit_seq = !empty($hseq) ? (string)$hseq[0] : '';
                        $midline_str = !empty($midline) ? (string)$midline[0] : '';
                        
                        // Count gaps and track individual gap lengths
                        $gap_count = 0;
                        $gap_lengths = [];
                        
                        // Find gaps in query sequence
                        $in_gap = false;
                        $gap_len = 0;
                        for ($i = 0; $i < strlen($query_seq); $i++) {
                            if ($query_seq[$i] === '-') {
                                if (!$in_gap) {
                                    $in_gap = true;
                                    $gap_len = 1;
                                } else {
                                    $gap_len++;
                                }
                            } else {
                                if ($in_gap) {
                                    $gap_lengths[] = $gap_len;
                                    $gap_count += $gap_len;
                                    $in_gap = false;
                                }
                            }
                        }
                        if ($in_gap) {
                            $gap_lengths[] = $gap_len;
                            $gap_count += $gap_len;
                        }
                        
                        // Find gaps in hit sequence
                        $in_gap = false;
                        $gap_len = 0;
                        for ($i = 0; $i < strlen($hit_seq); $i++) {
                            if ($hit_seq[$i] === '-') {
                                if (!$in_gap) {
                                    $in_gap = true;
                                    $gap_len = 1;
                                } else {
                                    $gap_len++;
                                }
                            } else {
                                if ($in_gap) {
                                    $gap_lengths[] = $gap_len;
                                    $gap_count += $gap_len;
                                    $in_gap = false;
                                }
                            }
                        }
                        if ($in_gap) {
                            $gap_lengths[] = $gap_len;
                            $gap_count += $gap_len;
                        }
                        
                        $total_gap_length = $gap_count;
                        $gaps = count($gap_lengths);
                        $gap_lengths_str = implode(', ', $gap_lengths);
                        
                        $similarities = strlen($midline_str) - $identities_int - substr_count($midline_str, ' ');
                        
                        // Calculate HSP subject coverage percentage
                        $subject_coverage_percent = $hit_len_int > 0 ? round((abs($hit_to_int - $hit_from_int) + 1) / $hit_len_int * 100, 2) : 0;
                        
                        $hsp_data = [
                            'identities' => $identities_int,
                            'alignment_length' => $align_len_int,
                            'evalue' => $evalue_float,
                            'bit_score' => !empty($bit_score) ? (float)$bit_score[0] : 0,
                            'score' => !empty($score) ? (int)$score[0] : 0,
                            'percent_identity' => $align_len_int > 0 ? round(($identities_int / $align_len_int) * 100, 2) : 0,
                            'query_from' => !empty($query_from) ? (int)$query_from[0] : 0,
                            'query_to' => !empty($query_to) ? (int)$query_to[0] : 0,
                            'hit_from' => $hit_from_int,
                            'hit_to' => $hit_to_int,
                            'query_seq' => $query_seq,
                            'hit_seq' => $hit_seq,
                            'midline' => $midline_str,
                            'gaps' => $gaps,
                            'gap_lengths' => $gap_lengths,
                            'gap_lengths_str' => $gap_lengths_str,
                            'total_gap_length' => $total_gap_length,
                            'similarities' => $similarities,
                            'subject_coverage_percent' => $subject_coverage_percent
                        ];
                        
                        $hsps_array[] = $hsp_data;
                    }
                    
                    // Calculate cumulative coverage for this hit across all HSPs
                    // Track query coverage (not subject coverage)
                    $query_coverage = [];
                    foreach ($hsps_array as $hsp_data) {
                        $query_coverage[] = [
                            'from' => $hsp_data['query_from'],
                            'to' => $hsp_data['query_to']
                        ];
                    }
                    
                    usort($query_coverage, function($a, $b) { return $a['from'] - $b['from']; });
                    $merged = [];
                    foreach ($query_coverage as $region) {
                        if (empty($merged) || $merged[count($merged)-1]['to'] < $region['from']) {
                            $merged[] = $region;
                        } else {
                            $merged[count($merged)-1]['to'] = max($merged[count($merged)-1]['to'], $region['to']);
                        }
                    }
                    $total_covered = 0;
                    foreach ($merged as $region) {
                        $total_covered += $region['to'] - $region['from'] + 1;
                    }
                    $query_coverage_percent = $results['query_length'] > 0 ? round(($total_covered / $results['query_length']) * 100, 2) : 0;
                    
                    // Also calculate subject cumulative coverage for display
                    $subject_cumulative_coverage = [];
                    foreach ($results['hits'] as $hit_test) {
                        if ($hit_test['subject'] === $hit_def_str) {
                            foreach ($hit_test['hsps'] as $hsp_test) {
                                $subject_cumulative_coverage[] = [
                                    'from' => min($hsp_test['hit_from'], $hsp_test['hit_to']),
                                    'to' => max($hsp_test['hit_from'], $hsp_test['hit_to'])
                                ];
                            }
                        }
                    }
                    usort($subject_cumulative_coverage, function($a, $b) { return $a['from'] - $b['from']; });
                    $merged_subject = [];
                    foreach ($subject_cumulative_coverage as $region) {
                        if (empty($merged_subject) || $merged_subject[count($merged_subject)-1]['to'] < $region['from']) {
                            $merged_subject[] = $region;
                        } else {
                            $merged_subject[count($merged_subject)-1]['to'] = max($merged_subject[count($merged_subject)-1]['to'], $region['to']);
                        }
                    }
                    $total_subject_covered = 0;
                    foreach ($merged_subject as $region) {
                        $total_subject_covered += $region['to'] - $region['from'] + 1;
                    }
                    $subject_cumulative_coverage_percent = $hit_len_int > 0 ? round(($total_subject_covered / $hit_len_int) * 100, 2) : 0;
                    
                    // Create hit entry with all its HSPs
                    $hit = [
                        'id' => $hit_id_str,
                        'subject' => $hit_def_str,
                        'length' => $hit_len_int,
                        'hsps' => $hsps_array,
                        'best_evalue' => $best_evalue,
                        'num_hsps' => count($hsps_array),
                        'query_coverage_percent' => $query_coverage_percent,
                        'subject_cumulative_coverage_percent' => $subject_cumulative_coverage_percent
                    ];
                    
                    $results['hits'][] = $hit;
                }
            }
        }
        
        $results['total_hits'] = count($results['hits']);
        
    } catch (Exception $e) {
        $results['error'] = 'XML parsing error: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Generate HTML for hits summary table
 * 
 * @param array $results Parsed BLAST results
 * @return string HTML table
 */
function generateHitsSummaryTable($results) {
    $html = '<div class="blast-hits-summary mb-4">';
    $html .= '<h6><i class="fa fa-table"></i> Hits Summary (' . $results['total_hits'] . ' hits found)</h6>';
    $html .= '<div style="overflow-x: auto;">';
    $html .= '<table class="table table-sm table-striped blast-hits-table">';
    $html .= '<thead class="table-light">';
    $html .= '<tr>';
    $html .= '<th style="width: 5%">#</th>';
    $html .= '<th style="width: 40%">Subject</th>';
    $html .= '<th style="width: 20%">Query Coverage %</th>';
    $html .= '<th style="width: 12%">E-value</th>';
    $html .= '<th style="width: 12%">HSPs</th>';
    $html .= '<th style="width: 11%">Action</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    foreach ($results['hits'] as $idx => $hit) {
        $hit_num = $idx + 1;
        $evalue_display = $hit['best_evalue'] < 1e-100 ? '0' : sprintf('%.2e', $hit['best_evalue']);
        $query_coverage = $hit['query_coverage_percent'];
        $coverage_bar_width = min(100, $query_coverage);
        
        // Color based on coverage percentage
        if ($query_coverage >= 80) {
            $coverage_color = '#28a745'; // Green - excellent coverage
        } elseif ($query_coverage >= 50) {
            $coverage_color = '#ffc107'; // Yellow - good coverage
        } elseif ($query_coverage >= 30) {
            $coverage_color = '#fd7e14'; // Orange - moderate coverage
        } else {
            $coverage_color = '#dc3545'; // Red - low coverage
        }
        
        $html .= '<tr>';
        $html .= '<td><strong>' . $hit_num . '</strong></td>';
        $html .= '<td><small>' . htmlspecialchars(substr($hit['subject'], 0, 60)) . '</small></td>';
        $html .= '<td>';
        $html .= '<div class="blast-coverage-bar" style="width: 100%; background: #e9ecef; border-radius: 4px; overflow: hidden;">';
        $html .= '<div style="width: ' . $coverage_bar_width . '%; background: ' . $coverage_color . '; height: 20px; display: flex; align-items: center; justify-content: center;">';
        $html .= '<small style="font-weight: bold; color: white;">' . $query_coverage . '%</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '<td><small>' . $evalue_display . '</small></td>';
        $html .= '<td>' . $hit['num_hsps'] . '</td>';
        $html .= '<td><button class="btn btn-xs btn-primary" onclick="document.getElementById(\'hit-' . $hit_num . '\').scrollIntoView({behavior: \'smooth\', block: \'start\'})" title="Scroll to alignment">View</button></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate BLAST graphical results using SVG
 * Displays hits/HSPs as colored rectangles with score-based coloring
 * Similar to canvas graph but with better styling and E-value display
 * 
 * @param array $results Parsed BLAST results
 * @return string SVG HTML
 */
function generateBlastGraphicalView($results) {
    if ($results['query_length'] <= 0 || empty($results['hits'])) {
        return '';
    }
    
    $query_len = $results['query_length'];
    $canvas_width = 1000;
    $canvas_height_per_row = 25;
    $total_rows = 0;
    
    // Count total rows (one row per HSP) - limit to top 2 HSPs per hit
    foreach ($results['hits'] as $hit) {
        $total_rows += min(2, count($hit['hsps']));
    }
    
    $canvas_height = 120 + ($total_rows * $canvas_height_per_row) + 40;
    $img_width = 850;
    $xscale = $img_width / $query_len;
    $top_margin = 100;
    $left_margin = 200;
    $right_margin = 100;
    
    // Determine tick distance based on query length
    $tick_dist = 100;
    if ($query_len > 2450) $tick_dist = 150;
    if ($query_len > 3900) $tick_dist = 200;
    if ($query_len > 5000) $tick_dist = 300;
    if ($query_len > 7500) $tick_dist = round($query_len / 5);
    
    $html = '<div style="margin: 20px 0; overflow-x: auto; border: 1px solid #ddd; border-radius: 8px; background: white;">';
    $html .= '<svg width="' . ($canvas_width + $left_margin + $right_margin) . '" height="' . $canvas_height . '" style="font-family: Arial, sans-serif;">';
    
    // Background
    $html .= '<rect width="' . ($canvas_width + $left_margin + $right_margin) . '" height="' . $canvas_height . '" fill="#f9f9f9"/>';
    
    // Title
    $html .= '<text x="' . ($left_margin + ($img_width / 2)) . '" y="30" font-size="18" font-weight="bold" text-anchor="middle" fill="#333">Query Length (' . $query_len . ' bp)</text>';
    
    // Score legend
    $legend_y = 50;
    $legend_items = [
        ['label' => '<40', 'color' => '#000000', 'range' => '<40'],
        ['label' => '40-50', 'color' => '#0047c8', 'range' => '40-50'],
        ['label' => '50-80', 'color' => '#77de75', 'range' => '50-80'],
        ['label' => '80-200', 'color' => '#e967f5', 'range' => '80-200'],
        ['label' => '≥200', 'color' => '#e83a2d', 'range' => '200+']
    ];
    
    $legend_x = $left_margin;
    $legend_width = $img_width / count($legend_items);
    foreach ($legend_items as $item) {
        $html .= '<rect x="' . $legend_x . '" y="' . $legend_y . '" width="' . $legend_width . '" height="20" fill="' . $item['color'] . '"/>';
        $html .= '<text x="' . ($legend_x + ($legend_width / 2)) . '" y="' . ($legend_y + 15) . '" font-size="11" font-weight="bold" text-anchor="middle" fill="white">' . $item['label'] . '</text>';
        $legend_x += $legend_width;
    }
    
    // Horizontal line under legend
    $html .= '<line x1="' . $left_margin . '" y1="' . ($legend_y + 25) . '" x2="' . ($left_margin + $img_width) . '" y2="' . ($legend_y + 25) . '" stroke="#333" stroke-width="2"/>';
    
    // Tick marks and labels
    $vline_tag = $tick_dist;
    for ($l = $tick_dist; $l + ($tick_dist / 2) < $query_len; $l += $tick_dist) {
        $x = $left_margin + ($l * $xscale);
        // Vertical line
        $html .= '<line x1="' . $x . '" y1="' . ($legend_y + 25) . '" x2="' . $x . '" y2="' . $canvas_height . '" stroke="#ccc" stroke-width="1"/>';
        // Tick label
        $html .= '<text x="' . $x . '" y="' . ($legend_y + 45) . '" font-size="12" text-anchor="middle" fill="#333">' . $vline_tag . '</text>';
        $vline_tag += $tick_dist;
    }
    
    // E-value column header
    $html .= '<text x="' . ($left_margin + $img_width + 15) . '" y="' . ($legend_y + 45) . '" font-size="12" font-weight="bold" fill="#333">E-value</text>';
    
    // Draw hits/HSPs
    $current_y = $top_margin;
    $prev_subject = '';
    
    foreach ($results['hits'] as $hit_idx => $hit) {
        $subject_name = substr($hit['subject'], 0, 40);
        $is_new_subject = ($prev_subject !== $hit['subject']);
        
        if ($is_new_subject && $prev_subject !== '') {
            $current_y += 5; // Add spacing between different subjects
        }
        
        // Subject name (only once per hit)
        if ($is_new_subject) {
            $html .= '<text x="5" y="' . ($current_y + 15) . '" font-size="11" font-weight="bold" fill="#333">' . htmlspecialchars($subject_name) . '</text>';
        }
        
        // HSPs for this hit - limit to top 2
        foreach ($hit['hsps'] as $hsp_idx => $hsp) {
            if ($hsp_idx >= 2) break; // Only show top 2 HSPs
            
            $start_pos = $hsp['query_from'];
            $end_pos = $hsp['query_to'];
            $score = $hsp['bit_score'];
            
            // Determine color based on bit score
            if ($score >= 200) {
                $fill_color = 'rgba(255, 50, 40, 0.8)';
            } elseif ($score >= 80) {
                $fill_color = 'rgba(235,96,247, 0.8)';
            } elseif ($score >= 50) {
                $fill_color = 'rgba(119,222,117, 0.8)';
            } elseif ($score >= 40) {
                $fill_color = 'rgba(0,62,203, 0.8)';
            } else {
                $fill_color = 'rgba(10,10,10, 0.8)';
            }
            
            $rect_x = $left_margin + ($start_pos * $xscale);
            $rect_width = (($end_pos - $start_pos + 1) * $xscale);
            
            // Convert rgba to hex for SVG
            if ($score >= 200) {
                $fill_hex = '#ff3228';
            } elseif ($score >= 80) {
                $fill_hex = '#eb60f7';
            } elseif ($score >= 50) {
                $fill_hex = '#77de75';
            } elseif ($score >= 40) {
                $fill_hex = '#003ecb';
            } else {
                $fill_hex = '#0a0a0a';
            }
            
            // HSP rectangle - clickable
            $html .= '<g onclick="document.getElementById(\'hit-' . ($hit_idx + 1) . '\').scrollIntoView({behavior: \'smooth\', block: \'start\'})" style="cursor: pointer;">';
            $html .= '<title>Hit ' . ($hit_idx + 1) . ' HSP ' . ($hsp_idx + 1) . ': ' . round($hsp['percent_identity'], 1) . '% identity | E-value: ' . sprintf('%.2e', $hsp['evalue']) . '</title>';
            $html .= '<rect x="' . $rect_x . '" y="' . ($current_y) . '" width="' . $rect_width . '" height="16" fill="' . $fill_hex . '" stroke="#333" stroke-width="0.5" rx="2"/>';
            $html .= '</g>';
            
            // E-value on the right
            $evalue_display = $hsp['evalue'] < 1e-100 ? '0' : sprintf('%.2e', $hsp['evalue']);
            $html .= '<text x="' . ($left_margin + $img_width + 15) . '" y="' . ($current_y + 12) . '" font-size="10" fill="#333">' . $evalue_display . '</text>';
            
            $current_y += $canvas_height_per_row;
        }
        
        $prev_subject = $hit['subject'];
    }
    
    $html .= '</svg>';
    $html .= '</div>';
    
    // Add legend explaining the colors
    $html .= '<div style="margin: 15px 0; background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 15px;">';
    $html .= '<strong style="display: block; margin-bottom: 10px;"><i class="fa fa-info-circle"></i> Legend - Bit Score Color Coding:</strong>';
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
    
    $legend_items = [
        ['color' => '#0a0a0a', 'label' => '< 40', 'desc' => 'Weak alignment'],
        ['color' => '#003ecb', 'label' => '40 - 50', 'desc' => 'Moderate alignment'],
        ['color' => '#77de75', 'label' => '50 - 80', 'desc' => 'Good alignment'],
        ['color' => '#eb60f7', 'label' => '80 - 200', 'desc' => 'Very good alignment'],
        ['color' => '#ff3228', 'label' => '≥ 200', 'desc' => 'Excellent alignment']
    ];
    
    foreach ($legend_items as $item) {
        $html .= '<div style="display: flex; align-items: center;">';
        $html .= '<div style="width: 24px; height: 24px; background: ' . $item['color'] . '; border-radius: 3px; margin-right: 10px;"></div>';
        $html .= '<div>';
        $html .= '<strong>' . $item['label'] . '</strong><br>';
        $html .= '<small style="color: #666;">' . $item['desc'] . '</small>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
/**
 * Generate alignment viewer section
 * Displays alignments organized by Hit, with multiple HSPs per Hit
 * 
 * @param array $results Parsed BLAST results from parseBlastResults()
 * @return string HTML with alignment viewer
 */
function generateAlignmentViewer($results, $blast_program = 'blastn') {
    $html = '<div class="blast-alignment-viewer mt-4">';
    $html .= '<h6><i class="fa fa-align-justify"></i> Detailed Alignments (HSPs)</h6>';
    $html .= '<small class="text-muted d-block mb-3">Each Hit section contains one or more High-Scoring Segment Pairs (HSPs)</small>';
    
    if (empty($results['hits'])) {
        $html .= '<div class="alert alert-info"><small>No alignments to display</small></div>';
        return $html . '</div>';
    }
    
    // Determine correct unit based on program
    $unit = 'bp';
    if (strpos($blast_program, 'blastp') !== false || strpos($blast_program, 'tblastn') !== false) {
        $unit = 'aa';
    }
    
    $html .= '<div style="background: #f8f9fa; border-radius: 4px; overflow-x: auto;">';
    
    foreach ($results['hits'] as $hit_idx => $hit) {
        $hit_num = $hit_idx + 1;
        $evalue_display = $hit['best_evalue'] < 1e-100 ? '0' : sprintf('%.2e', $hit['best_evalue']);
        
        // Hit header card
        $html .= '<div id="hit-' . $hit_num . '" style="padding: 15px; border-bottom: 2px solid #007bff; scroll-margin-top: 20px; background: #f0f7ff; margin-bottom: 15px;">';
        $html .= '<h5 style="margin-bottom: 10px; color: #007bff;">';
        $html .= '<strong>Hit ' . $hit_num . ': ' . htmlspecialchars($hit['subject']) . '</strong>';
        $html .= '</h5>';
        $html .= '<small class="d-block" style="margin-bottom: 10px;">';
        $html .= '<strong>Hit ID:</strong> ' . htmlspecialchars($hit['id']) . ' | ';
        $html .= '<strong>Length:</strong> ' . $hit['length'] . ' ' . $unit . ' | ';
        $html .= '<strong>Best E-value:</strong> ' . $evalue_display . ' | ';
        $html .= '<strong>Number of HSPs:</strong> ' . $hit['num_hsps'] . ' | ';
        $html .= '<strong>Query Coverage:</strong> ' . $hit['query_coverage_percent'] . '% | ';
        $html .= '<strong>Subject Coverage:</strong> ' . $hit['subject_cumulative_coverage_percent'] . '%';
        $html .= '</small>';
        $html .= '</div>';
        
        // HSPs for this hit
        foreach ($hit['hsps'] as $hsp_idx => $hsp) {
            $hsp_num = $hsp_idx + 1;
            
            $html .= '<div style="padding: 15px; border-bottom: 1px solid #dee2e6; margin-left: 15px; background: #ffffff; margin-bottom: 10px; border-left: 4px solid #28a745;">';
            $html .= '<h6 style="margin-bottom: 10px;"><strong>HSP ' . $hsp_num . '</strong></h6>';
            $html .= '<small class="text-muted d-block" style="margin-bottom: 10px;">';
            $html .= 'E-value: ' . sprintf('%.2e', $hsp['evalue']) . ' | ';
            $html .= 'Alignment length: ' . $hsp['alignment_length'] . ' | ';
            $html .= 'Identity: ' . $hsp['identities'] . '/' . $hsp['alignment_length'] . ' (' . $hsp['percent_identity'] . '%) | ';
            $html .= 'Similarities: ' . $hsp['similarities'] . ' | ';
            $html .= 'Gaps: ' . $hsp['gaps'];
            if ($hsp['gaps'] > 0) {
                $html .= ' (lengths: ' . $hsp['gap_lengths_str'] . ', total: ' . $hsp['total_gap_length'] . ')';
            }
            $html .= '</small>';
            
            // Query coverage information for this HSP
            $query_hsp_coverage = $results['query_length'] > 0 ? round((($hsp['query_to'] - $hsp['query_from'] + 1) / $results['query_length']) * 100, 2) : 0;
            $html .= '<small class="d-block" style="margin-bottom: 10px; background: #e7f3ff; padding: 8px; border-radius: 3px; border-left: 3px solid #007bff;">';
            $html .= '<strong>Query Coverage (This HSP):</strong> ';
            $html .= $query_hsp_coverage . '% (' . ($hsp['query_to'] - $hsp['query_from'] + 1) . '/' . $results['query_length'] . ') | ';
            $html .= '<strong>Subject Coverage (This HSP):</strong> ';
            $html .= $hsp['subject_coverage_percent'] . '% (' . abs($hsp['hit_to'] - $hsp['hit_from']) + 1 . '/' . $results['hits'][$hit_idx]['length'] . ')';
            $html .= '</small>';
            
            // Display alignment in monospace with frame-aware formatting
            $html .= '<pre style="background: white; border: 1px solid #dee2e6; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; margin: 0; font-family: \'Courier New\', monospace;">';
            
            // Use frame-aware alignment formatter if frames are available
            if (isset($hsp['query_frame']) || isset($hsp['hit_frame'])) {
                $query_frame = isset($hsp['query_frame']) ? (int)$hsp['query_frame'] : 0;
                $hit_frame = isset($hsp['hit_frame']) ? (int)$hsp['hit_frame'] : 0;
                $alignment_text = formatBlastAlignment(
                    $hsp['alignment_length'],
                    $hsp['query_seq'],
                    $hsp['query_from'],
                    $hsp['query_to'],
                    $hsp['midline'],
                    $hsp['hit_seq'],
                    $hsp['hit_from'],
                    $hsp['hit_to'],
                    'Plus',
                    $query_frame,
                    $hit_frame
                );
                $html .= htmlspecialchars($alignment_text);
            } else {
                // Fallback to simple formatting
                $label_width = 15;
                $query_label = str_pad('Query  ' . $hsp['query_from'], $label_width);
                $midline_label = str_pad('', $label_width);
                $sbjct_label = str_pad('Sbjct  ' . $hsp['hit_from'], $label_width);
                
                $html .= $query_label . htmlspecialchars($hsp['query_seq']) . ' ' . $hsp['query_to'] . "\n";
                $html .= $midline_label . htmlspecialchars($hsp['midline']) . "\n";
                $html .= $sbjct_label . htmlspecialchars($hsp['hit_seq']) . ' ' . $hsp['hit_to'] . "\n";
            }
            
            $html .= '</pre>';
            
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate BLAST results statistics summary
 * Pretty card showing overall results statistics
 * 
 * @param array $results Parsed BLAST results
 * @param string $query_seq Query sequence
 * @param string $blast_program BLAST program name
 * @return string HTML statistics card
 */
function generateBlastStatisticsSummary($results, $query_seq, $blast_program) {
    if ($results['total_hits'] === 0) {
        return '';
    }
    
    $html = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
    
    // Title
    $html .= '<h4 style="margin: 0 0 20px 0; font-weight: bold;"><i class="fa fa-chart-bar"></i> BLAST Search Statistics</h4>';
    
    // Statistics grid
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">';
    
    // Query info
    $html .= '<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #4fc3f7;">';
    $html .= '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Query</div>';
    $html .= '<div style="font-size: 24px; font-weight: bold;">' . strlen($query_seq) . ' bp</div>';
    if (!empty($results['query_name'])) {
        $html .= '<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">' . htmlspecialchars(substr($results['query_name'], 0, 30)) . '</div>';
    }
    $html .= '</div>';
    
    // Hits found
    $html .= '<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #81c784;">';
    $html .= '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Hits Found</div>';
    $html .= '<div style="font-size: 24px; font-weight: bold;">' . $results['total_hits'] . '</div>';
    $html .= '<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Subject sequences</div>';
    $html .= '</div>';
    
    // Best hit info
    $best_hit = $results['hits'][0];
    $best_hsp = $best_hit['hsps'][0];
    $html .= '<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #ffa726;">';
    $html .= '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Best E-value</div>';
    $evalue_display = $best_hit['best_evalue'] < 1e-100 ? '0' : sprintf('%.2e', $best_hit['best_evalue']);
    $html .= '<div style="font-size: 24px; font-weight: bold;">' . $evalue_display . '</div>';
    $html .= '<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Top hit</div>';
    $html .= '</div>';
    
    // Best identity
    $html .= '<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #ef5350;">';
    $html .= '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Best Identity</div>';
    $html .= '<div style="font-size: 24px; font-weight: bold;">' . $best_hsp['percent_identity'] . '%</div>';
    $html .= '<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">' . $best_hsp['identities'] . '/' . $best_hsp['alignment_length'] . ' bp/aa</div>';
    $html .= '</div>';
    
    // Total HSPs
    $total_hsps = 0;
    foreach ($results['hits'] as $hit) {
        $total_hsps += $hit['num_hsps'];
    }
    $html .= '<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #ab47bc;">';
    $html .= '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Total HSPs</div>';
    $html .= '<div style="font-size: 24px; font-weight: bold;">' . $total_hsps . '</div>';
    $html .= '<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Alignments</div>';
    $html .= '</div>';
    
    // Program
    $html .= '<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #29b6f6;">';
    $html .= '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Program</div>';
    $html .= '<div style="font-size: 18px; font-weight: bold;">' . strtoupper(htmlspecialchars($blast_program)) . '</div>';
    $html .= '<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Sequence search</div>';
    $html .= '</div>';
    
    $html .= '</div>'; // End grid
    
    $html .= '</div>'; // End container
    
    return $html;
}

/**
 * Generate complete BLAST results visualization
 * Combines all visualization components
 * 
 * @param array $blast_result Result from executeBlastSearch()
 * @param string $query_seq The query sequence
 * @param string $blast_program The BLAST program used
 * @return string Complete HTML visualization
 */
function generateCompleteBlastVisualization($blast_result, $query_seq, $blast_program) {
    if (!$blast_result['success']) {
        return '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> No results to visualize</div>';
    }
    
    $results = parseBlastResults($blast_result['output']);
    
    // Check for parsing errors
    if (!empty($results['error'])) {
        return '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> Error parsing results: ' . htmlspecialchars($results['error']) . '</div>';
    }
    
    if ($results['total_hits'] === 0) {
        return '<div class="alert alert-info"><i class="fa fa-info-circle"></i> No significant matches found</div>';
    }
    
    $html = '<div class="blast-visualization">';
    
    // Determine correct unit based on program
    $unit = 'bp';
    if (strpos($blast_program, 'blastp') !== false || strpos($blast_program, 'tblastn') !== false) {
        $unit = 'aa';
    }
    
    // Query info section (first)
    $html .= '<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">';
    $html .= '<h6 style="margin-bottom: 10px; color: #333;"><i class="fa fa-dna"></i> Query</h6>';
    $html .= '<small>';
    if (!empty($results['query_name'])) {
        $html .= '<strong>Name:</strong> ' . htmlspecialchars($results['query_name']) . '<br>';
    }
    if (!empty($results['query_desc'])) {
        $html .= '<strong>Description:</strong> ' . htmlspecialchars($results['query_desc']) . '<br>';
    }
    $html .= '<strong>Length:</strong> ' . $results['query_length'] . ' ' . $unit . '<br>';
    $html .= '<strong>Total Hits:</strong> ' . $results['total_hits'];
    $html .= '</small>';
    $html .= '</div>';
    
    // Collapsible search parameters section
    $html .= '<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">';
    $html .= '<div style="padding: 15px; cursor: pointer; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;" onclick="document.getElementById(\'search-params\').style.display = document.getElementById(\'search-params\').style.display === \'none\' ? \'block\' : \'none\'; this.querySelector(\'i\').style.transform = document.getElementById(\'search-params\').style.display === \'none\' ? \'rotate(0deg)\' : \'rotate(180deg)\';">';
    $html .= '<h6 style="margin: 0; color: #333;"><i class="fa fa-cog"></i> Search Parameters</h6>';
    $html .= '<i class="fa fa-chevron-down" style="transition: transform 0.2s; transform: rotate(0deg);"></i>';
    $html .= '</div>';
    
    $html .= '<div id="search-params" style="display: none; padding: 15px;">';
    
    // First row: Database and Program
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 15px;">';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Database</small><br>';
    $html .= '<small>protein.aa.fa</small>';
    $html .= '</div>';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Posted Date</small><br>';
    $html .= '<small>Nov 12, 2025 10:40 PM</small>';
    $html .= '</div>';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Database Size</small><br>';
    $html .= '<small>21,106,416 letters | 54,384 sequences</small>';
    $html .= '</div>';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Program</small><br>';
    $html .= '<small>' . strtoupper(htmlspecialchars($blast_program)) . '</small>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    // Second row: Matrix and Parameters
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Matrix</small><br>';
    $html .= '<small>BLOSUM62</small>';
    $html .= '</div>';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Gap Penalties</small><br>';
    $html .= '<small>Existence: 11, Extension: 1</small>';
    $html .= '</div>';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Window for Multiple Hits</small><br>';
    $html .= '<small>40</small>';
    $html .= '</div>';
    
    $html .= '<div>';
    $html .= '<small style="color: #666; font-weight: bold;">Threshold</small><br>';
    $html .= '<small>11</small>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    // HSP visualization with connecting lines (locBLAST style)
    $html .= generateHspVisualizationWithLines($results);
    
    // Graphical view (canvas-style but SVG-based) - moved above summary table
    $html .= generateBlastGraphicalView($results);
    
    // Summary table
    $html .= generateHitsSummaryTable($results);
    
    // Alignment viewer
    $html .= generateAlignmentViewer($results, $blast_program);
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate HSP visualization with connecting lines (ported from locBLAST)
 * Displays HSPs as colored segments with lines connecting adjacent HSPs
 * Adapted from: https://github.com/cobilab/locBLAST (GPL-3.0)
 * 
 * @param array $results Parsed BLAST results
 * @return string HTML with HSP visualization
 */
function generateHspVisualizationWithLines($results) {
    if (empty($results['hits']) || $results['query_length'] <= 0) {
        return '';
    }
    
    $html = '<div class="blast-hsp-visualization" style="margin: 20px 0; background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">';
    $html .= '<h6><i class="fa fa-align-left"></i> HSP Coverage Map (with connecting lines)</h6>';
    $html .= '<small class="text-muted">Each color represents a different bit score range. Lines connect adjacent HSPs on the query sequence.</small>';
    
    // Add CSS for HSP visualization
    $html .= '<style>';
    $html .= '.hsp-row { display: flex; align-items: center; margin-bottom: 12px; }';
    $html .= '.hsp-label { min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; word-break: break-all; }';
    $html .= '.hsp-segments { display: flex; align-items: center; width: 800px; position: relative; }';
    $html .= '.hsp-segment { height: 16px; display: inline-block; margin-right: 0; cursor: pointer; border: 1px solid #333; transition: opacity 0.2s; }';
    $html .= '.hsp-segment:hover { opacity: 0.8; }';
    $html .= '.hsp-gap { height: 4px; background: #e0e0e0; display: inline-block; margin-top: 6px; }';
    $html .= '.hsp-connector { width: 1px; height: 6px; background: #000; display: inline-block; margin: 5px 0; }';
    $html .= '.color-black { background-color: #000000; }';
    $html .= '.color-blue { background-color: #0047c8; }';
    $html .= '.color-green { background-color: #77de75; }';
    $html .= '.color-purple { background-color: #e967f5; }';
    $html .= '.color-red { background-color: #e83a2d; }';
    $html .= '</style>';
    
    $html .= '<div style="margin-top: 15px; margin: 0 auto; width: 1000px; text-align: center;">';
    
    // Pixel unit calculation based on query length (800px width)
    $px_unit = 800 / $results['query_length'];
    
    // Add query scale bar with intelligent tick spacing
    // Calculate total number of hits and HSPs for tick line height
    $num_hits = count($results['hits']);
    $total_hsp_rows = 0;
    foreach ($results['hits'] as $hit) {
        $total_hsp_rows += count($hit['hsps']);
    }
    // Each HSP row is ~12px margin-bottom, each hit has title row ~20px
    $hsp_area_height = ($num_hits * 20) + ($total_hsp_rows * 12) + 60;
    $html .= generateQueryScale($results['query_length'], $results['query_name'], $hsp_area_height);
    
    foreach ($results['hits'] as $hit_idx => $hit) {
        $hit_num = $hit_idx + 1;
        
        // Organize HSPs by their query coordinates
        $hsp_positions = [];
        $hsp_scores = [];
        $hsp_details = [];
        
        foreach ($hit['hsps'] as $hsp_idx => $hsp) {
            $q_start = min($hsp['query_from'], $hsp['query_to']);
            $q_end = max($hsp['query_from'], $hsp['query_to']);
            
            $hsp_positions[] = [
                'start' => $q_start,
                'end' => $q_end,
                'index' => $hsp_idx
            ];
            
            $hsp_scores[$hsp_idx] = $hsp['bit_score'];
            $hsp_details[$hsp_idx] = $hsp;
        }
        
        // Sort by start position
        usort($hsp_positions, function($a, $b) {
            return $a['start'] - $b['start'];
        });
        
        // Build HTML row
        $html .= '<div class="hsp-row">';
        $html .= '<div class="hsp-label"></div>';
        $html .= '<div class="hsp-segments">';
        
        // First HSP
        if (!empty($hsp_positions)) {
            $first_hsp = $hsp_positions[0];
            $first_idx = $first_hsp['index'];
            $color = getHspColorClass($hsp_scores[$first_idx]);
            $segment_width = ($first_hsp['end'] - $first_hsp['start']) * $px_unit;
            
            // Add leading gap if needed
            if ($first_hsp['start'] > 1) {
                $gap_width = ($first_hsp['start'] - 1) * $px_unit;
                $html .= '<div class="hsp-gap" style="width: ' . $gap_width . 'px;"></div>';
            }
            
            $hsp = $hsp_details[$first_idx];
            // Extract just the subject name (first word/identifier before space or bracket)
            $hit_name = 'Hit ' . $hit_num;
            if (!empty($hit['subject'])) {
                $desc = htmlspecialchars($hit['subject']);
                // Extract first word or identifier (up to first space, bracket, or pipe)
                preg_match('/^([^\s\[\|\-]+)/', $desc, $matches);
                if (!empty($matches[1])) {
                    $hit_name = $matches[1];
                }
            }
            $title = $hit_name . ' - HSP ' . ($first_idx + 1) . ': ' . $hsp['percent_identity'] . '% identity | E-value: ' . sprintf('%.2e', $hsp['evalue']);
            $html .= '<div class="hsp-segment ' . $color . '" style="width: ' . $segment_width . 'px;" title="' . htmlspecialchars($title) . '"></div>';
            
            // Additional HSPs with connecting logic
            for ($k = 1; $k < count($hsp_positions); $k++) {
                $current = $hsp_positions[$k];
                $previous = $hsp_positions[$k - 1];
                $current_idx = $current['index'];
                
                $gap = $current['start'] - $previous['end'];
                
                if ($gap > 0) {
                    // Add connector lines for gaps
                    $html .= '<div class="hsp-connector"></div>';
                    
                    // Add gap
                    $gap_width = $gap * $px_unit;
                    $html .= '<div class="hsp-gap" style="width: ' . $gap_width . 'px;"></div>';
                    
                    // Add connector on other side
                    $html .= '<div class="hsp-connector"></div>';
                } else {
                    // Overlapping or adjacent HSPs - just connector line
                    $html .= '<div class="hsp-connector"></div>';
                }
                
                // Add current segment
                $color = getHspColorClass($hsp_scores[$current_idx]);
                $segment_width = ($current['end'] - $current['start']) * $px_unit;
                $hsp = $hsp_details[$current_idx];
                // Extract just the subject name (first word/identifier before space or bracket)
                $hit_name = 'Hit ' . $hit_num;
                if (!empty($hit['subject'])) {
                    $desc = htmlspecialchars($hit['subject']);
                    // Extract first word or identifier (up to first space, bracket, or pipe)
                    preg_match('/^([^\s\[\|\-]+)/', $desc, $matches);
                    if (!empty($matches[1])) {
                        $hit_name = $matches[1];
                    }
                }
                $title = $hit_name . ' - HSP ' . ($current_idx + 1) . ': ' . $hsp['percent_identity'] . '% identity | E-value: ' . sprintf('%.2e', $hsp['evalue']);
                $html .= '<div class="hsp-segment ' . $color . '" style="width: ' . $segment_width . 'px;" title="' . htmlspecialchars($title) . '"></div>';
            }
            
            // Trailing gap
            $last_end = $hsp_positions[count($hsp_positions) - 1]['end'];
            if ($last_end < $results['query_length']) {
                $trailing_gap = ($results['query_length'] - $last_end) * $px_unit;
                $html .= '<div class="hsp-gap" style="width: ' . $trailing_gap . 'px;"></div>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get HSP color class based on bit score
 * Mirrors locBLAST color_key function
 * 
 * @param float $score Bit score
 * @return string CSS class name for color
 */
function getHspColorClass($score) {
    if ($score <= 40) {
        return 'color-black';
    } elseif ($score <= 50) {
        return 'color-blue';
    } elseif ($score <= 80) {
        return 'color-green';
    } elseif ($score <= 200) {
        return 'color-purple';
    } else {
        return 'color-red';
    }
}

/**
 * Get inline CSS style for color class
 * 
 * @param string $colorClass CSS class name
 * @return string Inline style
 */
function getColorStyle($colorClass) {
    $styles = [
        'color-black' => 'background-color: #000000;',
        'color-blue' => 'background-color: #0047c8;',
        'color-green' => 'background-color: #77de75;',
        'color-purple' => 'background-color: #e967f5;',
        'color-red' => 'background-color: #e83a2d;'
    ];
    
    return isset($styles[$colorClass]) ? $styles[$colorClass] : '';
}

/**
 * Format BLAST alignment output with frame-aware coordinate tracking
 * Ported from locBLAST fmtprint() - handles frame shifts for BLASTx/tBLASTx
 * 
 * @param int $length Alignment length
 * @param string $query_seq Query sequence with gaps
 * @param int $query_seq_from Query start coordinate
 * @param int $query_seq_to Query end coordinate
 * @param string $align_seq Midline (match indicators)
 * @param string $sbjct_seq Subject sequence with gaps
 * @param int $sbjct_seq_from Subject start coordinate
 * @param int $sbjct_seq_to Subject end coordinate
 * @param string $p_m Plus/Minus strand
 * @param int $query_frame Query reading frame (0=none, ±1,2,3 for proteins)
 * @param int $hit_frame Subject reading frame
 * @return string Formatted alignment text
 */
function formatBlastAlignment($length, $query_seq, $query_seq_from, $query_seq_to, $align_seq, $sbjct_seq, $sbjct_seq_from, $sbjct_seq_to, $p_m = 'Plus', $query_frame = 0, $hit_frame = 0) {
    $output = '';
    $large = max(array((int)$query_seq_from, (int)$query_seq_to, (int)$sbjct_seq_from, (int)$sbjct_seq_to));
    $large_len = strlen($large);
    $n = (int)($length / 60);
    $r = $length % 60;
    if ($r > 0) $t = $n + 1;
    else $t = $n;
    
    if ($query_frame != 0 && $hit_frame != 0) {
        // Both query and subject are in frames (protein vs protein or translated)
        for ($i = 0; $i < $t; $i++) {
            if ($query_frame > 0) {
                $xn4 = $query_seq_from;
                $xs4 = substr($query_seq, 60*$i, 60);
                $xs4 = preg_replace("/-/", "", $xs4);
                $yn4 = $xn4 + (strlen($xs4) * 3) - 1;
                $output .= "\nQuery  " . str_pad($xn4, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn4;
                $xn4 = $yn4 + 1;
                $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
            } else {
                $xn = $query_seq_to;
                $xs = substr($query_seq, 60*$i, 60);
                $xs = preg_replace("/-/", "", $xs);
                $yn = $xn - (strlen($xs) * 3) + 1;
                $output .= "\nQuery  " . str_pad($xn, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn;
                $xn = $yn - 1;
                $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
            }
            if ($hit_frame > 0) {
                $an4 = $sbjct_seq_from;
                $ys4 = substr($sbjct_seq, 60*$i, 60);
                $ys4 = preg_replace("/-/", "", $ys4);
                $bn4 = $an4 + (strlen($ys4) *3) - 1;
                $output .= "\nSbjct  " . str_pad($an4, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn4 . "\n";
                $an4 = $bn4 + 1;
            } else {
                $an = $sbjct_seq_to;
                $ys = substr($sbjct_seq, 60*$i, 60);
                $ys = preg_replace("/-/", "", $ys);
                $bn = $an - (strlen($ys) *3) + 1;
                $output .= "\nSbjct  " . str_pad($an, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn . "\n";
                $an = $bn - 1;
            }
        }
    } elseif ($query_frame != 0 && $hit_frame == 0) {
        // Query is framed (tBLASTx, BLASTx), subject is not
        if ($query_frame > 0) { $xn1 = $query_seq_from; } else { $xn1 = $query_seq_to; }
        $an1 = $sbjct_seq_from;
        for ($i = 0; $i < $t; $i++) {
            if ($query_frame > 0) {
                $xs1 = substr($query_seq, 60*$i, 60);
                $xs1 = preg_replace("/-/", "", $xs1);
                $yn1 = $xn1 + (strlen($xs1) * 3) - 1;
                $output .= "\nQuery  " . str_pad($xn1, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn1;
                $xn1 = $yn1 + 1;
                $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys1 = substr($sbjct_seq, 60*$i, 60);
                $ys1 = preg_replace("/-/", "", $ys1);
                $bn1 = $an1 + strlen($ys1) - 1;
                $output .= "\nSbjct  " . str_pad($an1, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn1 . "\n";
                $an1 = $bn1 + 1;
            } else {
                $xs1 = substr($query_seq, 60*$i, 60);
                $xs1 = preg_replace("/-/", "", $xs1);
                $yn1 = $xn1 - (strlen($xs1) * 3) + 1;
                $output .= "\nQuery  " . str_pad($xn1, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn1;
                $xn1 = $yn1 - 1;
                $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys1 = substr($sbjct_seq, 60*$i, 60);
                $ys1 = preg_replace("/-/", "", $ys1);
                $bn1 = $an1 + strlen($ys1) - 1;
                $output .= "\nSbjct  " . str_pad($an1, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn1 . "\n";
                $an1 = $bn1 + 1;
            }
        }
    } elseif ($query_frame == 0 && $hit_frame != 0) {
        // Subject is framed, query is not
        if ($hit_frame > 0) { $an3 = $sbjct_seq_from; } else { $an3 = $sbjct_seq_to; }
        $xn3 = $query_seq_from;
        for ($i = 0; $i < $t; $i++) {
            if ($hit_frame > 0) {
                $xs3 = substr($query_seq, 60*$i, 60);
                $xs3 = preg_replace("/-/", "", $xs3);
                $yn3 = $xn3 + strlen($xs3) - 1;
                $output .= "\nQuery  " . str_pad($xn3, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn3;
                $xn3 = $yn3 + 1;
                $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys3 = substr($sbjct_seq, 60*$i, 60);
                $ys3 = preg_replace("/-/", "", $ys3);
                $bn3 = $an3 + (strlen($ys3) * 3) - 1;
                $output .= "\nSbjct  " . str_pad($an3, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn3 . "\n";
                $an3 = $bn3 + 1;
            } else {
                $xs3 = substr($query_seq, 60*$i, 60);
                $xs3 = preg_replace("/-/", "", $xs3);
                $yn3 = $xn3 + strlen($xs3) - 1;
                $output .= "\nQuery  " . str_pad($xn3, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn3;
                $xn3 = $yn3 + 1;
                $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys3 = substr($sbjct_seq, 60*$i, 60);
                $ys3 = preg_replace("/-/", "", $ys3);
                $bn3 = $an3 - (strlen($ys3) * 3) + 1;
                $output .= "\nSbjct  " . str_pad($an3, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn3 . "\n";
                $an3 = $bn3 - 1;
            }
        }
    } else {
        // No frames - standard nucleotide vs nucleotide
        $xn2 = $query_seq_from;
        $an2 = $sbjct_seq_from;
        for ($i = 0; $i < $t; $i++) {
            $xs2 = substr($query_seq, 60*$i, 60);
            $xs2 = preg_replace("/-/", "", $xs2);
            $yn2 = $xn2 + strlen($xs2) - 1;
            $output .= "\nQuery  " . str_pad($xn2, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn2;
            $xn2 = $yn2 + 1;
            $output .= "\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
            $ys2 = substr($sbjct_seq, 60*$i, 60);
            $ys2 = preg_replace("/-/", "", $ys2);
            if ($p_m == "Plus") {
                $bn2 = $an2 + strlen($ys2) - 1;
                $output .= "\nSbjct  " . str_pad($an2, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn2 . "\n";
                $an2 = $bn2 + 1;
            } else {
                $bn2 = $an2 - strlen($ys2) + 1;
                $output .= "\nSbjct  " . str_pad($an2, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn2 . "\n";
                $an2 = $bn2 - 1;
            }
        }
    }
    
    return $output;
}

/**
 * Generate query scale ruler with intelligent tick spacing
 * Ported from locBLAST unit() function - displays as positioned overlay
 * Includes horizontal query bar representation aligned with HSP boxes
 * 
 * @param int $query_length Total query length
 * @param string $query_name Optional query name/ID
 * @param int $hsp_area_height Height of HSP area for tick line extension
 * @return string HTML for scale labels, ticks, and query bar
 */
function generateQueryScale($query_length, $query_name = '', $hsp_area_height = 200) {
    $pxls = 800 / $query_length;  // Wider 800px instead of 500px
    $output = '<div style="margin: 0 auto 20px auto; width: 1000px;">';
    
    // Score legend bar - discrete colored boxes (800px total width to match query)
    $output .= '<div style="display: flex; align-items: center; margin-bottom: 10px;">';
    $output .= '<div style="min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; text-align: right;">Score:</div>';
    $output .= '<div style="display: flex; gap: 0; width: 800px;">';
    
    $score_ranges = [
        ['color' => '#000000', 'label' => '≤40<br><small>(Weak)</small>'],
        ['color' => '#0047c8', 'label' => '40-50'],
        ['color' => '#77de75', 'label' => '50-80'],
        ['color' => '#e967f5', 'label' => '80-200'],
        ['color' => '#e83a2d', 'label' => '≥200<br><small>(Excellent)</small>']
    ];
    
    $box_width = (800 / 5); // Divide 800px by 5 color ranges evenly
    foreach ($score_ranges as $range) {
        $output .= '<div style="width: ' . $box_width . 'px; height: 25px; background-color: ' . $range['color'] . '; border-right: 1px solid #333; display: flex; align-items: center; justify-content: center;">';
        $output .= '<span style="color: white; font-size: 10px; font-weight: bold; text-align: center; line-height: 1.2;">' . $range['label'] . '</span>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    $output .= '</div>';
    
    // Query bar - 800px width
    $output .= '<div style="display: flex; align-items: center; margin-bottom: 0;">';
    $output .= '<div style="min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; text-align: right;">';
    $output .= 'Query:';
    if (!empty($query_name)) {
        $output .= '<br><small style="font-weight: normal; color: #666;">' . htmlspecialchars(substr($query_name, 0, 20)) . '</small>';
    }
    $output .= '</div>';
    $output .= '<div style="width: 800px; height: 20px; position: relative; background: #f0f0f0; border-left: 1px solid #999; border-right: 1px solid #999; border-bottom: 1px solid #999; border-radius: 0 0 3px 3px; margin-right: 10px;">';
    $output .= '<div style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(to right, #4CAF50 0%, #45a049 100%); border-radius: 0 0 2px 2px;"></div>';
    $output .= '<div style="position: absolute; left: 5px; top: 2px; color: white; font-size: 11px; font-weight: bold;">1 - ' . $query_length . ' bp</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    // Generate scale tick numbers: Calculate evenly spaced rounded intervals
    $tick_numbers = [];
    $tick_interval = $query_length / 10;  // Base interval for 10 ticks
    
    // Determine rounding interval based on query length for clean numbers
    if ($query_length <= 100) {
        $round_to = 10;
    } elseif ($query_length <= 500) {
        $round_to = 50;
    } elseif ($query_length <= 1000) {
        $round_to = 100;
    } elseif ($query_length <= 5000) {
        $round_to = 500;
    } else {
        $round_to = 1000;
    }
    
    // Round the base interval to get consistent tick spacing
    $rounded_interval = round($tick_interval / $round_to) * $round_to;
    
    // Generate 10 tick numbers using the rounded interval
    for ($i = 1; $i <= 10; $i++) {
        $tick_num = $i * $rounded_interval;
        if ($tick_num <= $query_length) {
            $tick_numbers[] = $tick_num;
        }
    }
    
    // Scale ruler with ticks and vertical lines down to HSPs
    $output .= '<div style="display: flex; align-items: flex-start;">';
    $output .= '<div style="min-width: 100px; padding-right: 15px;"></div>';
    
    // Container for ruler - 800px width, extended height for vertical lines
    $output .= '<div style="position: relative; height: 40px; width: 800px; margin-right: 10px;">';
    
    // Draw "1" marker at the start
    $output .= '<div style="position: absolute; left: -15px; top: 10px; width: 30px; text-align: center; font-size: 11px; font-weight: bold;">1</div>';
    
    // Draw tick marks with labels and vertical reference lines
    foreach ($tick_numbers as $tick_num) {
        // Calculate pixel position for this tick number (accounting for 1-based indexing)
        $pixel_pos = (int)($pxls * ($tick_num - 1));
        
        // Vertical reference line (light gray) extending through entire HSP area
        $output .= '<div style="position: absolute; left: ' . $pixel_pos . 'px; top: 0; width: 1px; height: ' . $hsp_area_height . 'px; background: #cccccc; pointer-events: none;"></div>';
        
        // Tick mark at the top (dark gray)
        $output .= '<div style="position: absolute; left: ' . $pixel_pos . 'px; top: 0; width: 1px; height: 8px; background: #999;"></div>';
        
        // Tick label number (centered below tick mark)
        $output .= '<div style="position: absolute; left: ' . ($pixel_pos - 15) . 'px; top: 10px; width: 30px; text-align: center; font-size: 11px; font-weight: bold;">' . $tick_num . '</div>';
    }
    
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    return $output;
}
?>
