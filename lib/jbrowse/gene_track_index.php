<?php
/**
 * Gene track indexes: the sorted, bgzipped GFF + tabix index each JBrowse gene track reads
 * (data/genomes/{org}/{asm}/{gs}/annotations.gff3.gz[.tbi|.csi]).
 *
 * Small on purpose, so the gene page (lib/gff_gene_model.php) can ask "is this copy sound and
 * current?" with the SAME rule as housekeeping and registration, without loading the whole
 * JBrowse prep library. See docs/JBrowse2/GENE_TRACK_INDEXES.md.
 */
require_once __DIR__ . '/../functions_system.php';

/**
 * The BGZF end-of-file block. bgzip writes it last, so a file without it was truncated or
 * was never finished; a .tbi/.csi index is BGZF-compressed too and ends the same way.
 */
const MOOP_BGZF_EOF = "\x1f\x8b\x08\x04\x00\x00\x00\x00\x00\xff\x06\x00\x42\x43\x02\x00\x1b\x00\x03\x00\x00\x00\x00\x00\x00\x00\x00\x00";

/**
 * Is this a complete, non-empty BGZF file (a bgzipped GFF, or a tabix .tbi/.csi)?
 *
 * Reads 4 bytes at the start and 28 at the end: the BGZF header and the EOF block. Cheap
 * enough to run on every gene track every housekeeping interval, and it catches the states
 * an mtime cannot: a 0-byte file, an interrupted write, a plain-gzip or uncompressed file.
 * A file that is ONLY the EOF block (28 bytes) is an empty archive, so it fails too.
 */
function moop_bgzf_is_complete(string $path): bool {
    clearstatcache(true, $path);
    if (!is_file($path) || filesize($path) <= strlen(MOOP_BGZF_EOF)) {
        return false;
    }
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return false;
    }
    $head = fread($fh, 4);
    $tail = fseek($fh, -strlen(MOOP_BGZF_EOF), SEEK_END) === 0 ? fread($fh, strlen(MOOP_BGZF_EOF)) : '';
    fclose($fh);
    return $head === "\x1f\x8b\x08\x04" && $tail === MOOP_BGZF_EOF;
}

/**
 * What, if anything, is wrong with a gene track's compressed GFF and tabix index.
 *
 * THE single rule. The dashboard count (housekeeping), the background rebuild's work list,
 * the prep step and the gene page's tabix fast path (lib/gff_gene_model.php) all call this.
 * It used to be written out five times, and the copies drifted.
 *
 * @param string $gff  data/genomes/{org}/{asm}/{gs}/annotations.gff3 (the symlink).
 * @return array{kind: 'missing'|'stale'|'broken', why: string}|null  null = sound and current.
 */
function gene_track_index_problem(string $gff): ?array {
    $gz  = "$gff.gz";
    clearstatcache();
    if (!file_exists($gz)) {
        return ['kind' => 'missing', 'why' => 'no compressed GFF'];
    }
    if (!moop_bgzf_is_complete($gz)) {
        return ['kind' => 'broken', 'why' => 'compressed GFF is empty, truncated or unreadable'];
    }
    // filemtime() follows the symlink, so this compares against the real GFF.
    if (file_exists($gff) && filemtime($gz) < filemtime($gff)) {
        return ['kind' => 'stale', 'why' => 'compressed GFF older than source'];
    }
    $index = file_exists("$gz.tbi") ? "$gz.tbi" : (file_exists("$gz.csi") ? "$gz.csi" : null);
    if ($index === null) {
        return ['kind' => 'missing', 'why' => 'no tabix index'];
    }
    if (!moop_bgzf_is_complete($index)) {
        return ['kind' => 'broken', 'why' => 'tabix index is empty, truncated or unreadable'];
    }
    // An index older than the .gz it describes points at the wrong offsets.
    if (filemtime($index) < filemtime($gz)) {
        return ['kind' => 'stale', 'why' => 'tabix index older than compressed GFF'];
    }
    return null;
}

/**
 * Sort + bgzip + tabix a gene track, replacing the live files only once the new ones are
 * verified. Returns false (and logs why) without touching the live files on any failure.
 *
 * The order is the point. This used to unlink the old .gz/.tbi FIRST and then run
 * `... | bgzip > annotations.gff3.gz`. On 2026-10-13 `sudo -u apache` ran it with a PATH
 * lacking /usr/local/bin: the shell created the output file, bgzip was "command not found",
 * and six tracks were left as 0-byte files -- newer than their GFF, so nothing noticed.
 * Now: (1) resolve bgzip and tabix to absolute paths before anything else, (2) build into a
 * temp name beside the target, under pipefail, (3) check the result is complete BGZF and
 * that tabix indexed it, (4) rename into place. A failed run leaves what was there.
 */
