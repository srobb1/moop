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
 * A Wikipedia plain-text extract (explaintext) made fit to show. The API removes pronunciation
 * markup -- the IPA and the "listen" link -- but leaves the brackets around it, so the lead of
 * Echinoderm reads "An echinoderm () is any animal of the phylum Echinodermata (), ..." and
 * Mollusca "molluscs or mollusks ()". Brackets holding nothing but spaces and separators go,
 * with the space before them; a separator left at the start of a bracket is dropped
 * ("(; from Greek ...)" -> "(from Greek ...)").
 */
function moop_wikipedia_clean_extract(string $text): string {
    $text = preg_replace('/\s*\(\s*[;,:]?\s*\)/u', '', $text);
    $text = preg_replace('/\(\s*[;,:]\s*/u', '(', $text);
    return trim($text);
}

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
        $result['description'] = moop_wikipedia_clean_extract($page['extract']);
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
            $result['description'] = moop_wikipedia_clean_extract($page['extract']);
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
 * Cached organism lookup — THE entry point. Callers must not reach past this.
 *
 * Keyed by SCIENTIFIC NAME, because the answer is a function of the name and nothing else.
 * It used to be keyed by whatever the caller passed first: the organism page passed the
 * directory name, the image lookup passed the COMMON name. Nine planarians share the common
 * name "Freshwater planarian", so they shared one cache entry — whichever was looked up first
 * decided the answer for all nine, and an image found that way is saved permanently under
 * each organism's own name. Found 2026-10-28 (13 stray cache directories named after common
 * names, e.g. "Cura Pinguis Unknown Species").
 *
 * An uncached lookup is several sequential calls to Wikipedia, so it must not run unbounded
 * inside a page request (measured 2026-08-04: 348 ms of a 428 ms organism page). Three
 * guards:
 *   - results are cached: 30 days for a hit, 7 for "no article", each stretched by a per-name
 *     ±20% so organisms looked up together do not all expire together;
 *   - at most WIKI_LOOKUPS_PER_REQUEST live lookups per request. A list page (a group of 49
 *     bats) used to look up every uncached organism in one request — on 2026-10-21 ~50 in a
 *     single minute — which is how Wikipedia came to refuse them;
 *   - a refusal or network failure is NEVER cached. Only a lookup in which every call got a
 *     real answer is written. Before 2026-10-28 the HTTP status was not checked, so a refusal
 *     read as "no article" and was kept for a week (50 organisms, Myotis lucifugus among them).
 * When the budget is spent or Wikipedia cannot be asked, a stale entry beats nothing.
 *
 * @param string $scientific_name "Genus species"; "Genus sp" gets the genus article
 * @param string $common_name     optional; tried as a title, accepted only if the article
 *                                names the species (see moop_wikipedia_fetch_organism_uncached)
 * @return array description, image_url, wikipedia_url, source, title, and scope
 *               ('species' | 'genus' | '' when nothing was found)
 */
function getWikipediaOrganismData($scientific_name, $common_name = '') {
    $scientific_name = moop_wikipedia_normalise_name((string) $scientific_name);
    if ($scientific_name === '') {
        return moop_wikipedia_empty_result();
    }

    $fresh  = false;
    $cached = moop_wikipedia_cached($scientific_name, $fresh);
    if ($cached !== null && $fresh) {
        return $cached;
    }

    static $budget = 3;   // WIKI_LOOKUPS_PER_REQUEST
    if ($budget <= 0) {
        return $cached ?? moop_wikipedia_empty_result();
    }
    $budget--;

    $reached = false;
    $data = moop_wikipedia_fetch_organism_uncached($scientific_name, (string) $common_name, $reached);
    if (!$reached) {
        return $cached ?? moop_wikipedia_empty_result();
    }

    $cache_file = function_exists('moop_wikipedia_cache_file')
        ? moop_wikipedia_cache_file($scientific_name)
        : '';
    if ($cache_file) {
        @file_put_contents(
            $cache_file,
            json_encode(['v' => 2, 'fetched_at' => time(), 'name' => $scientific_name, 'data' => $data]),
            LOCK_EX
        );
    }
    return $data;
}

/**
 * The cached answer for a scientific name, or null. Never touches the network, so display
 * code (an image caption) can ask what the lookup found without risking a fetch.
 *
 * @param bool $fresh OUT. False when the entry is past its TTL (still returned: stale beats
 *                    nothing when Wikipedia cannot be asked).
 */
