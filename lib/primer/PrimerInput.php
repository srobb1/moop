<?php
/**
 * PrimerInput - parse user-supplied primers into pairs.
 *
 * Accepts four shapes and detects which was given rather than making the user
 * declare it (see notes/PRIMER_BLAST_TOOL_PLAN.md §INPUT RULES):
 *
 *   1. FASTA, one primer per record      -> paired by name suffix, else adjacency
 *   2. FASTA, both primers in one record -> split on a run of N (>= N_SEPARATOR_MIN)
 *   3. TSV / CSV                         -> header-driven columns
 *   4. Bare sequences, one per line      -> paired by adjacency, names synthesised
 *
 * Nothing is ever dropped silently. Every rejected record is named in `errors`,
 * every assumption is stated in `warnings` -- a silent drop here would be a
 * specificity answer computed from primers the user did not know were missing.
 *
 * @package MOOP\Primer
 */

class PrimerInput
{
    /**
     * A run of this many or more N's inside a FASTA record separates the two
     * primers of a pair. Single/double N's are left alone because they are a
     * legitimate degenerate base within a primer.
     */
    const N_SEPARATOR_MIN = 3;

    /** Plain bases, plus the IUPAC ambiguity codes used in degenerate primers. */
    const VALID_BASES = 'ACGTURYSWKMBDHVN';

    /** Advisory length bounds. Outside these we warn but still search. */
    const LEN_WARN_MIN = 15;
    const LEN_WARN_MAX = 40;

    /**
     * Suffixes that mark a record as the forward or reverse primer of a pair.
     * Matched case-insensitively against the end of the name, after a
     * separator character.
     */
    private static $forward_suffixes = ['f', 'fwd', 'for', 'forward', 'left', 'l', '1', 'p1'];
    private static $reverse_suffixes = ['r', 'rev', 'reverse', 'right', '2', 'p2'];

    /**
     * Parse primer input of any supported shape.
     *
     * @param string $text Raw pasted text or uploaded file contents.
     * @return array {
     *     @type array  $pairs    List of ['name'=>, 'forward'=>, 'reverse'=>]
     *     @type array  $warnings Human-readable advisories; the search still runs.
     *     @type array  $errors   Records that could not be used, each one named.
     *     @type string $shape    Which input shape was detected.
     * }
     */
    public static function parse($text)
    {
        $result = [
            'pairs'    => [],
            'warnings' => [],
            'errors'   => [],
            'shape'    => 'unknown',
        ];

        $text = str_replace("\r\n", "\n", (string)$text);
        $text = str_replace("\r", "\n", $text);

        if (trim($text) === '') {
            $result['errors'][] = 'No primer sequences were provided.';
            return $result;
        }

        $shape = self::detectShape($text);
        $result['shape'] = $shape;

        switch ($shape) {
            case 'fasta_pair_in_record':
                $records = self::parseFastaRecords($text, $result);
                self::pairsFromSplitRecords($records, $result);
                break;

            case 'fasta_one_per_record':
                $records = self::parseFastaRecords($text, $result);
                self::pairsFromSeparateRecords($records, $result);
                break;

            case 'delimited':
                self::pairsFromDelimited($text, $result);
                break;

            case 'bare':
            default:
                // Names here are synthesised by us, so grouping by their suffixes
                // would be circular -- adjacency is the only real signal.
                $records = self::parseBareLines($text);
                self::pairsFromSeparateRecords($records, $result, false);
                break;
        }

        return $result;
    }

