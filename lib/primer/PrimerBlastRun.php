<?php
/**
 * One Primer BLAST check: primer pairs + an access-checked source in, products per pair and a
 * verdict out. Shared by the page (tools/primer_blast.php + pages/primer_blast.php) and the API
 * (api/v1/primer_blast.php), so both judge a pair the same way -- moved here unchanged from the
 * page's controller and template on 2026-11-24.
 */

require_once __DIR__ . '/PrimerInput.php';
require_once __DIR__ . '/PrimerBlast.php';
require_once __DIR__ . '/PrimerPairs.php';
require_once __DIR__ . '/ExonMap.php';
require_once __DIR__ . '/../genomic_regions.php';   // moop_genome_kind()

/**
 * Search every pair against the source's genome (always, where it has one) and, for
 * $search_mode 'transcript', its transcripts; then place cDNA products on the genome and name
 * their genes. $source is an entry of flattenSourcesList() the CALLER has access-checked.
 *
 * Returns results (per pair: pair, by_db => [genome|transcript => PrimerPairs::findProducts()
 * + below_floor, over_mismatch, matches]), searched_dbs (label => file), db_notes, error (null = ran).
 */
function primer_blast_check(array $pairs, array $source, string $search_mode, int $max_product): array
{
    $search_error = null;
    $results      = [];
    $searched_dbs = [];
    $db_notes     = [];
    $sel_assembly = $source['assembly'];
    $sel_gene_set = $source['gene_set'] ?? '';
    $parse_result = ['pairs' => $pairs];

    $source_obj    = $source;
    $gene_set_path = $source_obj['path'];
$assembly_path = dirname($gene_set_path);

    $dbs = [];

    // Genome is always searched, in both modes.
    // 20 of 95 sources on this deployment have NO genome — they are
    // transcriptome assemblies, where that is correct rather than missing
    // data. So "the genome is always searched" cannot be an invariant; it
    // is a default that some sources genuinely cannot satisfy.
    $genome_db     = $assembly_path . '/genome.fa';
    $transcript_db = $gene_set_path . '/transcript.nt.fa';
    // A transcripts "genome" is not genomic DNA: searching it in place of one would
    // report cDNA products as genomic (see $source_availability above).
    $is_transcripts = moop_genome_kind($assembly_path) === 'transcripts';
    $has_genome    = PrimerBlast::databaseExists($genome_db) && !$is_transcripts;
    $has_transcript = PrimerBlast::databaseExists($transcript_db);

    if ($has_genome) {
        $dbs['genome'] = $genome_db;
    }

    if ($search_mode === 'transcript' && $has_transcript) {
        $dbs['transcript'] = $transcript_db;
    }

    if ($search_mode === 'transcript' && !$has_transcript) {
        $db_notes[] = 'This gene set has no transcriptome index, so cDNA product sizes '
            . 'could not be calculated.';
    }

    // Say WHICH of the two situations this is. "No BLAST databases are
    // available" named neither the cause nor the fix, and on a
    // transcriptome-only assembly it fired every time, because Genomic DNA
    // is the default PCR input.
    if (!$has_genome) {
        $no_genome_why = $is_transcripts
            ? 'This assembly is transcripts only (one per gene), not a genome'
            : 'This assembly has no genome sequence — it is a transcriptome assembly';
        if ($search_mode === 'genome' && $has_transcript) {
            $search_error = $no_genome_why . '. Set PCR input to "cDNA" to check these '
                . 'primers against its transcripts.';
        } elseif ($search_mode === 'transcript' && $has_transcript) {
            $db_notes[] = $no_genome_why . ', so genomic DNA in the sample could not be '
                . 'checked — only the cDNA products below are reported.';
        } else {
            $search_error = 'This source has neither a genome nor a transcriptome BLAST index, '
                . 'so there is nothing to search. Build them from Admin → Organism Checklist.';
        }
    }

    // Report the user's INTENDED product first. With cDNA in the tube the
    // transcriptome result is the answer they came for and the genomic one
    // is the genomic-DNA check beneath it; listing the genome first
    // buries the answer under a caveat.
    if ($search_mode === 'transcript' && isset($dbs['transcript'])) {
        $dbs = ['transcript' => $dbs['transcript']]
             + (isset($dbs['genome']) ? ['genome' => $dbs['genome']] : []);
    }

    if (empty($dbs) && $search_error === null) {
        $search_error = 'No BLAST databases are available for the selected source.';
    }

    if (!empty($dbs) && $search_error === null) {
        foreach ($parse_result['pairs'] as $i => $pair) {
            $results[$i] = ['pair' => $pair, 'by_db' => []];
        }

        foreach ($dbs as $label => $db) {
            // No max_mismatch: the search bound is fixed (SEARCH_MAX_MISMATCH).
            $blast = PrimerBlast::run($parse_result['pairs'], $db);

            if (!$blast['success']) {
                $db_notes[] = ucfirst($label) . ' search failed: ' . $blast['error'];
                continue;
            }

            $searched_dbs[$label] = basename($db);

            foreach ($parse_result['pairs'] as $i => $pair) {
                // Always look at least as far as AMPLIFIABLE_MAX, whatever the
                // display limit is. The quality badge is computed from products up
                // to that size, so bounding the SEARCH by the display setting would
                // let "Largest product to report" silently change the verdict --
                // a display control quietly altering a scientific conclusion.
                // A repetitive primer (PrimerBlast::MAX_SITES_PER_PRIMER) has no stored
                // sites: products from the other primer alone would be a wrong, smaller
                // answer, so none are worked out and the verdict says why.
                $repetitive = array_filter($blast['repetitive'][$i] ?? []);
                // Past blastn's heap cap (PrimerBlast::HEAP_LIMIT_MB) not even the count is
                // known, nor which primer: 'either' => null.
                if (!empty($blast['out_of_memory'][$i])) {
                    $repetitive = ['either' => null];
                }
                $found = PrimerPairs::findProducts(
                    $repetitive ? [] : ($blast['hits'][$i] ?? []),
                    ['max_product' => max($max_product, PrimerPairs::AMPLIFIABLE_MAX)]
                );
                $found['repetitive']    = $repetitive;   // side => exact site count; [] = normal
                // Per pair, not the run-wide total: every pair would otherwise
                // report the same number as soon as more than one was searched.
                $found['below_floor']   = $blast['discards'][$i]['below_floor'] ?? 0;
                $found['over_mismatch'] = $blast['discards'][$i]['over_mismatch'] ?? 0;
                // The individual matches, so the page can SHOW them rather than
                // only counting them. A match that forms no product was otherwise
                // invisible, which is the opposite of the over-report rule.
                $found['matches']       = $blast['hits'][$i] ?? ['forward' => [], 'reverse' => []];
                $results[$i]['by_db'][$label] = $found;
            }
        }

        // ---- place cDNA products on the genome, through the exons ----
        // A primer sitting across an exon junction has no continuous
        // genomic match — which is exactly why it was designed that way —
        // so its only hit is on the transcript, and a transcript
        // coordinate cannot be shown in a browser. Mapped through the
        // exon structure it becomes two genomic blocks and can be.
        //
        // Done HERE, once for the whole page: ExonMap::load() reads an
        // 18 MB index, and asking per product in the view would read it
        // again for every row to get the same answer back.
        if (isset($dbs['transcript'])) {
            $subject_ids = [];
            foreach ($results as $r) {
                foreach ($r['by_db']['transcript']['products'] ?? [] as $prod) {
                    $subject_ids[PrimerBlast::cleanSubjectId($prod['subject'])] = true;
                }
            }

            $exon_records = ExonMap::load($gene_set_path, array_keys($subject_ids));

            // No index yet is the NORMAL state for most gene sets — it is
            // written at JBrowse registration, and only re-registered sets
            // have one. Say so rather than leaving the missing links
            // looking like a bug, but only when there was something to map.
            if (empty($exon_records) && !empty($subject_ids)) {
                $db_notes[] = 'cDNA products cannot be shown in the browser for this gene set: '
                    . 'its exon index has not been built yet. Re-register the gene set from '
                    . 'Admin → JBrowse Management to enable it.';
            }

            // ---- which GENE each cDNA product belongs to ----
            // Specificity is a question about genes. Two isoforms of one gene give
            // the same product, and counting transcripts graded such a pair
            // "non-specific — 2 products" (XM_037135499.2 / XM_037135500.2, both
            // LOC119041968, 2026-10-29). A transcript not found keeps no gene, and
            // the page then falls back to counting products.
            $genes_by_tx = moop_primer_transcript_genes(dirname($assembly_path) . '/organism.sqlite',
                $sel_assembly, $sel_gene_set, array_keys($subject_ids));

            foreach ($results as $i => $r) {
                foreach ($r['by_db']['transcript']['products'] ?? [] as $j => $prod) {
                    $gene = $genes_by_tx[PrimerBlast::cleanSubjectId($prod['subject'])] ?? null;
                    if ($gene !== null) {
                        $results[$i]['by_db']['transcript']['products'][$j]['gene'] = $gene;
                    }
                    $record = $exon_records[PrimerBlast::cleanSubjectId($prod['subject'])] ?? null;
                    if ($record === null) {
                        continue;
                    }
                    $mapped = ExonMap::mapProduct($record, $prod);
                    if ($mapped !== null) {
                        $results[$i]['by_db']['transcript']['products'][$j]['genomic'] = $mapped;
                    }
                }
            }
        }
    }

    return ['results' => $results, 'searched_dbs' => $searched_dbs, 'db_notes' => $db_notes, 'error' => $search_error];
}

