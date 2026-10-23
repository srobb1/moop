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
     * Columns that are NOT description: the ones MOOP reads to BUILD a track, plus `notes`,
     * which curators use for working notes ("Adding ... to dev stage for all in this project")
     * and which must not appear in a public browser. Every other column is description,
     * carried through as-is (see sheetMetadata()).
     *
     * Keys are normalized column names (see normalizeColumnName()).
     */
    const CONTROL_COLUMNS = [
        'track_id', 'name', 'track_path', 'filename', 'category', 'access', 'access_level',
        'color', 'description', 'notes',
        // synteny (MCScan) and alignment (MAF) inputs
        'assembly_1', 'assembly_2', 'assembly1', 'assembly2', 'bed1_path', 'bed2_path', 'maf',
    ];

    /**
     * The descriptive columns this parser used to copy, by name. Kept only so track data built
     * some other way than parseTracks() (no 'sheet_metadata' key) still yields its fields.
     */
    const METADATA_FIELDS = [
        'technique', 'institute', 'source', 'experiment',
        'developmental_stage', 'tissue', 'condition',
        'summary', 'citation', 'project', 'accession',
        'date', 'analyst', 'sciprj', 'biosample', 'ngs_file', 'mlong',
    ];

    /**
     * A track's descriptive sheet fields: column => value, empty cells left out.
     *
     * The ONE place a track type gets them. Until 2026-10-23 eleven track types each kept their
     * own list of column names (three different lists), so a new column in the sheet -- a
     * ChIP target, a developmental stage split into stage / time point / sex -- was silently
     * dropped unless all eleven were edited. Now every column that is not a CONTROL_COLUMN is
     * kept, under its normalized header name. Columns are found by name, so their order in the
     * sheet does not matter; a column that is empty for a track (or missing from the sheet
     * altogether) simply adds nothing.
     *
     * @param array $data Track data from parseTracks() (or any array carrying the fields)
     * @return array<string,string>
     */
    public static function sheetMetadata(array $data): array
    {
        $out = [];
        if (isset($data['sheet_metadata']) && is_array($data['sheet_metadata'])) {
            foreach ($data['sheet_metadata'] as $key => $value) {
                $value = is_scalar($value) ? trim((string)$value) : '';
                if ($value !== '') $out[$key] = $value;
            }
            return $out;
        }
        foreach (self::METADATA_FIELDS as $key) {
            $value = isset($data[$key]) && is_scalar($data[$key]) ? trim((string)$data[$key]) : '';
            if ($value !== '') $out[$key] = $value;
        }
        return $out;
    }

    /**
     * Normalize a sheet column header to the key the code reads.
     *
     * Lowercase and trimmed, with each run of hyphens or whitespace turned into one underscore,
     * so the sheet's "developmental-stage" is read as developmental_stage. Lowercasing alone
     * left that column under a key nothing looked up, and it was empty on every track.
     *
     * @param string $name Header as it appears in the sheet
     * @return string Normalized column name
     */
    public static function normalizeColumnName($name)
    {
        return preg_replace('/[\s-]+/', '_', strtolower(trim((string) $name)));
    }

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
        
        // Normalize column names (lowercase; hyphens and spaces become underscores)
        $header = array_map([self::class, 'normalizeColumnName'], $header);
        
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
        //
        // A track is DEFINED once: by the first row with its TRACK_ID that has a NAME and a
        // TRACK_PATH. Combo groups hold references to tracks, not copies -- a member row may be
        // just the TRACK_ID (every other cell blank), and the definition fills it in. A later
        // full copy still works, but the definition wins and a copy that disagrees is reported.
        // Until 2026-10-20 every member row had to repeat the whole track (425 of Nematostella's
        // 1,166 tracks appear more than once), an edit to one copy did not reach the others, and
        // which copy won depended on processing order.
        $definitions = [];     // track_id => track (first full row)
        $definedOnLine = [];   // track_id => sheet line of that row
        $warnings = [];
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
                // Only a block that had colour groups -- "# BAM FILES" and the like are section
                // labels, not combos, and are left unclosed on purpose.
                if ($currentCombo && !empty($currentCombo['groups'])) {
                    $warnings[] = 'line ' . ($i + 1) . ': combo "' . $currentCombo['name'] . '" was not closed with "### end" before "'
                                . trim($matches[1]) . '" -- it is ignored';
                }
                $comboName = trim($matches[1]);
                $comboGroup = null;   // rows before this combo's first "##" line belong to no group
                $inCombo = true;
                $currentCombo = [
                    // The id is also the JSON file name: '/' or '\\' would make it a path. 23
                    // Nematostella combos ("Circadian dark/light overlay ...") were never written
                    // because of it (found 2026-10-20). Other characters are left as they were so
                    // existing ids (e.g. gonads_male+female) do not change.
                    'track_id' => strtolower(str_replace([' ', ',', ':', '/', '\\'], ['_', '', '_', '_', '_'], $comboName)),
                    'name' => $comboName,
                    'organism' => $organism,
                    'assembly' => $assembly,
                    'groups' => [],
                    'line' => $i + 1
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
            
            $rowTrackId = trim((string)($row['track_id'] ?? ''));
            $inGroup = $inCombo && $currentCombo && $comboGroup !== null;

            // A reference: a member row inside a combo group with only its TRACK_ID filled in.
            if ($inGroup && $rowTrackId !== ''
                    && trim((string)($row['name'] ?? '')) === '' && trim((string)($row['track_path'] ?? '')) === '') {
                $currentCombo['groups'][$comboGroup]['tracks'][] = ['ref' => $rowTrackId, 'line' => $i + 1];
                continue;
            }

            // Skip if missing required fields
            if (empty($row['track_id']) || empty($row['name']) || empty($row['track_path'])) {
                continue;
            }
            
            // Add organism and assembly
            $row['organism'] = $organism;
            $row['assembly'] = $assembly;
            
            // Generate browser track ID from file path (for JBrowse2 config)
            $row['browser_track_id'] = $this->generateBrowserTrackId($row['track_path']);
            
            // Clean track data
            $track = $this->cleanTrackData($row);
            
            // The first full row defines the track; a later full copy is checked against it.
            $id = $track['track_id'];
            if (!isset($definitions[$id])) {
                $definitions[$id] = $track;
                $definedOnLine[$id] = $i + 1;
            } else {
                $differ = [];
                foreach ($track as $key => $value) {
                    if (in_array($key, ['browser_track_id', 'organism', 'assembly'], true)) continue;
                    $other = $definitions[$id][$key] ?? '';
                    $same  = is_array($value) || is_array($other) ? $value == $other : (string)$value === (string)$other;
                    if (!$same) $differ[] = $key;
                }
                if ($differ) {
                    $warnings[] = 'line ' . ($i + 1) . ': ' . $id . ' repeats line ' . $definedOnLine[$id]
                                . ' with a different ' . implode(', ', $differ) . ' -- line ' . $definedOnLine[$id]
                                . "'s values are used (leave only the TRACK_ID on this row to avoid copies)";
                }
            }

            // In a combo group, the member is a reference to the definition, resolved below.
            // !== null, not truthiness: a group named "0" (Woods Hole "## oranges: 0", the 0 hpf
            // strand) is falsy in PHP, so its rows were dropped and the 4 time-series combos failed.
            if ($inGroup) {
                $currentCombo['groups'][$comboGroup]['tracks'][] = ['ref' => $id, 'line' => $i + 1];
            }
        }
        
        // Add final combo track if we're still in one
        if ($currentCombo && !empty($currentCombo['groups'])) {
            $comboTracks[] = $currentCombo;
        }

        // Resolve members to their definitions; report what cannot be built.
        $seenComboIds = [];
        $resolvedCombos = [];
        foreach ($comboTracks as $combo) {
            if (isset($seenComboIds[$combo['track_id']])) {
                $warnings[] = 'line ' . $combo['line'] . ': combo "' . $combo['name'] . '" has the same name as line '
                            . $seenComboIds[$combo['track_id']] . ' -- ignored; give it its own name';
                continue;
            }
            $seenComboIds[$combo['track_id']] = $combo['line'];
            foreach ($combo['groups'] as $groupName => $group) {
                $members = [];
                foreach ($group['tracks'] as $member) {
                    if (isset($definitions[$member['ref']])) {
                        $members[] = $definitions[$member['ref']];
                    } else {
                        $warnings[] = 'line ' . $member['line'] . ': combo "' . $combo['name'] . '" refers to TRACK_ID '
                                    . $member['ref'] . ', which no row defines (a row with its NAME and TRACK_PATH)';
                    }
                }
                if (!$members) {
                    $warnings[] = 'line ' . $combo['line'] . ': combo "' . $combo['name'] . '", group "' . $groupName
                                . '" has no tracks -- the combo will not be built';
                }
                $combo['groups'][$groupName]['tracks'] = $members;
            }
            // A combo track shows its members' data, so it takes the STRICTEST member level; a member
            // with no level makes the combo not set. The `#` lines that define it have no Access cell.
            $combo['access_level'] = self::strictestAccessLevel($combo);
            $resolvedCombos[] = $combo;
        }
        $comboTracks = $resolvedCombos;
        
        return [
            'regular'  => array_values($definitions),   // each track once
            'combo'    => $comboTracks,
            'warnings' => $warnings,
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
        
        // Normalize column names (lowercase; hyphens and spaces become underscores)
        $header = array_map([self::class, 'normalizeColumnName'], $header);
        
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
            $accessLevel = trim($firstRow['access'] ?? $firstRow['access_level'] ?? '');   // blank = not set (admins only), never PUBLIC
            
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
    /**
     * The strictest access level among a combo track's member tracks; '' (not set) if any member
     * has none. An unrecognised level ranks as ADMIN, as required_access_level_value() treats it.
     */
    public static function strictestAccessLevel(array $combo): string
    {
        $rank = ['PUBLIC' => 1, 'COLLABORATOR' => 2, 'IP_IN_RANGE' => 3, 'ADMIN' => 4];
        $best = null;
        $bestRank = 0;
        foreach ($combo['groups'] ?? [] as $group) {
            foreach ($group['tracks'] ?? [] as $member) {
                $level = strtoupper(trim((string)($member['access_level'] ?? '')));
                if ($level === '') return '';
                $memberRank = $rank[$level] ?? 4;
                if ($memberRank > $bestRank) { $bestRank = $memberRank; $best = $level; }
            }
        }
        return $best ?? '';
    }

    private function normalizeTrackPath(string $path): string
    {
        // Collapse accidental double (or more) slashes in the path portion of a URL
        // without touching the :// in the scheme (https://, http://)
        if (preg_match('/^https?:\/\//i', $path)) {
            // Split on :// so we only normalize the part after the scheme
            [$scheme, $rest] = explode('://', $path, 2);
            $rest = preg_replace('#/{2,}#', '/', $rest);
            return $scheme . '://' . $rest;
        }
        return $path;
    }

    private function cleanTrackData($row)
    {
        // Map ACCESS column to access_level
        // A blank Access cell is NOT public: it stays '' = not set, which JBrowse shows to admins
        // only. Until 2026-10-20 a blank became PUBLIC -- 2,541 of 2,636 sheet rows were blank.
        $accessLevel = trim($row['access'] ?? $row['access_level'] ?? '');
        
        $track = [
            'track_id' => trim($row['track_id']),
            'browser_track_id' => trim($row['browser_track_id'] ?? ''),
            'name' => trim($row['name']),
            'TRACK_PATH' => $this->normalizeTrackPath(trim($row['track_path'])),
            'category' => trim($row['category'] ?? 'Uncategorized'),
            'access_level' => $accessLevel,
            'color' => trim($row['color'] ?? ''),
            'description' => trim($row['description'] ?? ''),
            'organism' => $row['organism'],
            'assembly' => $row['assembly'],
        ];

        // Every other column is description: kept by name, empty cells dropped (sheetMetadata()).
        // The flat copies under METADATA_FIELDS stay for code that still reads them by name.
        $sheet = [];
        foreach ($row as $column => $value) {
            if (in_array($column, self::CONTROL_COLUMNS, true)
                || in_array($column, ['organism', 'assembly', 'browser_track_id'], true)
                || $column === '') continue;
            $value = trim((string)$value);
            if ($value !== '') $sheet[$column] = $value;
        }
        $track['sheet_metadata'] = $sheet;
        foreach (self::METADATA_FIELDS as $field) {
            $track[$field] = $sheet[$field] ?? '';
        }

        return $track;
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
    
    /**
     * Generate a reproducible browser track ID from file path using MD5 hash
     * 
     * This creates hash-based track IDs for use in JBrowse2 configs to prevent
     * information disclosure in shared sessions. The ID is deterministic and 
     * reproducible, but reveals nothing about the original filename.
     * 
     * This is separate from the human-readable track_id used for management.
     * 
     * @param string $filePath Full file path to generate ID from
     * @return string Browser track ID like "track_a7f3d2e942"
     */
    public function generateBrowserTrackId($filePath)
    {
        if (empty($filePath)) {
            return '';
        }
        
        // Create MD5 hash of the file path
        $hash = md5($filePath);
        
        // Take first 10 characters for a compact ID
        return "track_" . substr($hash, 0, 10);
    }
}