    /**
     * Decide which of the four input shapes this text is.
     *
     * Order matters: a FASTA record containing an N-run is shape 2, and must be
     * tested before shape 1. Delimited input is only considered when there is no
     * '>' at all, so a FASTA description line containing a comma cannot be
     * mistaken for CSV.
     */
    private static function detectShape($text)
    {
        if (strpos($text, '>') !== false) {
            $n_run = '/[ACGT]' . str_repeat('N', self::N_SEPARATOR_MIN) . '+[ACGT]/i';
            foreach (self::splitFastaBlocks($text) as $block) {
                if (preg_match($n_run, $block['seq'])) {
                    return 'fasta_pair_in_record';
                }
            }
            return 'fasta_one_per_record';
        }

        // No '>' anywhere: delimited only if a data line carries a delimiter.
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, "\t") !== false || strpos($line, ',') !== false) {
                return 'delimited';
            }
        }

        return 'bare';
    }

    /**
     * Split FASTA text into name/description/sequence blocks.
     *
     * Sequence whitespace and digits are stripped: people paste sequences with
     * position numbers down the side, and a digit would otherwise be reported
     * as an invalid base.
     */
    private static function splitFastaBlocks($text)
    {
        $blocks = [];
        $current = null;

        foreach (explode("\n", $text) as $line) {
            if (isset($line[0]) && $line[0] === '>') {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $header = trim(substr($line, 1));
                $parts  = preg_split('/\s+/', $header, 2);
                $current = [
                    'name' => $parts[0] !== '' ? $parts[0] : 'unnamed',
                    'seq'  => '',
                ];
            } elseif ($current !== null) {
                $current['seq'] .= preg_replace('/[\s\d]/', '', $line);
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * FASTA blocks -> validated records, naming anything rejected.
     */
    private static function parseFastaRecords($text, array &$result)
    {
        $records = [];
        $seen = [];

        foreach (self::splitFastaBlocks($text) as $block) {
            $name = $block['name'];

            if (isset($seen[strtolower($name)])) {
                $result['warnings'][] = "Duplicate primer name \"$name\" — vendor order forms key on name.";
            }
            $seen[strtolower($name)] = true;

            if ($block['seq'] === '') {
                $result['errors'][] = "\"$name\" has no sequence.";
                continue;
            }

            $records[] = $block;
        }

        return $records;
    }

    /**
     * Bare sequence lines -> records with synthesised names.
     */
    private static function parseBareLines($text)
    {
        $records = [];
        $i = 0;

        foreach (explode("\n", $text) as $line) {
            $seq = preg_replace('/[\s\d]/', '', $line);
            if ($seq === '') {
                continue;
            }
            $i++;
            $records[] = ['name' => 'primer_' . $i, 'seq' => $seq];
        }

        return $records;
    }

    /**
     * Shape 3: TSV or CSV, header-driven.
     *
     * The header names the columns, so column ORDER is never assumed -- the
     * positional-identity trap that cost this codebase four defects in the
     * results tables (CLAUDE.md §9b).
     */
    private static function pairsFromDelimited($text, array &$result)
    {
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line);
            if (trim($line) === '' || $line[0] === '#') {
                continue;
            }
            $lines[] = $line;
        }

        if (empty($lines)) {
            $result['errors'][] = 'No data rows were found.';
            return;
        }

        $delim = (substr_count($lines[0], "\t") >= substr_count($lines[0], ',')) ? "\t" : ',';

        $header = array_map(
            function ($h) { return strtolower(trim($h, " \t\"'")); },
            explode($delim, array_shift($lines))
        );

        $col = function (array $names) use ($header) {
            foreach ($names as $n) {
                $i = array_search($n, $header, true);
                if ($i !== false) {
                    return $i;
                }
            }
            return null;
        };

        $i_name = $col(['name', 'id', 'pair', 'pair_name', 'sequence name', 'gene']);
        $i_fwd  = $col(['forward', 'fwd', 'left', 'f', 'forward primer', 'primer_f', 'p1']);
        $i_rev  = $col(['reverse', 'rev', 'right', 'r', 'reverse primer', 'primer_r', 'p2']);

        if ($i_fwd === null || $i_rev === null) {
            $result['errors'][] = 'Could not find forward and reverse columns. Expected a header row '
                . 'naming them (e.g. name, forward, reverse).';
            return;
        }

        $row_no = 1;
        foreach ($lines as $line) {
            $row_no++;
            $f = explode($delim, $line);

            $fwd = isset($f[$i_fwd]) ? trim($f[$i_fwd], " \t\"'") : '';
            $rev = isset($f[$i_rev]) ? trim($f[$i_rev], " \t\"'") : '';

            if ($fwd === '' || $rev === '') {
                $result['errors'][] = "Row $row_no is missing a forward or reverse sequence.";
                continue;
            }

            $name = ($i_name !== null && isset($f[$i_name]) && trim($f[$i_name]) !== '')
                ? trim($f[$i_name], " \t\"'")
                : 'pair_' . ($row_no - 1);

            self::addPair($name, $fwd, $rev, $result);
        }
    }

    /**
     * Shape 2: each record already holds both primers, N-separated.
     */
    private static function pairsFromSplitRecords(array $records, array &$result)
    {
        foreach ($records as $rec) {
            $parts = preg_split(
                '/N{' . self::N_SEPARATOR_MIN . ',}/i',
                $rec['seq'],
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            if (count($parts) === 1) {
                $result['errors'][] = "\"{$rec['name']}\" has no N-separator, but other records do — "
                    . 'mixed formats are ambiguous, so this record was not used.';
                continue;
            }

            if (count($parts) > 2) {
                $result['errors'][] = "\"{$rec['name']}\" splits into " . count($parts)
                    . ' fragments; a pair needs exactly 2.';
                continue;
            }

            self::addPair($rec['name'], $parts[0], $parts[1], $result);
        }
    }

    /**
     * Shapes 1 and 4: one primer per record. Pair by name suffix where every
     * record carries one, otherwise fall back to adjacency.
     */
    private static function pairsFromSeparateRecords(array $records, array &$result, $allow_suffix = true)
    {
        if (empty($records)) {
            return;
        }

        $bySuffix = $allow_suffix ? self::groupBySuffix($records) : null;
        if ($bySuffix !== null) {
            foreach ($bySuffix as $base => $pair) {
                self::addPair($base, $pair['forward'], $pair['reverse'], $result);
            }
            return;
        }

        // Adjacency. An odd count means the tail record has no partner, and
        // guessing would silently mis-pair everything after the gap.
        $count = count($records);
        if ($count % 2 !== 0) {
            $last = $records[$count - 1]['name'];
            $result['errors'][] = "An odd number of primers ($count) was given and names carry no "
                . "F/R suffixes, so they were paired in the order supplied. \"$last\" has no partner "
                . 'and was not used.';
        }

        $result['warnings'][] = 'Primers were paired by the order they appear '
            . '(1st with 2nd, 3rd with 4th, …). Add _F / _R suffixes to name pairs explicitly.';

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $name = self::commonBaseName($records[$i]['name'], $records[$i + 1]['name']);
            self::addPair($name, $records[$i]['seq'], $records[$i + 1]['seq'], $result);
        }
    }

    /**
     * Group records into pairs by _F / _R style suffixes.
     *
     * Returns null unless EVERY record carries a recognised suffix and every
     * base name has exactly one forward and one reverse — a partial match means
     * adjacency is the safer reading, and mixing the two silently would pair
     * the wrong primers.
     */
    private static function groupBySuffix(array $records)
    {
        $groups = [];

        foreach ($records as $rec) {
            if (!preg_match('/^(.*?)[\s_.\-]([A-Za-z0-9]+)$/', $rec['name'], $m)) {
                return null;
            }

            $base   = $m[1];
            $suffix = strtolower($m[2]);

            if ($base === '') {
                return null;
            }

            if (in_array($suffix, self::$forward_suffixes, true)) {
                $role = 'forward';
            } elseif (in_array($suffix, self::$reverse_suffixes, true)) {
                $role = 'reverse';
            } else {
                return null;
            }

            if (isset($groups[$base][$role])) {
                return null; // two forwards for one base name: ambiguous
            }

            $groups[$base][$role] = $rec['seq'];
        }

        foreach ($groups as $pair) {
            if (!isset($pair['forward']) || !isset($pair['reverse'])) {
                return null;
            }
        }

        return $groups ?: null;
    }

    /**
     * Longest shared prefix of two names, for labelling an adjacency-formed pair.
     */
    private static function commonBaseName($a, $b)
    {
        $len = min(strlen($a), strlen($b));
        $i = 0;
        while ($i < $len && $a[$i] === $b[$i]) {
            $i++;
        }

        $base = rtrim(substr($a, 0, $i), " _.-");

        return $base !== '' ? $base : $a;
    }

    /**
     * Validate both sequences and record the pair.
     */
    private static function addPair($name, $forward, $reverse, array &$result)
    {
        $forward = strtoupper(trim($forward));
        $reverse = strtoupper(trim($reverse));

        foreach ([['forward', $forward], ['reverse', $reverse]] as $side) {
            list($label, $seq) = $side;

            $bad = preg_replace('/[' . self::VALID_BASES . ']/', '', $seq);
            if ($bad !== '') {
                $chars = implode(', ', array_unique(str_split($bad)));
                $result['errors'][] = "\"$name\" ($label) contains characters that are not bases: $chars";
                return;
            }

            $len = strlen($seq);
            if ($len < self::LEN_WARN_MIN) {
                $result['warnings'][] = "\"$name\" ($label) is {$len} nt — shorter than "
                    . self::LEN_WARN_MIN . ' nt, so genome-wide hits will be mostly noise.';
            } elseif ($len > self::LEN_WARN_MAX) {
                $result['warnings'][] = "\"$name\" ($label) is {$len} nt — longer than "
                    . self::LEN_WARN_MAX . ' nt, which usually needs extra purification when ordered.';
            }
        }

        $result['pairs'][] = [
            'name'    => $name,
            'forward' => $forward,
            'reverse' => $reverse,
        ];
    }
}