function gene_track_build_index(string $gff, string $label, array &$log): bool {
    $gz    = "$gff.gz";
    $bgzip = moop_find_executable('bgzip');
    $tabix = moop_find_executable('tabix');
    if ($bgzip === '' || $tabix === '') {
        $missing = implode(' and ', array_keys(array_filter(['bgzip' => $bgzip === '', 'tabix' => $tabix === ''])));
        $log[] = "$label: ERROR — $missing not found (looked on PATH, /usr/local/bin, /usr/bin). "
               . 'Nothing was changed; install htslib or fix PATH and re-run.';
        return false;
    }

    // Temp files sit BESIDE the target so the final rename() is on one filesystem (atomic).
    // tabix names its index after the file it indexes: $tmp.tbi / $tmp.csi.
    $tmp     = "$gz.tmp-" . getmypid();
    $cleanup = function () use ($tmp) {
        foreach ([$tmp, "$tmp.tbi", "$tmp.csi"] as $f) {
            if (file_exists($f)) @unlink($f);
        }
    };
    $cleanup();

    // Headers first, then features sorted by seqid and start -- what tabix requires.
    $sorted = '(grep "^#" ' . escapeshellarg($gff)
            . '; grep -v "^#" ' . escapeshellarg($gff)
            . ' | sort -t"$(printf \'\\t\')" -k1,1 -k4,4n)';
    $out = []; $rc = 1;
    exec('/bin/bash -c ' . escapeshellarg('set -o pipefail; ' . $sorted . ' | ' . escapeshellarg($bgzip)
         . ' > ' . escapeshellarg($tmp)) . ' 2>&1', $out, $rc);

    if ($rc !== 0 || !moop_bgzf_is_complete($tmp)) {
        // GFFs whose parents follow their children need jbrowse's own sort.
        $jb = moop_find_jbrowse_cli();
        if ($jb) {
            $out = []; $rc = 1;
            exec('/bin/bash -c ' . escapeshellarg('set -o pipefail; ' . escapeshellarg($jb) . ' sort-gff '
                 . escapeshellarg($gff) . ' | ' . escapeshellarg($bgzip) . ' > ' . escapeshellarg($tmp))
                 . ' 2>&1', $out, $rc);
            $log[] = $rc === 0 ? "$label: sorted with jbrowse sort-gff" : "$label: jbrowse sort-gff also failed";
        }
    }
    if ($rc !== 0 || !moop_bgzf_is_complete($tmp)) {
        $log[] = "$label: ERROR — bgzip failed; existing index left untouched. " . implode(' ', array_slice($out, -3));
        $cleanup();
        return false;
    }

    $type = 'tbi';
    $out = []; $rc = 1;
    exec(escapeshellarg($tabix) . ' -p gff ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        $err = implode(' ', $out);
        // TBI cannot address chromosomes over 512 Mb; CSI can.
        if (stripos($err, 'coordinate') !== false || stripos($err, 'CSI') !== false) {
            $out = []; $rc = 1;
            exec(escapeshellarg($tabix) . ' -C -p gff ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
            $type = 'csi';
        }
    }
    if ($rc !== 0 || !moop_bgzf_is_complete("$tmp.$type")) {
        $log[] = "$label: ERROR — tabix failed; existing index left untouched. " . implode(' ', array_slice($out, -3));
        $cleanup();
        return false;
    }

    // Swap in: the .gz first, then its index. The index was written after the .gz, and
    // rename() keeps mtimes, so the pair passes the freshness rule. If the second rename
    // fails, the old index is older than the new .gz and the next check rebuilds it.
    $other = $type === 'tbi' ? "$gz.csi" : "$gz.tbi";
    if (!@rename($tmp, $gz) || !@rename("$tmp.$type", "$gz.$type")) {
        $log[] = "$label: ERROR — could not move the rebuilt index into place (is the directory writable by the web server?)";
        $cleanup();
        return false;
    }
    if (file_exists($other)) {
        @unlink($other);   // never leave a stale index of the other type for a reader to pick
    }

    clearstatcache();
    $log[] = "$label: bgzip OK (" . number_format(filesize($gz)) . ' bytes), tabix ' . strtoupper($type)
           . ' OK (' . number_format(filesize("$gz.$type")) . ' bytes)'
           . ($type === 'csi' ? ' — chromosomes exceed the TBI limit' : '');
    return true;
}
