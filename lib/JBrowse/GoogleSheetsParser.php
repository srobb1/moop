<?php
/**
 * GoogleSheetsParser - Handle Google Sheets data for JBrowse track loading
 * 
 * Downloads and parses Google Sheets exported as TSV format.
 * Extracts track metadata for both regular tracks and combo tracks.
 * 
 * Google Sheets URL format:
 *   https://docs.google.com/spreadsheets/d/{SHEET_ID}/export?format=tsv&gid={GID}
 * 
 * Usage:
 *   $parser = new GoogleSheetsParser();
 *   $content = $parser->download($sheetId, $gid);
 *   $tracks = $parser->parseTracks($content, $organism, $assembly);
 * 
 * @package MOOP\JBrowse
 */

class GoogleSheetsParser
{
    /**
     * Required columns for regular tracks
     */
    private $requiredColumns = ['track_id', 'name', 'track_path'];
    
    /**
     * Download Google Sheet as TSV
     * 
     * @param string $sheetId Google Sheet ID
     * @param string $gid Sheet GID (tab identifier)
     * @return string TSV content
     * @throws RuntimeException If download fails
     */
    public function download($sheetId, $gid)
    {
        $url = "https://docs.google.com/spreadsheets/d/$sheetId/export?format=tsv&gid=$gid";
        
        // Use file_get_contents with error handling
        $content = @file_get_contents($url);
        
        if ($content === false) {
            throw new RuntimeException(
                "Failed to download Google Sheet. Check sheet ID and GID are correct and sheet is publicly accessible.\n" .
                "URL: $url"
            );
        }
        
        if (empty($content)) {
            throw new RuntimeException("Downloaded sheet is empty");
        }
        
        return $content;
    }
    
    /**
     * Parse TSV content and extract tracks
     * 
     * @param string $content TSV content
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @return array ['regular' => [...], 'combo' => [...]]
     */
    public function parseTracks($content, $organism, $assembly)
    {
        $lines = explode("\n", $content);
        
        if (empty($lines)) {
            return ['regular' => [], 'combo' => []];
        }
        
        // Parse header
        $header = str_getcsv(trim($lines[0]), "\t");
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // Remove BOM
        
        // Normalize column names to lowercase for consistency
        $header = array_map('strtolower', $header);
        
        // Filter out columns starting with #
        $validColumns = [];
        $validIndices = [];
        foreach ($header as $index => $col) {
            if (!preg_match('/^#/', $col)) {
                $validColumns[] = $col;
                $validIndices[] = $index;
            }
        }
        
        // Parse all rows
        $regularTracks = [];
        $comboTracks = [];
        
        $inCombo = false;
        $comboName = null;
        $comboGroup = null;
        $comboColor = null;
        $currentCombo = null;
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            if (empty($line)) {
                continue;
            }
            
            // Check for combo track markers
            if (preg_match('/^###/', $line)) {
                // End of combo track
                if ($currentCombo && !empty($currentCombo['groups'])) {
                    $comboTracks[] = $currentCombo;
                }
                $inCombo = false;
                $comboName = null;
                $comboGroup = null;
                $currentCombo = null;
                continue;
                
            } elseif (preg_match('/^# ([^#].*)/', $line, $matches)) {
                // Start of combo track: # SIMR:Four_Adult_Tissues_MOLNG-2707
                $comboName = trim($matches[1]);
                $inCombo = true;
                $currentCombo = [
                    'track_id' => strtolower(str_replace([' ', ',', ':'], ['_', '', '_'], $comboName)),
                    'name' => $comboName,
                    'organism' => $organism,
                    'assembly' => $assembly,
                    'groups' => []
                ];
                continue;
                
            } elseif (preg_match('/^## (\S+):\s*(.+)/', $line, $matches)) {
                // Color group: ## greens: body_wall +
                $comboColor = trim($matches[1]);
                $comboGroup = trim($matches[2]);
                
                if ($currentCombo) {
                    $currentCombo['groups'][$comboGroup] = [
                        'color' => $comboColor,
                        'tracks' => []
                    ];
                }
                continue;
            }
            
            // Parse data row
            $values = str_getcsv($line, "\t");
            
            // Filter to valid columns
            $filteredValues = [];
            foreach ($validIndices as $index) {
                $filteredValues[] = $values[$index] ?? '';
            }
            
            // Create associative array
            $row = [];
            foreach ($validColumns as $index => $columnName) {
                $row[$columnName] = $filteredValues[$index] ?? '';
            }
            
            // Skip if missing required fields
            if (empty($row['track_id']) || empty($row['name']) || empty($row['track_path'])) {
                continue;
            }
            
            // Add organism and assembly
            $row['organism'] = $organism;
            $row['assembly'] = $assembly;
            
            // Clean track data
            $track = $this->cleanTrackData($row);
            
            // Add to combo group if we're in a combo track
            if ($inCombo && $currentCombo && $comboGroup) {
                $currentCombo['groups'][$comboGroup]['tracks'][] = $track;
            }
            
            // Always add to regular tracks (combo tracks are made from regular tracks)
            $regularTracks[] = $track;
        }
        
        // Add final combo track if we're still in one
        if ($currentCombo && !empty($currentCombo['groups'])) {
            $comboTracks[] = $currentCombo;
        }
        