/**
 * The gene each transcript belongs to, in one gene set: [transcript id => ['id' => …, 'name' => …]].
 * A database that cannot be read gives [] — the page then counts products, as it always did.
 */
function moop_primer_transcript_genes(string $db_file, string $assembly, string $gene_set, array $transcript_ids): array {
    if (!$transcript_ids || !is_file($db_file)) return [];
    $out = [];
    try {
        foreach (array_chunk($transcript_ids, 500) as $chunk) {
            $rows = fetchData(
                'SELECT m.feature_uniquename AS tx, g.feature_uniquename AS gene, g.feature_name AS name
                   FROM feature m
                   JOIN feature g ON g.feature_id = m.parent_feature_id
                   JOIN gene_set gs ON gs.gene_set_id = m.gene_set_id
                   JOIN genome ge ON ge.genome_id = gs.genome_id
                  WHERE gs.gene_set_name = ? AND (ge.genome_accession = ? OR ge.genome_name = ?)
                    AND m.feature_uniquename IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')',
                $db_file, array_merge([$gene_set, $assembly, $assembly], $chunk));
            foreach ($rows as $row) {
                $out[$row['tx']] = ['id' => $row['gene'], 'name' => (string)($row['name'] ?? '')];
            }
        }
    } catch (Throwable $e) {
        error_log('primer_blast: transcript genes: ' . $e->getMessage());
        return [];
    }
    return $out;
}

