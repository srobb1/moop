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
        // NOT truncated. The request above already passes exintro=true, which asks
        // Wikipedia for the lead section ONLY -- so what arrives is exactly the article's
        // first section, and cutting it again served no purpose. The old 500-character
        // limit chopped mid-word ("...where its slender colum...") with no way to read on.
        //
        // It was also byte-based: strlen/substr on UTF-8 can sever a multi-byte character
        // and emit a broken byte. Dropping the cut removes that hazard rather than papering
        // over it with mb_ equivalents.
        $result['description'] = trim($page['extract']);
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
            // exintro=true already limits this to the lead section -- see the note on the
            // first of these four call sites. No second cut.
            $result['description'] = trim($page['extract']);
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
            // exintro=true already limits this to the lead section -- see the note on the
            // first of these four call sites. No second cut.
            $result['description'] = trim($page['extract']);
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

        // exintro=true already limits this to the lead section. No second cut.
        $result['description'] = trim($page['extract']);

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

/**
 * Italicise an organism's scientific name inside Wikipedia prose.
 *
 * The extract is fetched with explaintext=true, which returns PLAIN TEXT -- so Wikipedia's
 * own italics are stripped, and a binomial that must be italicised by convention arrives as
 * ordinary words. Asking for HTML instead would bring the whole article's markup along with
 * it, and with it the job of sanitising someone else's HTML before echoing it.
 *
 * So restore just the one thing we can name with certainty: this organism's own binomial,
 * which the caller already knows. Nothing else is touched.
 *
 * Operates only on text OUTSIDE tags -- stored descriptions carry a little markup, and a
 * naive str_replace would happily rewrite an attribute value.
 *
 * @param string $html   description text, may contain simple markup
 * @param string $genus
 * @param string $species
 * @return string
 */
function moop_italicise_binomial(string $html, string $genus, string $species): string {
    $genus   = trim($genus);
    $species = trim($species);
    if ($genus === '' || $species === '') {
        return $html;
    }

    $binomial = preg_quote($genus . ' ' . $species, '/');
    // Also the abbreviated form Wikipedia uses after first mention: "N. vectensis".
    $abbrev   = preg_quote(mb_substr($genus, 0, 1) . '. ' . $species, '/');
    $pattern  = '/\b(' . $binomial . '|' . $abbrev . ')\b/u';

    // Split on tags, keeping them, and rewrite only the text between.
    $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($part === '' || $part[0] === '<') {
            continue;
        }
        $parts[$i] = preg_replace($pattern, '<em>$1</em>', $part);
    }
    return implode('', $parts);
}