function moop_wikipedia_cached(string $scientific_name, &$fresh = false): ?array {
    $fresh = false;
    $scientific_name = moop_wikipedia_normalise_name($scientific_name);
    $file = ($scientific_name !== '' && function_exists('moop_wikipedia_cache_file'))
        ? moop_wikipedia_cache_file($scientific_name)
        : '';
    if (!$file || !is_readable($file)) {
        return null;
    }
    $cached = json_decode((string) @file_get_contents($file), true);
    // v2 = the scientific-name lookup. Anything older came from the lookup that could
    // hand one organism another's answer, so it is not trusted.
    if (!is_array($cached) || ($cached['v'] ?? 0) !== 2 || !isset($cached['fetched_at'], $cached['data'])) {
        return null;
    }
    $base   = empty($cached['data']['scope']) ? 7 * 86400 : 30 * 86400;
    $spread = 0.8 + 0.4 * ((crc32($scientific_name) % 1000) / 1000);
    $fresh  = (time() - (int) $cached['fetched_at']) < $base * $spread;
    return $cached['data'] + moop_wikipedia_empty_result();
}

function moop_wikipedia_empty_result(): array {
    return ['description' => '', 'image_url' => '', 'wikipedia_url' => '', 'source' => 'Wikipedia',
            'title' => '', 'scope' => ''];
}

/** "Girardia_sp" / "Girardia  sp" -> "Girardia sp". */
function moop_wikipedia_normalise_name(string $name): string {
    return trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $name)));
}

/**
 * One call to a MediaWiki API (Wikipedia, Wikidata, Commons). Returns the decoded answer,
 * or null with $ok set false when there was no real answer — a network failure, ANY
 * non-200 status (429 is how Wikipedia says "too many requests"), unparseable JSON, or an
 * API-level error. Callers stop at the first false $ok so nothing is cached from it.
 */
function moop_wikipedia_api(string $base_url, array $params, bool &$ok): ?array {
    $code = 0;
    $body = moop_curl_get($base_url . '?' . http_build_query($params + ['format' => 'json', 'formatversion' => 2]), 3, 6, $code);
    $data = ($body !== false && $code === 200) ? json_decode($body, true) : null;
    if (!is_array($data) || isset($data['error'])) {
        $ok = false;
        return null;
    }
    return $data;
}

/**
 * Wikipedia pages, by exact title or by search, each reduced to what the lookup needs:
 * title, plain-text lead, page image, Wikidata id. Missing pages, disambiguation pages and
 * pages with no lead text are dropped. Search results keep the search's ranking.
 */