/**
 * The one quality call for a pair, and the reasons it is not clean. Returns key (specific |
 * nonspecific | none), badge (Bootstrap classes), label, reasons, isoforms_note, intended_db,
 * amplifiable (products on the intended template that would amplify), amp_contam, too_long, kb.
 */
function primer_blast_verdict(array $r, string $search_mode, int $max_product): array
{
    // Specificity is judged against the INTENDED template only. Summing
    // across databases counts one amplicon twice -- the cDNA product and
    // the genomic product are the same fragment seen against two
    // templates -- and a perfectly specific pair then reads "2 products".
    $intended_db = ($search_mode === 'transcript' && isset($r['by_db']['transcript']))
        ? 'transcript' : 'genome';

    // ONE quality call, with the reasons it is not clean. Counts alone made
    // the reader do this arithmetic themselves, and "specific — 1 product"
    // beside "gDNA: 2 products" read as a contradiction.
    // THE RULE: a product counts when PrimerPairs::isAmplifiable() says it
    // would amplify -- under AMPLIFIABLE_MAX (3 kb since 2026-10-13), fewer than
    // 6 mismatches per primer, and fewer than 2 in the last 5 bases at the 3'
    // end (NCBI Primer-BLAST's thresholds). The search keeps every site up to
    // PrimerBlast::SEARCH_MAX_MISMATCH, so this sees all of them whatever the
    // "Mismatches listed" box says. One is clean, more than one is ambiguous.
    // Everything else is still LISTED -- it is real -- it just does not count.
    $amp = function (array $found) {
        return array_values(array_filter($found['products'] ?? [],
            ['PrimerPairs', 'isAmplifiable']));
    };
    $amp_intended = count($amp($r['by_db'][$intended_db] ?? []));
    $amp_contam   = ($intended_db === 'transcript' && isset($r['by_db']['genome']))
        ? count($amp($r['by_db']['genome'])) : 0;

    // Specificity is about GENES. On cDNA each product carries its gene
    // (tools/primer_blast.php); isoforms of one gene are one target, not
    // several. When any product's gene is unknown, count products as before.
    $amp_products = $amp($r['by_db'][$intended_db] ?? []);
    $amp_genes = [];
    foreach ($amp_products as $pr) {
        if (!isset($pr['gene'])) { $amp_genes = null; break; }
        $amp_genes[$pr['gene']['id']] = $pr['gene'];
    }
    $gene_word = 'primer_blast_gene_word';
    $isoforms_note = null;   // one gene, several transcripts: say so, it is not a warning
    $reasons = [];
    if ($amp_genes !== null && $amp_intended > 1) {
        if (count($amp_genes) > 1) {
            $reasons[] = t_plain('%1$d different genes could amplify: %2$s', count($amp_genes),
                         implode(', ', array_map($gene_word, array_slice(array_values($amp_genes), 0, 4)))
                       . (count($amp_genes) > 4 ? ', …' : ''));
        } else {
            $sizes = array_values(array_unique(array_map(fn($pr) => (int)$pr['size'], $amp_products)));
            sort($sizes);
            $isoforms_note = [
                'gene'  => $gene_word(reset($amp_genes)),
                'n'     => $amp_intended,
                'tx'    => array_map(fn($pr) => PrimerBlast::cleanSubjectId($pr['subject']), $amp_products),
                'sizes' => $sizes,
            ];
        }
    } elseif ($amp_intended > 1) {
        $reasons[] = t_plain('%d products on your template could amplify', $amp_intended);
    }
    if ($amp_contam > 1) {
        $reasons[] = t_plain('genomic DNA could give %d products, not just the one matching locus', $amp_contam);
    }
    foreach ($r['by_db'] as $f) {
        foreach ($amp($f) as $pr) {
            if (!empty($pr['self_pairing'])) {
                $reasons[] = t_plain('one primer amplifies on its own (self-pairing)');
                break 2;
            }
        }
    }

    // A primer matching thousands of places (PrimerBlast::MAX_SITES_PER_PRIMER) had no
    // products worked out, so the counts above are 0 -- which must NOT read as "no product".
    // It is non-specific: such a primer primes all over the template (or the genomic DNA).
    $repetitive = [];
    foreach ($r['by_db'] as $db_key => $f) {
        foreach ($f['repetitive'] ?? [] as $side => $n) {
            $where = $db_key === 'transcript' ? t_plain('the transcripts') : t_plain('the genome');
            $reasons[] = $side === 'either'
                ? t_plain('one of the primers matches so many places in %s that the search stopped before it could count them: it lies in a repeated sequence, so products were not worked out',
                          $where)
                : ($side === 'forward'
                ? t_plain('the forward primer matches %1$s places in %2$s (up to %3$d mismatches): it lies in a repeated sequence, so products were not worked out',
                          number_format($n), $where, PrimerBlast::SEARCH_MAX_MISMATCH)
                : t_plain('the reverse primer matches %1$s places in %2$s (up to %3$d mismatches): it lies in a repeated sequence, so products were not worked out',
                          number_format($n), $where, PrimerBlast::SEARCH_MAX_MISMATCH));
            $repetitive[$db_key][$side] = $n;
        }
    }

    $total = $amp_intended;

    // Products on the template that would amplify but for their LENGTH: they
    // are listed in the table (anything up to "Largest product to report"),
    // yet the verdict counts only those under AMPLIFIABLE_MAX. Without naming that, the
    // badge said "no product" above a perfect 3,733 bp genomic product
    // (Congeria COKUS1KC_0031865-RA, pair 5 across two introns).
    $too_long = array_values(array_filter($r['by_db'][$intended_db]['products'] ?? [],
        fn($pr) => $pr['size'] > PrimerPairs::AMPLIFIABLE_MAX
                && $pr['size'] <= $max_product
                && PrimerPairs::isAmplifiable(array_merge($pr, ['size' => 0]))));
    $kb = PrimerPairs::amplifiableKb();

    if ($repetitive) {
        $verdict = ['nonspecific', 'bg-warning text-dark', t_plain('non-specific')];
    } elseif ($total === 0 && $too_long) {
        $verdict = ['none', 'bg-secondary', t_plain('no product under %s kb', $kb)];
    } elseif ($total === 0) {
        $verdict = ['none', 'bg-secondary', t_plain('no product')];
    } elseif (!$reasons) {
        $verdict = ['specific', 'bg-success', t_plain('specific')];
    } else {
        $verdict = ['nonspecific', 'bg-warning text-dark', t_plain('non-specific')];
    }

    return ['key' => $verdict[0], 'badge' => $verdict[1], 'label' => $verdict[2], 'reasons' => $reasons,
            'isoforms_note' => $isoforms_note, 'intended_db' => $intended_db, 'amplifiable' => $total,
            'amp_contam' => $amp_contam, 'too_long' => $too_long, 'kb' => $kb,
            'repetitive' => $repetitive];
}

