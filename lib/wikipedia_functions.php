<?php
/**
 * Wikipedia enrichment helpers — extracted from functions_data.php (2026-07-07)
 * as part of the code-review Phase 3 file split.
 *
 * Fetch descriptions and images for taxonomic ranks and organisms from Wikipedia.
 * Loaded via a require_once at the top of functions_data.php, so every existing
 * include of functions_data.php continues to expose these unchanged. They depend
 * on moop_curl_get() (defined in functions_data.php), which resolves at call time.
 */

/**
 * Fetch Wikipedia data for a taxonomic rank/level
 * Gets description and image from Wikipedia using the search API
 *
 * @param string $rank_name Name of taxonomic rank (e.g., 'Primates', 'Mammalia')
 * @return array Array with 'description' (HTML), 'image_url', 'wikipedia_url', 'source'
 */
function getWikipediaTaxonomyData($rank_name) {
    $result = [
        'description' => '',
        'image_url' => '',
        'wikipedia_url' => '',
        'source' => 'Wikipedia'
    ];
    
    if (empty($rank_name)) {
        return $result;
    }
    
    // Use Wikipedia API to search for the taxonomic rank
    $wiki_search_url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'titles' => $rank_name,
        'format' => 'json',
        'prop' => 'extracts|pageimages|info',
        'exlimit' => 1,
        'exintro' => true,
        'explaintext' => true,
        'piprop' => 'thumbnail|original',
        'pithumbsize' => 300,
        'redirects' => true
    ]);
    
    $response = moop_curl_get($wiki_search_url);

    if ($response === false) {
        return $result;
    }

    $data = json_decode($response, true);

    if (empty($data['query']['pages'])) {
        return $result;
    }

    // Get first (and usually only) page result
    $pages = array_values($data['query']['pages']);
    $page = $pages[0];

    if (!isset($page['pageid'])) {
        // Page not found, try search instead
        return getWikipediaTaxonomyDataFromSearch($rank_name);
    }
    
    // Determine the actual title (in case of redirects)
    $actual_title = $page['title'] ?? $rank_name;
    
    // Get the page URL
    $result['wikipedia_url'] = 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $actual_title);
    
    // Extract description from intro
    if (!empty($page['extract'])) {
        $description = $page['extract'];
        // Truncate if too long
        if (strlen($description) > 500) {
            $description = substr($description, 0, 500) . '...';
        }
        $result['description'] = trim($description);
    }
    
    // Extract image (try thumbnail first, then original)
    if (!empty($page['thumbnail']['source'])) {
        $result['image_url'] = $page['thumbnail']['source'];
    } elseif (!empty($page['original']['source'])) {
        $result['image_url'] = $page['original']['source'];
    }
    
    return $result;
}

/**
 * Search Wikipedia for taxonomic rank information
 * Fallback when direct title lookup doesn't find good content
 * 
 * @param string $rank_name Name of taxonomic rank
 * @return array Array with description, image, and Wikipedia URL
 */
function getWikipediaTaxonomyDataFromSearch($rank_name) {
    $result = [
        'description' => '',
        'image_url' => '',
        'wikipedia_url' => '',
        'source' => 'Wikipedia'
    ];
    
    // Search for the term
    $search_url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'list' => 'search',
        'srsearch' => $rank_name,
        'format' => 'json',
        'srlimit' => 3
    ]);
    
    $response = moop_curl_get($search_url);

    if ($response === false) {
        return $result;
    }

    $data = json_decode($response, true);

    if (empty($data['query']['search'])) {
        return $result;
    }

    // Try the first few results to find one with content
    foreach ($data['query']['search'] as $search_result) {
        $found_title = $search_result['title'];

        // Fetch details about this page
        $fetch_url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'titles' => $found_title,
            'format' => 'json',
            'prop' => 'extracts|pageimages',
            'exintro' => true,
            'explaintext' => true,
            'piprop' => 'thumbnail|original',
            'pithumbsize' => 300,
            'redirects' => true
        ]);

        $response = moop_curl_get($fetch_url);
        
        if ($response === false) {
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['query']['pages'])) {
            continue;
        }
        
        $pages = array_values($data['query']['pages']);
        $page = $pages[0];
        
        // Skip if no content
        if (empty($page['extract'])) {
            continue;
        }
        
        if (!empty($page['extract'])) {
            $description = $page['extract'];
            if (strlen($description) > 500) {
                $description = substr($description, 0, 500) . '...';
            }
            $result['description'] = trim($description);
        }
        
        if (!empty($page['thumbnail']['source'])) {
            $result['image_url'] = $page['thumbnail']['source'];
        } elseif (!empty($page['original']['source'])) {
            $result['image_url'] = $page['original']['source'];
        }
        
        $result['wikipedia_url'] = 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $page['title']);
        
        // Found a good result, return it
        return $result;
    }
    
    return $result;
}