function moop_wikipedia_pages(array $query, bool &$ok): array {
    $data = moop_wikipedia_api('https://en.wikipedia.org/w/api.php', $query + [
        'action'      => 'query',
        'prop'        => 'extracts|pageimages|pageprops',
        'exintro'     => 1,
        'explaintext' => 1,
        'exlimit'     => 'max',
        'piprop'      => 'thumbnail|original',
        'pithumbsize' => 400,
        'ppprop'      => 'disambiguation|wikibase_item',
        'redirects'   => 1,
    ], $ok);
    $pages = $data['query']['pages'] ?? [];
    usort($pages, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

    $out = [];
    foreach ($pages as $page) {
        if (!empty($page['missing']) || isset($page['pageprops']['disambiguation']) || empty($page['extract'])) {
            continue;
        }
        $out[] = [
            'title'   => (string) $page['title'],
            'extract' => moop_wikipedia_clean_extract((string) $page['extract']),
            'image'   => $page['thumbnail']['source'] ?? ($page['original']['source'] ?? ''),
            'qid'     => $page['pageprops']['wikibase_item'] ?? '',
        ];
    }
    return $out;
}

/**
 * Is this page the article about the genus itself? Its title is the genus (or the genus
 * with a qualifier, "Cura (flatworm)") and its lead calls it a genus. "Cura" alone is a
 * disambiguation page, already dropped by moop_wikipedia_pages().
 */
function moop_wikipedia_is_genus_page(array $page, string $genus): bool {
    $title = strtolower($page['title']);
    $g     = strtolower($genus);
    return ($title === $g || strpos($title, "$g (") === 0)
        && preg_match('/\bgenus\b/i', $page['extract']);
}

/** Does the page name this species — in its title or its lead text? */
function moop_wikipedia_names_species(array $page, string $scientific_name): bool {
    return stripos($page['title'], $scientific_name) !== false
        || stripos(preg_replace('/\s+/', ' ', $page['extract']), $scientific_name) !== false;
}

/**
 * The live lookup. Call getWikipediaOrganismData() instead — see the note there about why
 * this must not sit in a page request unguarded.
 *
 * Order, and what each step must prove before it is believed:
 *   1. the scientific name as a title. Wikipedia redirects binomials to the common-name
 *      article ("Myotis lucifugus" -> "Little brown bat"), so this finds most species. A
 *      binomial that redirects to its GENUS article is not taken as the species.
 *   2. the common name as a title — only if the article names the species. Common names
 *      can be generic: "Marine planarian" gave Procerodes the general Planarian article.
 *   3. a search for the binomial — same test.
 *   4. the genus article (title, then a search for "<genus> genus" for genera whose bare
 *      name is a disambiguation page, like Cura) — scope 'genus', and the page says so.
 *      Also used for "Genus sp" organisms, which have no species to find.
 * The image comes from the article; if it has none, from Wikidata's image (P18) for the
 * same item — Cura foremanii's infobox image is too wide for Wikipedia's page-image pick.
 *
 * @param bool $reached OUT. True only if every call got a real answer, so the result —
 *                      including "nothing found" — may be cached.
 */
function moop_wikipedia_fetch_organism_uncached($scientific_name, $common_name = '', &$reached = false) {
    $reached = false;
    $ok      = true;
    $result  = moop_wikipedia_empty_result();

    $scientific_name = moop_wikipedia_normalise_name((string) $scientific_name);
    [$genus, $species] = array_pad(explode(' ', $scientific_name, 2), 2, '');
    if ($genus === '') {
        return $result;
    }
    $species_known = $species !== '' && !preg_match('/^(sp|spp)\.?$/i', $species);

    $found = null;
    $scope = '';
    $genus_page = null;

    if ($species_known) {
        $pages = moop_wikipedia_pages(['titles' => $scientific_name], $ok);
        if (!$ok) return $result;
        foreach ($pages as $page) {
            if (moop_wikipedia_is_genus_page($page, $genus)) {
                $genus_page = $page;            // binomial redirects to its genus
            } else {
                $found = $page;
            }
        }

        $common_name = moop_wikipedia_normalise_name((string) $common_name);
        if (!$found && $common_name !== '' && strcasecmp($common_name, $scientific_name) !== 0) {
            $pages = moop_wikipedia_pages(['titles' => $common_name], $ok);
            if (!$ok) return $result;
            foreach ($pages as $page) {
                if (moop_wikipedia_names_species($page, $scientific_name)) { $found = $page; break; }
            }
        }

        if (!$found) {
            $pages = moop_wikipedia_pages(['generator' => 'search', 'gsrsearch' => "\"$scientific_name\"", 'gsrlimit' => 3], $ok);
            if (!$ok) return $result;
            foreach ($pages as $page) {
                if (!moop_wikipedia_is_genus_page($page, $genus) && moop_wikipedia_names_species($page, $scientific_name)) {
                    $found = $page;
                    break;
                }
            }
        }
        if ($found) $scope = 'species';
    }

    if (!$found) {
        if (!$genus_page) {
            foreach (moop_wikipedia_pages(['titles' => $genus], $ok) as $page) {
                if (moop_wikipedia_is_genus_page($page, $genus)) $genus_page = $page;
            }
            if (!$ok) return $result;
        }
        if (!$genus_page) {
            $pages = moop_wikipedia_pages(['generator' => 'search', 'gsrsearch' => "$genus genus", 'gsrlimit' => 3], $ok);
            if (!$ok) return $result;
            foreach ($pages as $page) {
                if (moop_wikipedia_is_genus_page($page, $genus)) { $genus_page = $page; break; }
            }
        }
        if ($genus_page) {
            $found = $genus_page;
            $scope = 'genus';
        }
    }

    if ($found) {
        $image = $found['image'];
        if ($image === '' && $found['qid'] !== '') {
            $image = moop_wikidata_image($found['qid'], $ok);
            if (!$ok) return $result;
        }
        $result = [
            'description'   => $found['extract'],
            'image_url'     => $image,
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $found['title'])),
            'source'        => 'Wikipedia',
            'title'         => $found['title'],
            'scope'         => $scope,
        ];
    }

    $reached = true;
    return $result;
}

/**
 * The image Wikidata records for an item (property P18), as a 400 px Commons thumbnail URL,
 * or '' if it has none.
 */
function moop_wikidata_image(string $qid, bool &$ok): string {
    $claims = moop_wikipedia_api('https://www.wikidata.org/w/api.php',
        ['action' => 'wbgetclaims', 'entity' => $qid, 'property' => 'P18'], $ok);
    $file = $claims['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? '';
    if (!$ok || !is_string($file) || $file === '') {
        return '';
    }
    $info = moop_wikipedia_api('https://commons.wikimedia.org/w/api.php', [
        'action' => 'query', 'titles' => "File:$file", 'prop' => 'imageinfo',
        'iiprop' => 'url', 'iiurlwidth' => 400,
    ], $ok);
    $ii = $info['query']['pages'][0]['imageinfo'][0] ?? [];
    return (string) ($ii['thumburl'] ?? ($ii['url'] ?? ''));
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
