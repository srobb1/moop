<?php
/**
 * ONE way to name a downloaded file.
 *
 * A download is the part of MOOP that leaves the site. It gets renamed, emailed, dropped
 * in a shared folder, and opened months later by someone who never saw the page that
 * produced it. Anything the filename omits is gone at that point.
 *
 * THE RULE: a filename says WHAT the file is, WHERE it came from, and WHETHER it is
 * complete.
 *
 *     {what}_{scope}_{Y-m-d}[_MARKER].{ext}
 *
 * Before this existed the app produced (audited 2026-07-30/31, all 13 producers verified by
 * downloading each file; the audit note is closed and was deleted):
 *
 *   - three date formats -- Y-m-d, Ymd_His and Y-m-d_His -- of which only Y-m-d sorts
 *     correctly in a file listing;
 *   - dots as separators in one place ({organism}.{assembly}.{pattern}) and underscores
 *     everywhere else, and a dot-separated name reads as a chain of extensions to some tools;
 *   - no provenance at all in several: sequences_pep_{date}.fa does not say which organism,
 *     so two organisms' protein sets land in one folder as near-identical names.
 *
 * Dots are preserved INSIDE a part, because feature and assembly identifiers legitimately
 * contain them (bkew.kc1.004061_0_1.4) and mangling an id makes it unusable for pasting
 * back into a search. Dots are never used BETWEEN parts. That is the distinction the old
 * fasta handler got wrong.
 */

/**
 * Sanitise one component. Keeps letters, digits, dot, dash and underscore -- so real
 * identifiers survive intact -- and collapses everything else.
 */
function moop_filename_part(string $part): string
{
    $part = preg_replace('/[^A-Za-z0-9._-]+/', '_', $part);
    $part = preg_replace('/_+/', '_', $part);
    return trim($part, '_.');
}

/**
 * Build a download filename.
 *
 * @param string $what   What the file is: 'annotations', 'sequences', 'moopmart', ...
 * @param array  $scope  Provenance, most general first: organism, assembly, gene set, type.
 *                       Empty entries are dropped, so callers can pass what they have.
 * @param string $ext    Extension without the dot ('csv', 'fasta', 'tar.gz').
 * @param string $marker Optional suffix for a file that is NOT complete, e.g.
 *                       'CAPPED-2500-per-organism'. Anything that makes the data partial
 *                       belongs here -- that is the third part of the rule.
 */
function moop_download_filename(string $what, array $scope = [], string $ext = 'txt', string $marker = ''): string
{
    $parts = [moop_filename_part($what)];
    foreach ($scope as $s) {
        $s = moop_filename_part((string)$s);
        if ($s !== '') $parts[] = $s;
    }
    $parts[] = date('Y-m-d');
    if ($marker !== '') $parts[] = moop_filename_part($marker);

    $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
    return implode('_', $parts) . '.' . ltrim($ext, '.');
}

/**
 * How to name the ORGANISM part when a download can span several.
 *
 * Many exports are cross-organism -- a group search, a MOOPmart export over a whole group --
 * so naming the file after one organism would be a lie. Naming it after none loses the
 * provenance entirely.
 *
 *   0 organisms  ''                (caller decides what to do)
 *   1 organism   'Bipalium_kewense'
 *   many, group  'Planaria'        the group is the truthful, human name for the set
 *   many, no group  '15-organisms' honest about breadth without inventing a name
 *
 * @param array  $organisms Organism names actually included
 * @param string $group     Group name if the export came from a group page
 */
function moop_download_scope_label(array $organisms, string $group = ''): string
{
    $organisms = array_values(array_unique(array_filter(array_map('strval', $organisms))));
    $n = count($organisms);
    if ($n === 0) return $group !== '' ? moop_filename_part($group) : '';
    if ($n === 1) return moop_filename_part($organisms[0]);
    if ($group !== '') return moop_filename_part($group);
    return $n . '-organisms';
}
