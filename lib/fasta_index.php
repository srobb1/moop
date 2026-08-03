<?php
/**
 * Point lookups into a FASTA using its samtools .fai index — pure PHP, no process spawn.
 *
 * WHY THIS EXISTS
 *
 * Fetching a handful of sequences used to shell out to blastdbcmd. That costs ~110ms per
 * call and is almost entirely PROCESS STARTUP — `blastdbcmd -version` alone is 100ms, so
 * the price is the same whether you ask for one sequence or fifty. The gene page makes
 * three such calls (protein, transcript, cds), which made sequence extraction the ENTIRE
 * server-side cost of the page. Measured by skipping the calls:
 *
 *     3-isoform gene    0.467s -> 0.093s
 *     17-isoform gene   0.771s -> 0.059s
 *
 * The same lookup against a .fai is 4ms, and 4.8ms worst case with the ids at the very end
 * of the file — scanning a 1.3MB text index is cheap and, unlike a process spawn, scales
 * with what you actually asked for. api/get_sequence.php already uses this technique for
 * genome.fa; this generalises it to the gene-set FASTAs.
 *
 * The BLAST databases stay: they are what BLAST itself searches. This is a different
 * question — point lookup by id, not similarity search.
 *
 * WHAT A .fai LOOKS LIKE
 *
 *     name<TAB>length<TAB>offset<TAB>line_bases<TAB>line_width
 *
 * `offset` is the byte position of the first SEQUENCE byte (not the header). line_bases is
 * residues per line; line_width includes the newline(s), so a CRLF file has width = bases+2
 * and the arithmetic below still holds.
 *
 * Indexes are built by the pipeline (scripts/process_one_geneset.sh, beside makeblastdb).
 * Callers must handle their absence — see moop_fasta_index_available().
 */

/**
 * Is there a usable index for this FASTA?
 *
 * Also requires the index to be NO OLDER than the FASTA. A stale .fai does not fail
 * loudly — its byte offsets simply point into the wrong place and you get whatever
 * happens to sit there, which is worse than no index at all.
 */
function moop_fasta_index_available(string $fasta): bool
{
    $fai = $fasta . '.fai';
    return is_readable($fasta)
        && is_readable($fai)
        && filesize($fai) > 0
        && filemtime($fai) >= filemtime($fasta);
}

/**
 * Read the .fai entries for the ids we actually want.
 *
 * Deliberately does NOT build a map of the whole file: a gene-set index runs to tens of
 * thousands of entries and parsing all of them costs ~24ms, against ~4ms for scanning and
 * keeping only the handful asked for. It stops as soon as every id is found.
 *
 * @param  list<string> $wanted
 * @return array<string, array{len:int,off:int,lb:int,lw:int}>
 */
function moop_fai_lookup(string $fai, array $wanted): array
{
    $want = array_flip($wanted);
    $need = count($want);
    $out  = [];

    $fh = @fopen($fai, 'r');
    if ($fh === false) return $out;

    while ($need > 0 && ($line = fgets($fh)) !== false) {
        $tab = strpos($line, "\t");
        if ($tab === false) continue;
        $name = substr($line, 0, $tab);
        if (!isset($want[$name])) continue;          // cheap reject before any parsing
        $p = explode("\t", rtrim($line, "\r\n"));
        if (count($p) < 5) continue;
        $out[$name] = ['len' => (int)$p[1], 'off' => (int)$p[2],
                       'lb'  => (int)$p[3], 'lw'  => (int)$p[4]];
        $need--;
    }
    fclose($fh);
    return $out;
}

/**
 * Fetch sequences by exact id.
 *
 * Returns the residues with all line breaks removed; the caller decides how to wrap them.
 * Ids not present in the index are simply absent from the result — the same contract
 * blastdbcmd has when an entry does not exist, which callers already treat as "this id is
 * not in this file type" rather than as an error.
 *
 * @param  list<string> $ids
 * @return array<string,string>  id => sequence, in the order the ids were requested
 */