/** A gene as a reader names it: "name (id)", or the id when it has no other name. */
function primer_blast_gene_word(array $g): string
{
    return $g['name'] !== '' && $g['name'] !== $g['id'] ? $g['name'] . ' (' . $g['id'] . ')' : $g['id'];
}

/**
 * The products a listing shows: within the "largest product" and "mismatches listed" limits.
 * Both are DISPLAY limits -- a product hidden by either still counted toward the verdict.
 */
function primer_blast_shown_products(array $found, int $max_product, int $max_mismatch): array
{
    return array_values(array_filter($found['products'] ?? [], function ($pr) use ($max_product, $max_mismatch) {
        return $pr['size'] <= $max_product && $pr['max_mismatch'] <= $max_mismatch;
    }));
}

/**
 * One row of the download for one listed product. Keyed by NAME: the file's header is derived
 * from these keys (js moopRowsToTsv / PHP moop_rows_to_tsv), never kept as a separate list.
 */
function primer_blast_export_row(array $pair, string $db_key, array $prod, string $verdict_key): array
{
    // The intron facts the page shows as text and a badge. A download has neither, so without
    // these columns the single most important property of an RT-PCR pair -- that it cannot
    // amplify gDNA -- survives only as the absence of a genomic row, which reads as missing data.
    //
    // '' means NOT DETERMINED (a genomic row, or a gene set with no exon index); 0 and 'none'
    // are real answers. Collapsing the two would make "no intron here" and "we could not tell"
    // the same cell.
    $mapped   = $prod['genomic'] ?? null;
    $introns  = $mapped ? (int) $mapped['introns'] : '';
    $junction = '';
    if ($mapped) {
        $which = [];
        foreach ($mapped['hits'] as $h) {
            if (!empty($h['spliced'])) $which[] = $h['primer'];
        }
        $junction = $which ? implode('+', array_unique($which)) : 'none';
    }
    return [
        'pair'            => $pair['name'],
        'forward'         => $pair['forward'],
        'reverse'         => $pair['reverse'],
        // The pair's verdict on every one of its rows, so a filtered spreadsheet keeps it.
        'pair_verdict'    => $verdict_key,
        'template'        => $db_key === 'genome' ? 'genomic' : 'cDNA',
        'subject'         => PrimerBlast::cleanSubjectId($prod['subject']),
        'start'           => $prod['start'],
        'end'             => $prod['end'],
        'product_size'    => $prod['size'],
        'max_mismatch'    => $prod['max_mismatch'],
        // Name WHICH primer: a downloaded row has no badge to hover, so "self-pairing" alone loses the fact.
        'formed_by'       => $prod['self_pairing'] ? $prod['primers'][0] . ' self-pairing' : 'forward+reverse',
        // The gene a cDNA product belongs to (isoforms of one gene are one target); '' on genomic rows.
        'gene'            => isset($prod['gene']) ? $prod['gene']['id'] : '',
        'gene_name'       => isset($prod['gene']) && $prod['gene']['name'] !== $prod['gene']['id'] ? $prod['gene']['name'] : '',
        'introns_spanned' => $introns,
        'junction_primer' => $junction,
    ];
}
