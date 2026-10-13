<?php
/**
 * One colour per curated group, the same on every page and for every viewer.
 *
 * Group chips were coloured five ways: three pasted copies of a name hash (MOOPmart,
 * Annotation Search, Genome Browser), the home page by each group's alphabetical POSITION
 * among the groups the viewer could see (so a group changed colour with access level and
 * whenever a group was added), and the BLAST-style source list by position again, from
 * Bootstrap's palette. Measured 2026-10-13: home page and MOOPmart agreed on 1 of 15
 * groups -- under a comment saying they matched.
 *
 * Keyed on the NAME, so it depends on nothing else. JS never recomputes it: pages that
 * colour chips in the browser are handed the colours from here.
 */

const MOOP_GROUP_PALETTE = [
    '#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c',
    '#e67e22', '#e91e63', '#00bcd4', '#795548', '#607d8b',
];

/** The colour (hex) for a group, from its name alone. */
function moop_group_color(string $group_name): string {
    return MOOP_GROUP_PALETTE[crc32($group_name) % count(MOOP_GROUP_PALETTE)];
}

/** name => colour for several groups, for handing to JavaScript. */
function moop_group_colors(array $group_names): array {
    $out = [];
    foreach ($group_names as $g) $out[(string)$g] = moop_group_color((string)$g);
    return $out;
}