/**
 * Fetch Wikipedia data for an organism (species)
 * Gets description and image from Wikipedia using scientific name or common name
 * 
 * @param string $organism_name Common name or scientific name (e.g., 'Human', 'Homo sapiens')
 * @param string $scientific_name Scientific name to try first (optional)
 * @return array Array with 'description', 'image_url', 'wikipedia_url', 'source'
 */
function getWikipediaOrganismData($organism_name, $scientific_name = '') {
    $result = [
        'description' => '',
        'image_url' => '',
        'wikipedia_url' => '',
        'source' => 'Wikipedia'
    ];
    
    if (empty($organism_name) && empty($scientific_name)) {
        return $result;
    }
    
    // Try scientific name first, then common name
    $names_to_try = array_filter([
        $scientific_name,
        $organism_name,
        // Also try common name without underscores
        str_replace('_', ' ', $organism_name)
    ]);
    
    foreach ($names_to_try as $search_name) {
        $wiki_search_url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'titles' => $search_name,
            'format' => 'json',
            'prop' => 'extracts|pageimages|info',
            'exlimit' => 1,
            'exintro' => true,
            'explaintext' => true,
            'piprop' => 'thumbnail|original',
            'pithumbsize' => 400,
            'redirects' => true
        ]);
        
        $response = moop_curl_get($wiki_search_url);

        if ($response === false) {
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['query']['pages'])) {
            continue;
        }
        
        $pages = array_values($data['query']['pages']);
        $page = $pages[0];
        
        if (!isset($page['pageid']) || empty($page['extract'])) {
            continue;
        }
        
        // Found good data, return it
        $actual_title = $page['title'] ?? $search_name;
        $result['wikipedia_url'] = 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $actual_title);
        
        if (!empty($page['extract'])) {
            $description = $page['extract'];
            if (strlen($description) > 500) {
                $description = substr($description, 0, 500) . '...';
            }
            $result['description'] = trim($description);
        }
        
        if (!empty($page['thumbnail']['source'])) {
            $result['image_url'] = $page['thumbnail']['source'];
        } elseif (!empty($page['original']['source'])) {
            $result['image_url'] = $page['original']['source'];
        }
        
        return $result;
    }
    
    // If direct search failed, try Wikipedia search API as fallback
    return getWikipediaOrganismDataFromSearch($organism_name);
}

/**
 * Search Wikipedia for organism information
 * Fallback when direct title lookup doesn't find good content
 * 
 * @param string $organism_name Organism name to search for
 * @return array Array with description, image, and Wikipedia URL
 */
function getWikipediaOrganismDataFromSearch($organism_name) {
    $result = [
        'description' => '',
        'image_url' => '',
        'wikipedia_url' => '',
        'source' => 'Wikipedia'
    ];
    
    // Search for the organism
    $search_url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'list' => 'search',
        'srsearch' => str_replace('_', ' ', $organism_name) . ' species animal',
        'format' => 'json',
        'srlimit' => 3
    ]);
    
    $response = moop_curl_get($search_url);

    if ($response === false) {
        return $result;
    }

    $data = json_decode($response, true);

    if (empty($data['query']['search'])) {
        return $result;
    }

    // Try the first few results
    foreach ($data['query']['search'] as $search_result) {
        $found_title = $search_result['title'];

        // Fetch details about this page
        $fetch_url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'titles' => $found_title,
            'format' => 'json',
            'prop' => 'extracts|pageimages',
            'exintro' => true,
            'explaintext' => true,
            'piprop' => 'thumbnail|original',
            'pithumbsize' => 400,
            'redirects' => true
        ]);

        $response = moop_curl_get($fetch_url);

        if ($response === false) {
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['query']['pages'])) {
            continue;
        }
        
        $pages = array_values($data['query']['pages']);
        $page = $pages[0];

        // Skip if no extract
        if (empty($page['extract'])) {
            continue;
        }

        // Reject results whose title shares no words with the organism name —
        // prevents generic pages like "Largest and heaviest animals" from matching.
        $result_title_lower = strtolower($page['title'] ?? '');
        $name_words = preg_split('/[\s_]+/', strtolower(str_replace('_', ' ', $organism_name)));
        $name_words = array_filter($name_words, fn($w) => strlen($w) > 3); // skip short words
        $title_matches = false;
        foreach ($name_words as $word) {
            if (strpos($result_title_lower, $word) !== false) {
                $title_matches = true;
                break;
            }
        }
        if (!$title_matches) {
            continue;
        }

        $description = $page['extract'];
        if (strlen($description) > 500) {
            $description = substr($description, 0, 500) . '...';
        }
        $result['description'] = trim($description);

        if (!empty($page['thumbnail']['source'])) {
            $result['image_url'] = $page['thumbnail']['source'];
        } elseif (!empty($page['original']['source'])) {
            $result['image_url'] = $page['original']['source'];
        }

        $result['wikipedia_url'] = 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $page['title']);

        return $result;
    }
    
    return $result;
}