function moop_fasta_fetch(string $fasta, array $ids): array
{
    if ($ids === [] || !moop_fasta_index_available($fasta)) return [];

    $idx = moop_fai_lookup($fasta . '.fai', $ids);
    if ($idx === []) return [];

    $fh = @fopen($fasta, 'rb');
    if ($fh === false) return [];

    $out = [];
    foreach ($ids as $id) {                          // preserve caller order
        if (!isset($idx[$id])) continue;
        $e = $idx[$id];
        if ($e['len'] <= 0 || $e['lb'] <= 0) continue;

        // Bytes to read = residues + one line terminator per full line. lw - lb is the
        // terminator width, so this is correct for LF and CRLF alike.
        $lines = (int)ceil($e['len'] / $e['lb']);
        $bytes = $e['len'] + ($lines * max(0, $e['lw'] - $e['lb']));

        if (fseek($fh, $e['off']) !== 0) continue;
        $raw = fread($fh, $bytes);
        if ($raw === false) continue;

        $seq = preg_replace('/\s+/', '', $raw);
        // Guard against a stale index pointing somewhere plausible but wrong.
        if ($seq === null || strlen($seq) !== $e['len']) continue;
        $out[$id] = $seq;
    }
    fclose($fh);
    return $out;
}

/**
 * The full header line for an id, as it appears in the FASTA.
 *
 * The .fai stores only the id, but callers key their results on the WHOLE header —
 * description included — because that is what blastdbcmd emits. Reading it back means one
 * short seek per id to the byte before the sequence starts.
 *
 * @param  list<string> $ids
 * @return array<string,string>  id => header text WITHOUT the leading '>'
 */
function moop_fasta_headers(string $fasta, array $ids): array
{
    if ($ids === [] || !moop_fasta_index_available($fasta)) return [];
    $idx = moop_fai_lookup($fasta . '.fai', $ids);
    if ($idx === []) return [];

    $fh = @fopen($fasta, 'rb');
    if ($fh === false) return [];

    $out = [];
    foreach ($ids as $id) {
        if (!isset($idx[$id])) continue;
        // The header ends with the newline immediately before the sequence offset. Read a
        // generous window back from there; descriptions here run to a few hundred bytes
        // (source, accession, e-value), so 4096 is comfortable headroom.
        $back  = 4096;
        $start = max(0, $idx[$id]['off'] - $back);
        if (fseek($fh, $start) !== 0) continue;
        $chunk = fread($fh, $idx[$id]['off'] - $start);
        if ($chunk === false) continue;
        $chunk = rtrim($chunk, "\r\n");
        $nl    = strrpos($chunk, "\n");
        $hdr   = $nl === false ? $chunk : substr($chunk, $nl + 1);
        if ($hdr !== '' && $hdr[0] === '>') $out[$id] = substr($hdr, 1);
    }
    fclose($fh);
    return $out;
}

/**
 * Sequences AND headers in ONE pass over the index.
 *
 * moop_fasta_fetch() and moop_fasta_headers() each scan the .fai independently, so calling
 * both costs two scans of a 1.3MB index — ~3.3ms apiece, doubled, per FASTA. Every caller
 * that keys results by header needs both, so it is always the pair. This does the lookup
 * once and reuses it.
 *
 * @param  list<string> $ids
 * @return array<string, array{seq:string, header:string}>  keyed by id, caller order
 */
function moop_fasta_fetch_with_headers(string $fasta, array $ids): array
{
    if ($ids === [] || !moop_fasta_index_available($fasta)) return [];

    $idx = moop_fai_lookup($fasta . '.fai', $ids);       // the ONE scan
    if ($idx === []) return [];

    $fh = @fopen($fasta, 'rb');
    if ($fh === false) return [];

    $out = [];
    foreach ($ids as $id) {
        if (!isset($idx[$id])) continue;
        $e = $idx[$id];
        if ($e['len'] <= 0 || $e['lb'] <= 0) continue;

        // Header: read back from the sequence offset to the preceding newline.
        $start = max(0, $e['off'] - 4096);
        $hdr   = '';
        if (fseek($fh, $start) === 0) {
            $chunk = fread($fh, $e['off'] - $start);
            if ($chunk !== false) {
                $chunk = rtrim($chunk, "\r\n");
                $nl    = strrpos($chunk, "\n");
                $line  = $nl === false ? $chunk : substr($chunk, $nl + 1);
                if ($line !== '' && $line[0] === '>') $hdr = substr($line, 1);
            }
        }

        // Sequence: residues plus one terminator per full line (correct for LF and CRLF).
        $lines = (int)ceil($e['len'] / $e['lb']);
        $bytes = $e['len'] + ($lines * max(0, $e['lw'] - $e['lb']));
        if (fseek($fh, $e['off']) !== 0) continue;
        $raw = fread($fh, $bytes);
        if ($raw === false) continue;
        $seq = preg_replace('/\s+/', '', $raw);
        if ($seq === null || strlen($seq) !== $e['len']) continue;   // stale index guard

        $out[$id] = ['seq' => $seq, 'header' => ($hdr !== '' ? $hdr : $id)];
    }
    fclose($fh);
    return $out;
}

