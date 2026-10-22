# From the web side, 2026-10-22: a "provenance" field in genome.json / geneset.json

**Asked of the compute side.** Web-side work is done and live; nothing here edits pipeline files.

## What the user wants

A place to write down where an assembly or a gene set came from. Field name chosen by the user:
`"provenance"` — free text, any length, line breaks allowed, URLs become links on the site.

## Why it has to be written on the compute box

`copy2moop_v2.sh` sends `genome.json` and `geneset.json` with rsync on every run, so the web
server's copy is overwritten whenever the compute copy differs. A note typed on the web server
would be lost on the next copy. The compute-box JSON is the master; the site only displays it.

## Asked of the pipeline

`scripts/process_one_geneset.sh`, `write_genome_json()` and `write_geneset_json()`: add
`"provenance": ""` to the JSON they write, so every NEW file has the field to fill in (today
nobody knows it exists — the site has displayed an optional `"note"` since May 2026, and no file
has ever had one, because nothing writes it).

Existing files are left alone by those functions (`[ -f "$file" ] && return`), which is right:
the user will add the field by hand where they have something to say.

## What the site does with it (already live)

- Assembly page and gene set page: a **Provenance** row under Source, shown only when non-empty.
  The existing `"note"` field is still shown as **Note**, rendered the same way.
- `moop_text_with_links()` (`lib/functions_display.php`): escaped text, http(s)/ftp URLs linked.
- The help page "How the data is organized" lists the field.

Example:

```json
{
  "accession": "RS_101",
  "name": "",
  "source": "RefSeq",
  "date_added": "2026-07-03",
  "provenance": "NCBI RefSeq annotation release 101, downloaded 2026-07-03 from\nhttps://ftp.ncbi.nlm.nih.gov/genomes/all/GCF/932/526/225/"
}
```
