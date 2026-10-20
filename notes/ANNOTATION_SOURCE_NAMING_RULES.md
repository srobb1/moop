# Annotation source names — rules the loader enforces (for the compute box)

**Status:** agreed 2026-10-20 (user). To be implemented in
`config/build_and_load_db/data_loaders/load_annotations_sqlite.pl`. Rule 2 is already in place.

## Why

The web server finds annotation sources by **name**: the search page's "annotation types" step,
the filter on the organism / assembly / gene set pages, and MOOPmart. Three naming problems
broke that silently (found 2026-10-20, search page review):

| Problem | Effect on the site | Status |
|---|---|---|
| The same name under two annotation types (`"Ensembl Homo sapiens"` as both Homologs and RBBH Homolog) | Filtering by one also matched the other; 65 of 85 databases | Fixed in the pipeline 2026-10-06 (`9eb4a2c4`, `6bbf80fb`); gone on rerun |
| A trailing space as the only difference between two sources (`"Ensembl Danio rerio "` = RBBH, `"Ensembl Danio rerio"` = Homologs) | The web trimmed the name, so every filtered search returned no RBBH rows; 8 databases | Parser trims since 2026-07-24; gone on rerun. The web no longer trims either |
| A comma in a name (`"Closest human gene (Ensembl, no HGNC record)"`, written by `assign_gene_names_v2.pl`) | The web split the filter on commas, so the source never matched | Fixed on the web side (JSON list, 2026-10-20). Commas are allowed |

The web side now matches names **exactly as stored** and accepts any character. So the loader is
the one place that decides what a stored name looks like, and it should guarantee these rules for
every file, whichever parser wrote it.

## The rules

Applied to the `## Annotation Source:` name **and** to the annotation type, for every file loaded.

1. **Normalise whitespace — never reject for it.**
   Trim leading and trailing whitespace; collapse any run of internal whitespace (spaces, tabs)
   to one space. A stray space is never meaningful, so silently fixing it is safe. Print one
   line when a name was changed, naming the file, so the parser that produced it can be fixed.
2. **One annotation type per source name.** ✅ Already enforced (`9eb4a2c4`): a second file of the
   same run under a source already loaded with another type dies, naming both files. Keep it, and
   apply it AFTER rule 1, so `"X "` and `"X"` count as the same name.
3. **Reject what cannot be a name — die, naming the file.**
   - empty after normalising;
   - control characters (anything below 0x20 or 0x7F) — a tab or newline in a name breaks every
     TSV export and the search filter;
   - invalid UTF-8.
4. **Everything else is allowed.** Commas, quotes, parentheses, slashes, colons and non-ASCII
   letters are all fine — the web handles any character. Do not "simplify" names to avoid them;
   names are what users read in the filter.

Suggested limit (warn, do not die): over 200 characters, since the filter list becomes hard to read.

## Implementation notes

- One small sub, e.g. `normalise_source_name($name, $file)`, called wherever the loader reads the
  source name and the annotation type, before the source lookup or insert.
- Perl conventions (user maintains these scripts): named loop variables, never bare `$_`; all
  work in `sub main`, `main();` as the last line (the file-scope trap — `CLAUDE.md` §12).
- A test in `config/build_and_load_db/tests/`: a made-up file with `"  A   B  "`, `"A, B (C)"`,
  a tab, and an empty name — the first two load as `"A B"` and `"A, B (C)"`, the last two die.
- Databases already loaded keep their names until rerun; nothing on the web side depends on them
  being rerun first.
