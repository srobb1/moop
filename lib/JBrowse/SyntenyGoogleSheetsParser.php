<?php
/**
 * SyntenyGoogleSheetsParser - Parse Google Sheets for dual-assembly synteny tracks
 * 
 * Handles tracks that reference TWO assemblies (PIF, MCScan, PAF synteny)
 * 
 * Required columns:
 * - track_id
 * - name
 * - track_path
 * - organism1
 * - assembly1
 * - organism2
 * - assembly2
 * 
 * Optional columns:
 * - category (default: Synteny)
 * - access_level (default: PUBLIC)
 * - bed1_path (required for MCScan .anchors tracks)
 * - bed2_path (required for MCScan .anchors tracks)
 * - description, technique, institute, source, etc.
 */

class SyntenyGoogleSheetsParser
{
    /**
     * Required columns for synteny tracks
     */
    private $requiredColumns = [
        'track_id',
        'name', 
        'track_path',
        'organism1',
        'assembly1',
        'organism2',
        'assembly2'
    ];
    
    /**
     * Download Google Sheet as TSV
     */
    public function download($sheetId, $gid)
    {
        $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=tsv&gid={$gid}";
        
        $content = @file_get_contents($url);
        
        if ($content === false) {
            throw new RuntimeException(
                "Failed to download Google Sheet. Check sheet ID and GID are correct and sheet is publicly accessible.\n" .
                "URL: $url"
            );
        }
        
        return $content;
    }
    
    /**
     * Parse TSV content for synteny tracks
     * 
     * @param string $content TSV content
     * @return array Array of synteny tracks
     */
    public function parseTracks($content)
    {
        $lines = explode("\n", $content);
        
        if (empty($lines)) {
            return [];
        }
        
        // Parse header
        $header = str_getcsv(trim($lines[0]), "\t");
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // Remove BOM
        
        // Normalize column names to lowercase
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
        
        // Validate required columns
        $validation = $this->validateColumns($validColumns);
        if (!$validation['valid']) {
            throw new RuntimeException(
                "Sheet missing required columns: " . implode(', ', $validation['missing'])
            );
        }
        
        // Parse data rows
        $tracks = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
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
            
            // Skip rows without required fields
            if (empty($row['track_id']) || empty($row['track_path'])) {
                continue;
            }
            
            // Build track data
            $track = $this->buildTrackData($row);
            
            if ($track) {
                $tracks[] = $track;
            }
        }
        
        return $tracks;
    }
    
    /**
     * Build track data from row
     */
    private function buildTrackData($row)
    {
        // Required fields
        $track = [
            'track_id' => trim($row['track_id']),
            'name' => trim($row['name']),
            'track_path' => trim($row['track_path']),
            'organism1' => trim($row['organism1']),
            'assembly1' => trim($row['assembly1']),
            'organism2' => trim($row['organism2']),
            'assembly2' => trim($row['assembly2']),
        ];
        
        // Validate required fields
        foreach ($track as $key => $value) {
            if (empty($value)) {
                return null; // Skip incomplete rows
            }
        }
        
        // Optional fields with defaults
        $track['category'] = trim($row['category'] ?? 'Synteny');
        $track['access_level'] = trim($row['access_level'] ?? $row['access'] ?? 'PUBLIC');
        $track['description'] = trim($row['description'] ?? '');
        
        // BED paths (required for MCScan .anchors tracks)
        if (isset($row['bed1_path']) && !empty($row['bed1_path'])) {
            $track['bed1_path'] = trim($row['bed1_path']);
        }
        if (isset($row['bed2_path']) && !empty($row['bed2_path'])) {
            $track['bed2_path'] = trim($row['bed2_path']);
        }
        
        // Optional metadata fields
        $optionalFields = [
            'technique', 'institute', 'source', 'experiment',
            'developmental_stage', 'tissue', 'condition',
            'summary', 'citation', 'project', 'accession',
            'date', 'analyst', 'sciprj', 'biosample', 'ngs_file', 'mlong'
        ];
        
        foreach ($optionalFields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                $track[$field] = trim($row[$field]);
            }
        }
        
        return $track;
    }
    
    /**
     * Validate that required columns exist
     */
    public function validateColumns($columns)
    {
        $missing = [];
        
        foreach ($this->requiredColumns as $required) {
            if (!in_array($required, $columns)) {
                $missing[] = $required;
            }
        }
        
        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }
    
    /**
     * Get assembly pair name (alphabetically sorted)
     * 
     * @param string $organism1
     * @param string $assembly1
     * @param string $organism2
     * @param string $assembly2
     * @return array ['name' => 'Asm1_Asm2', 'assembly1' => 'Asm1', 'assembly2' => 'Asm2']
     */
    public function getAssemblyPairName($organism1, $assembly1, $organism2, $assembly2)
    {
        $asm1 = $organism1 . '_' . $assembly1;
        $asm2 = $organism2 . '_' . $assembly2;
        
        // Sort alphabetically for consistency
        $assemblies = [$asm1, $asm2];
        sort($assemblies);
        
        return [
            'name' => implode('_', $assemblies),
            'assembly1' => $assemblies[0],
            'assembly2' => $assemblies[1]
        ];
    }
}