        return [
            'regular' => $regularTracks,
            'combo' => $comboTracks
        ];
    }
    
    /**
     * Parse TSV content into array of associative arrays
     * 
     * @param string $content TSV content
     * @return array Array of rows (associative arrays)
     */
    public function parseTSV($content)
    {
        $lines = explode("\n", $content);
        
        if (empty($lines)) {
            return [];
        }
        
        // First line is header
        $header = str_getcsv(trim($lines[0]), "\t");
        
        // Remove BOM if present
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        
        // Normalize column names to lowercase for consistency
        $header = array_map('strtolower', $header);
        
        $rows = [];
        
        // Parse data rows
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            $values = str_getcsv($line, "\t");
            
            // Create associative array
            $row = [];
            foreach ($header as $index => $columnName) {
                $row[$columnName] = $values[$index] ?? '';
            }
            
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Extract regular tracks from parsed data
     * 
     * Regular tracks have a TRACK_PATH and are not part of a combo track.
     * 
     * @param array $parsed Parsed TSV data
     * @return array Regular tracks
     */
    public function extractRegularTracks($parsed)
    {
        $tracks = [];
        
        foreach ($parsed as $row) {
            // Skip if missing required fields
            if (empty($row['track_id']) || empty($row['name'])) {
                continue;
            }
            
            // Skip if no TRACK_PATH (might be combo track definition)
            if (empty($row['track_path'])) {
                continue;
            }
            
            // Skip if TRACK_PATH is "AUTO" but this is a combo track row
            if (strtoupper($row['track_path']) === 'AUTO' && !empty($row['combo_track_id'])) {
                continue;
            }
            
            // Clean up the row
            $track = $this->cleanTrackData($row);
            
            $tracks[] = $track;
        }
        
        return $tracks;
    }
    
    /**
     * Extract combo tracks from parsed data
     * 
     * Combo tracks are defined by COMBO_TRACK_ID and reference multiple files.
     * 
     * @param array $parsed Parsed TSV data
     * @return array Combo tracks
     */
    public function extractComboTracks($parsed)
    {
        $comboTracks = [];
        
        // Group rows by COMBO_TRACK_ID
        $comboGroups = [];
        foreach ($parsed as $row) {
            if (!empty($row['combo_track_id'])) {
                $comboId = $row['combo_track_id'];
                if (!isset($comboGroups[$comboId])) {
                    $comboGroups[$comboId] = [];
                }
                $comboGroups[$comboId][] = $row;
            }
        }
        
        // Process each combo track
        foreach ($comboGroups as $comboId => $rows) {
            // Use first row for track metadata
            $firstRow = $rows[0];
            
            // Map ACCESS column to access_level
            $accessLevel = trim($firstRow['access'] ?? $firstRow['access_level'] ?? 'PUBLIC');
            
            $comboTrack = [
                'track_id' => $comboId,
                'name' => $firstRow['combo_name'] ?? $comboId,
                'category' => $firstRow['category'] ?? 'Combo',
                'access_level' => $accessLevel,
                'organism' => $firstRow['organism'],
                'assembly' => $firstRow['assembly'],
                'files' => [],
                'names' => [],
                'colors' => []
            ];
            
            // Extract file paths, names, and colors
            foreach ($rows as $row) {
                if (!empty($row['track_path'])) {
                    $comboTrack['files'][] = $row['track_path'];
                    $comboTrack['names'][] = $row['name'] ?? basename($row['track_path']);
                    $comboTrack['colors'][] = $row['color'] ?? '';
                }
            }
            
            // Only add if we have files
            if (!empty($comboTrack['files'])) {
                $comboTracks[] = $comboTrack;
            }
        }
        
        return $comboTracks;
    }
    
    /**
     * Clean and normalize track data
     * 
     * @param array $row Raw row data
     * @return array Cleaned track data
     */
    private function cleanTrackData($row)
    {
        // Map ACCESS column to access_level
        $accessLevel = trim($row['access'] ?? $row['access_level'] ?? 'PUBLIC');
        
        return [
            'track_id' => trim($row['track_id']),
            'name' => trim($row['name']),
            'TRACK_PATH' => trim($row['track_path']),
            'category' => trim($row['category'] ?? 'Uncategorized'),
            'access_level' => $accessLevel,
            'color' => trim($row['color'] ?? ''),
            'description' => trim($row['description'] ?? ''),
            'organism' => $row['organism'],
            'assembly' => $row['assembly'],
            
            // Optional metadata fields
            'technique' => trim($row['technique'] ?? ''),
            'institute' => trim($row['institute'] ?? ''),
            'source' => trim($row['source'] ?? ''),
            'experiment' => trim($row['experiment'] ?? ''),
            'developmental_stage' => trim($row['developmental_stage'] ?? ''),
            'tissue' => trim($row['tissue'] ?? ''),
            'condition' => trim($row['condition'] ?? ''),
            'summary' => trim($row['summary'] ?? ''),
        ];
    }
    
    /**
     * Validate that required columns exist
     * 
     * @param array $parsed Parsed TSV data
     * @return array ['valid' => bool, 'missing' => array]
     */
    public function validateColumns($parsed)
    {
        if (empty($parsed)) {
            return [
                'valid' => false,
                'missing' => ['Sheet is empty']
            ];
        }
        
        $firstRow = $parsed[0];
        $actualColumns = array_keys($firstRow);
        
        $missing = [];
        foreach ($this->requiredColumns as $required) {
            if (!in_array($required, $actualColumns)) {
                $missing[] = $required;
            }
        }
        
        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }
    
    /**
     * Get statistics about parsed tracks
     * 
     * @param array $tracks Tracks array from parseTracks()
     * @return array Statistics
     */
    public function getStatistics($tracks)
    {
        return [
            'regular_tracks' => count($tracks['regular']),
            'combo_tracks' => count($tracks['combo']),
            'total' => count($tracks['regular']) + count($tracks['combo'])
        ];
    }
}
