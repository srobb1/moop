<?php
/**
 * ColorSchemes - Shared color scheme definitions and utilities
 * 
 * Provides color schemes for JBrowse combo tracks with helper functions
 * for listing, suggesting, and validating color choices.
 * 
 * Matches functionality from Python generate_tracks_from_sheet.py:
 * - --list-colors: Show all available color schemes
 * - --suggest-colors N: Suggest best schemes for N files
 * 
 * @package MOOP\JBrowse
 */

class ColorSchemes
{
    /**
     * All available color schemes
     * Matches Python script COLORS dictionary
     */
    private static $schemes = [
        // Original groups
        'blues' => ['Navy', 'Blue', 'RoyalBlue', 'SteelBlue', 'DodgerBlue', 'DeepSkyBlue', 
                    'CornflowerBlue', 'SkyBlue', 'LightSkyBlue', 'LightSteelBlue', 'LightBlue'],
        'purples' => ['Indigo', 'Purple', 'DarkViolet', 'DarkSlateBlue', 'DarkOrchid', 
                      'Fuchsia', 'SlateBlue', 'MediumSlateBlue', 'MediumOrchid', 
                      'MediumPurple', 'Orchid', 'Plum', 'Thistle', 'Lavender'],
        'yellows' => ['DarkKhaki', 'Gold', 'Khaki', 'PeachPuff', 'Yellow', 'PaleGoldenrod', 
                      'Moccasin', 'PapayaWhip', 'LightGoldenrodYellow', 'LemonChiffon', 'LightYellow'],
        'cyans' => ['Teal', 'LightSeaGreen', 'CadetBlue', 'DarkTurquoise', 'Turquoise', 
                    'Aqua', 'Aquamarine', 'PaleTurquoise', 'LightCyan'],
        'pinks' => ['MediumVioletRed', 'DeepPink', 'PaleVioletRed', 'HotPink', 'LightPink', 'Pink'],
        'greens' => ['DarkGreen', 'DarkOliveGreen', 'ForestGreen', 'SeaGreen', 'Olive', 
                     'OliveDrab', 'MediumSeaGreen', 'LimeGreen', 'Lime', 'MediumSpringGreen', 
                     'DarkSeaGreen', 'MediumAquamarine', 'YellowGreen', 'LawnGreen', 
                     'LightGreen', 'GreenYellow'],
        'reds' => ['DarkRed', 'Red', 'Firebrick', 'Crimson', 'IndianRed', 'LightCoral', 
                   'Salmon', 'DarkSalmon', 'LightSalmon'],
        'oranges' => ['OrangeRed', 'Tomato', 'DarkOrange', 'Coral', 'Orange'],
        'browns' => ['Maroon', 'Brown', 'SaddleBrown', 'Sienna', 'Chocolate', 'DarkGoldenrod', 
                     'Peru', 'RosyBrown', 'Goldenrod', 'SandyBrown', 'Tan', 'Burlywood', 
                     'Wheat', 'NavajoWhite', 'Bisque', 'BlanchedAlmond', 'Cornsilk'],
        'grays' => ['Gainsboro', 'LightGray', 'Silver', 'DarkGray', 'Gray', 'DimGray', 
                    'LightSlateGray', 'SlateGray', 'DarkSlateGray'],
        'diffs' => ['Turquoise', 'Coral', 'MediumVioletRed', 'Red', 'Gold', 'Sienna', 
                    'SeaGreen', 'SkyBlue', 'BlueViolet', 'MistyRose', 'LightSlateGray'],
        
        // Expanded groups
        'rainbow' => ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', 
                      '#46f0f0', '#f032e6', '#bcf60c', '#fabebe', '#008080', '#e6beff', 
                      '#9a6324', '#fffac8', '#800000', '#aaffc3', '#808000', '#ffd8b1', 
                      '#000075', '#808080'],
        'warm' => ['#8B0000', '#DC143C', '#FF0000', '#FF4500', '#FF6347', '#FF7F50', 
                   '#FFA500', '#FFD700', '#FFFF00', '#ADFF2F', '#7FFF00', '#00FF00'],
        'cool' => ['#00008B', '#0000CD', '#0000FF', '#1E90FF', '#00BFFF', '#00CED1', 
                   '#00FFFF', '#00FA9A', '#00FF7F', '#3CB371', '#2E8B57', '#006400'],
        'earth' => ['#8B4513', '#A0522D', '#D2691E', '#CD853F', '#DEB887', '#F4A460', 
                    '#D2B48C', '#BC8F8F', '#F5DEB3', '#FFE4C4', '#FFDEAD', '#FFE4B5', 
                    '#FAEBD7', '#FAF0E6', '#FFF8DC', '#FFFACD'],
        'pastels' => ['#FFB3BA', '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF', '#E0BBE4', 
                      '#FFDFD3', '#FEC8D8', '#D5AAFF', '#B4F8C8', '#A0C4FF', '#FDFFB6', 
                      '#FFD6A5', '#CAFFBF', '#FFC6FF', '#FFFFFC'],
        'vibrant' => ['#FF006E', '#FB5607', '#FFBE0B', '#8338EC', '#3A86FF', '#06FFA5', 
                      '#FF1654', '#247BA0', '#F72585', '#4361EE', '#4CC9F0', '#7209B7', 
                      '#F77F00', '#06D6A0', '#EF476F', '#FFD166'],
        'monoblues' => ['#03045E', '#023E8A', '#0077B6', '#0096C7', '#00B4D8', '#48CAE4', 
                        '#90E0EF', '#ADE8F4', '#CAF0F8'],
        'monogreens' => ['#004B23', '#006400', '#007200', '#008000', '#38B000', '#70E000', 
                         '#9EF01A', '#CCFF33'],
        'monoreds' => ['#641220', '#6E1423', '#85182A', '#A11D33', '#A71E34', '#C9184A', 
                       '#FF4D6D', '#FF758F', '#FF8FA3'],
        'monopurples' => ['#240046', '#3C096C', '#5A189A', '#7209B7', '#9D4EDD', '#C77DFF', 
                          '#E0AAFF', '#F0D9FF'],
        'neon' => ['#FF10F0', '#39FF14', '#FFFF00', '#FF3503', '#00F5FF', '#FE019A', 
                   '#BC13FE', '#FF073A', '#FF6600', '#00FFFF', '#FF00FF', '#CCFF00'],
        'sea' => ['#001F3F', '#003D5C', '#005A7A', '#007899', '#0095B7', '#00B3D5', 
                  '#33C1E3', '#66CFF0', '#99DDFC', '#CCEBFF'],
        'forest' => ['#013220', '#2D6A4F', '#40916C', '#52B788', '#74C69D', '#95D5B2', 
                     '#B7E4C7', '#D8F3DC'],
        'sunset' => ['#03071E', '#370617', '#6A040F', '#9D0208', '#D00000', '#DC2F02', 
                     '#E85D04', '#F48C06', '#FAA307', '#FFBA08'],
        'galaxy' => ['#0B0C10', '#1F2833', '#45A29E', '#66FCF1', '#C5C6C7', '#7B2CBF', 
                     '#9D4EDD', '#C77DFF', '#E0AAFF'],
        'contrast' => ['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', 
                       '#FF00FF', '#00FFFF'],
        'grayscale' => ['#000000', '#1A1A1A', '#333333', '#4D4D4D', '#666666', '#808080', 
                        '#999999', '#B3B3B3', '#CCCCCC', '#E6E6E6', '#F2F2F2', '#FFFFFF'],
    ];
    
    /**
     * Color group metadata
     */
    private static $info = [
        'blues' => ['type' => 'sequential', 'best_for' => 'samples, replicates'],
        'purples' => ['type' => 'sequential', 'best_for' => 'larger groups, time series'],
        'yellows' => ['type' => 'sequential', 'best_for' => 'expression data'],
        'cyans' => ['type' => 'sequential', 'best_for' => 'water/aquatic samples'],
        'pinks' => ['type' => 'sequential', 'best_for' => 'small groups'],
        'greens' => ['type' => 'sequential', 'best_for' => 'large groups, plant data'],
        'reds' => ['type' => 'sequential', 'best_for' => 'treatments, stress'],
        'oranges' => ['type' => 'sequential', 'best_for' => 'small groups'],
        'browns' => ['type' => 'sequential', 'best_for' => 'large groups, earthy themes'],
        'grays' => ['type' => 'sequential', 'best_for' => 'controls, baselines'],
        'diffs' => ['type' => 'qualitative', 'best_for' => 'distinct samples'],
        'rainbow' => ['type' => 'qualitative', 'best_for' => 'maximum variety'],
        'warm' => ['type' => 'sequential', 'best_for' => 'upregulated/active'],
        'cool' => ['type' => 'sequential', 'best_for' => 'downregulated/inactive'],
        'earth' => ['type' => 'sequential', 'best_for' => 'natural/soil samples'],
        'pastels' => ['type' => 'qualitative', 'best_for' => 'subtle differences'],
        'vibrant' => ['type' => 'qualitative', 'best_for' => 'presentations, posters'],
        'monoblues' => ['type' => 'sequential', 'best_for' => 'intensity gradients'],
        'monogreens' => ['type' => 'sequential', 'best_for' => 'growth/abundance'],
        'monoreds' => ['type' => 'sequential', 'best_for' => 'severity/danger'],
        'monopurples' => ['type' => 'sequential', 'best_for' => 'epigenetic data'],
        'neon' => ['type' => 'qualitative', 'best_for' => 'high contrast needs'],
        'sea' => ['type' => 'sequential', 'best_for' => 'marine organisms'],
        'forest' => ['type' => 'sequential', 'best_for' => 'vegetation data'],
        'sunset' => ['type' => 'sequential', 'best_for' => 'time progression'],
        'galaxy' => ['type' => 'sequential', 'best_for' => 'dark backgrounds'],
        'contrast' => ['type' => 'qualitative', 'best_for' => 'accessibility'],
        'grayscale' => ['type' => 'sequential', 'best_for' => 'black & white'],
    ];
    
    /**
     * Get all color schemes
     */
    public static function getSchemes()
    {
        return self::$schemes;
    }
    
    /**
     * Get a specific color scheme
     */
    public static function getScheme($name)
    {
        return self::$schemes[$name] ?? null;
    }
    
    /**
     * Get color scheme info
     */
    public static function getInfo($name)
    {
        return self::$info[$name] ?? ['type' => 'unknown', 'best_for' => 'general use'];
    }
    
    /**
     * List all color schemes formatted for CLI
     * Matches Python --list-colors
     */
    public static function listSchemes()
    {
        // Build sorted list
        $groups = [];
        foreach (self::$schemes as $name => $colors) {
            $info = self::$info[$name] ?? ['type' => 'unknown', 'best_for' => 'general use'];
            $groups[] = [
                'name' => $name,
                'count' => count($colors),
                'type' => $info['type'],
                'best_for' => $info['best_for']
            ];
        }
        
        // Sort by count (descending), then name
        usort($groups, function($a, $b) {
            if ($b['count'] != $a['count']) {
                return $b['count'] - $a['count'];
            }
            return strcmp($a['name'], $b['name']);
        });
        
        // Display formatted table
        echo str_repeat('=', 80) . "\n";
        echo "AVAILABLE COLOR GROUPS\n";
        echo str_repeat('=', 80) . "\n\n";
        
        printf("%-15s %-8s %-12s %s\n", "Group", "Colors", "Type", "Best For");
        echo str_repeat('-', 80) . "\n";
        
        foreach ($groups as $group) {
            printf("%-15s %-8d %-12s %s\n", 
                $group['name'], 
                $group['count'], 
                $group['type'], 
                $group['best_for']
            );
        }
        
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "USAGE EXAMPLES:\n";
        echo str_repeat('-', 80) . "\n";
        echo "  ## blues: Sample Group          # Use blues color group\n";
        echo "  ## exact=OrangeRed: Group       # Use specific color\n";
        echo "  ## reds3: Group                 # Use 4th color from reds (0-indexed)\n";
        echo str_repeat('=', 80) . "\n";
    }
    
    /**
     * Suggest color schemes for a given number of files
     * Matches Python --suggest-colors N
     * 
     * @param int $numFiles Number of files needing colors
     * @param int $maxSuggestions Number of suggestions to return (default: 5)
     * @return array Top suggestions sorted by suitability
     */
    public static function suggestSchemes($numFiles, $maxSuggestions = 5)
    {
        $suitable = [];
        
        foreach (self::$schemes as $name => $colors) {
            if (count($colors) >= $numFiles) {
                $info = self::$info[$name] ?? ['type' => 'unknown', 'best_for' => 'general use'];
                $suitable[] = [
                    'name' => $name,
                    'count' => count($colors),
                    'type' => $info['type'],
                    'best_for' => $info['best_for']
                ];
            }
        }
        
        // Sort by: closest to needed count, then by name
        usort($suitable, function($a, $b) use ($numFiles) {
            $diffA = abs($a['count'] - $numFiles);
            $diffB = abs($b['count'] - $numFiles);
            if ($diffA != $diffB) {
                return $diffA - $diffB;
            }
            return strcmp($a['name'], $b['name']);
        });
        
        return array_slice($suitable, 0, $maxSuggestions);
    }
    
    /**
     * Display color scheme suggestions formatted for CLI
     * Matches Python --suggest-colors N output
     */
    public static function displaySuggestions($numFiles)
    {
        $suggestions = self::suggestSchemes($numFiles, 5);
        
        if (empty($suggestions)) {
            echo "No color schemes have $numFiles or more colors.\n";
            echo "Largest available: " . max(array_map('count', self::$schemes)) . " colors\n";
            return;
        }
        
        echo str_repeat('=', 80) . "\n";
        echo "COLOR GROUP SUGGESTIONS FOR $numFiles FILES\n";
        echo str_repeat('=', 80) . "\n\n";
        
        printf("%-15s %-8s %-12s %s\n", "Group", "Colors", "Type", "Best For");
        echo str_repeat('-', 80) . "\n";
        
        foreach ($suggestions as $group) {
            printf("%-15s %-8d %-12s %s\n", 
                $group['name'], 
                $group['count'], 
                $group['type'], 
                $group['best_for']
            );
        }
        
        echo "\nRECOMMENDED:\n";
        echo "  ## {$suggestions[0]['name']}: Your Group Name\n\n";
        echo str_repeat('=', 80) . "\n";
    }
}
