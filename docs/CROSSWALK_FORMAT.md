# crosswalk.sqlite — the format MOOP expects

`organisms/<Organism>/crosswalk.sqlite` maps IDs from many sources (transcriptomes, microarrays,
ESTs, gene symbols) to **reference genes**. MOOP uses it for gene links in PAGE results, "Also
known as" on gene pages, and the Rosetta Stone tool (`lib/crosswalk.php`). Pages open it
read-only.

**Who builds it:** normally the compute pipeline (a script in `config/build_and_load_db/`), and
`copy2moop` ships it next to `organism.sqlite` — the raw mapping files never need to be on the
web server. `scripts/build_crosswalk.php` builds the same file from a local folder (tests, or a
site without a compute pipeline). Both must produce exactly this format.

## Input (what the mapping step produces)

One tab-separated file per **hub** (a reference gene set every accession maps to):

    seq_id <TAB> ref_id <TAB> seq_id_source        (header line exactly this)
    dd_Smed_v4_12_0_1	SMED30005911	dd_Smed_v4

Rows whose `ref_id` is `"NA"` are left out.

## Output

```sql
CREATE TABLE map (
  accession    TEXT NOT NULL,   -- seq_id, as written
  accession_lc TEXT NOT NULL,   -- lower(seq_id): lookups are case-insensitive
  source       TEXT NOT NULL,   -- seq_id_source, e.g. dd_Smed_v4, GPL14150, smed_ncbi_20240424
  ref_id       TEXT NOT NULL,   -- the reference gene / transcript id
  hub          TEXT NOT NULL    -- which hub file the row came from, e.g. schMedS3h1_WBPS19
);
CREATE INDEX m_acc ON map (accession_lc);
CREATE INDEX m_ref ON map (ref_id, hub);
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT);
```

`meta` must hold:

| key | value |
|---|---|
| `format` | `crosswalk/1` — MOOP refuses a file without it (a wrong file gives a clear error, not odd results) |
| `organism` | the MOOP organism folder name, e.g. `Schmidtea_mediterranea` |
| `built_at` | ISO 8601 time |
| `hubs` | JSON object, hub -> rows loaded, e.g. `{"schMedS3h1_WBPS19":1203019}` |
| `rows`, `accessions`, `genes`, `sources` | counts (informational) |

Hub names must match the organism's `crosswalk` entry in MOOP's configuration, which says which
hubs link to MOOP gene pages (`gene_set`, `assembly`) and which link out. Schmidtea mediterranea:
`schMedS3h1_WBPS19` (S3 h1 genes), `schMedS3h2_WBPS19` (S3 h2 genes), `smed_20140614` (SMED3
transcripts). Since 2026-11-11 compute names every hub and source after its MOOP gene set.

Build to a temporary name and move it into place only when complete (`PRAGMA integrity_check`
= `ok`), so MOOP never opens a half-written file.