/**
 * Build the .fai if it is missing or stale, so one bad copy does not mean a full re-sync.
 *
 * The pipeline builds these on compute and copy2moop ships them, which is the normal path.
 * This is the safety net for the case that path misses: a gene set copied before the
 * indexing step existed, a FASTA replaced by hand, or an index left behind by a partial
 * transfer. Without it, recovering a single gene set means re-running and re-copying the
 * whole thing — and the symptom that prompts it is invisible, because the blastdbcmd
 * fallback keeps working and only the speed suffers.
 *
 * Costs ~0.14s for a 48MB FASTA, once — the request that triggers it pays, every request
 * after is served from the index. Worst case measured on this corpus is 2.86s for a 301MB
 * transcript FASTA, so a gene set with three large files could cost one visitor ~6-8s the
 * first time. That is within the 30s max_execution_time, and in any case PHP does not
 * count time spent in exec() against it. Verified NOT to repeat: six consecutive loads
 * reuse the same index (same inode), because rename() stamps the new .fai with the current
 * time, which is always >= the FASTA's mtime that the freshness test compares against.
 *
 * WRITTEN ATOMICALLY. samtools writes <fasta>.fai in place, so a concurrent reader could
 * otherwise pick up a half-written index — and a truncated .fai is worse than none, since
 * its byte offsets are read as authoritative. Building to a temp file in the SAME directory
 * (rename is only atomic within a filesystem) and renaming over means a reader sees either
 * the old index or the complete new one, never a partial. Two requests racing both produce
 * a valid file, so last-writer-wins is harmless.
 *
 * Returns false rather than throwing when samtools is absent or the directory is not
 * writable — callers fall back to blastdbcmd, which is slower but correct.
 */
function moop_fasta_ensure_index(string $fasta): bool
{
    if (moop_fasta_index_available($fasta)) return true;      // nothing to do
    if (!is_readable($fasta)) return false;

    $dir = dirname($fasta);
    if (!is_writable($dir)) return false;                     // read-only tree: give up quietly

    // Only try once per request per file. A FASTA that cannot be indexed (no samtools, a
    // malformed file) would otherwise re-attempt for every sequence type on the page.
    static $tried = [];
    if (isset($tried[$fasta])) return false;
    $tried[$fasta] = true;

    $samtools = 'samtools';
    if (class_exists('ConfigManager')) {
        $tools    = ConfigManager::getInstance()->getArray('blast_tools', []);
        $samtools = $tools['samtools'] ?? 'samtools';
    }

    $tmp = @tempnam($dir, '.fai.');
    if ($tmp === false) return false;

    $cmd = escapeshellcmd($samtools) . ' faidx -o ' . escapeshellarg($tmp)
         . ' ' . escapeshellarg($fasta) . ' 2>/dev/null';
    $out = []; $rc = 1;
    @exec($cmd, $out, $rc);

    if ($rc !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        return false;
    }

    // Match the FASTA's own permissions so the index is readable by everything that can
    // read the sequence it indexes; tempnam() creates 0600.
    @chmod($tmp, (fileperms($fasta) & 0777) ?: 0664);
    if (!@rename($tmp, $fasta . '.fai')) {                    // atomic within the directory
        @unlink($tmp);
        return false;
    }
    return moop_fasta_index_available($fasta);
}
