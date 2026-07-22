# BLAST result linkouts

Every BLAST hit can carry links — to the gene page, into the genome browser at the hit
coordinates, and out to external databases. They are configured entirely through the admin
UI; nothing here requires editing files.

**Admin → Manage BLAST Linkouts.**

---

## Contents

- [The three kinds of linkout](#the-three-kinds-of-linkout)
- [External link templates](#external-link-templates)
- [Genome-browser links and HSP joining](#genome-browser-links-and-hsp-joining)
- [The coordinate index](#the-coordinate-index)
- [Where the settings live](#where-the-settings-live)
- [Troubleshooting](#troubleshooting)

---

## The three kinds of linkout

| Kind | What it does | Needs |
|---|---|---|
| **Gene page** | links the hit to its MOOP gene page | the coordinate index |
| **Genome browser** | opens JBrowse2 at the hit, HSPs drawn as a feature | the coordinate index + a JBrowse registration |
| **External** | any URL you like, built from the hit | nothing |

The first two are on/off toggles with an editable button label (default "Gene Page" and
"Genome Browser"). External links you add yourself, and there is no limit.

External links come in two flavours:

- **Global** — shown for every BLAST database.
- **Per database** — shown only for hits in one specific database, so you can send
  *Nematostella* protein hits somewhere different from *Drosophila* nucleotide hits.

---

## External link templates

A link is a **label** plus a **URL template**. The template is a normal URL with
placeholders substituted per hit:

| Placeholder | Replaced with |
|---|---|
| `{fasta_id}` | the hit's sequence ID, as it appears in the FASTA |
| `{organism}` | the organism directory name, e.g. `Nematostella_vectensis` |
| `{assembly}` | the assembly name, e.g. `GCA_033964005.1` |

Example:

```
Label: NCBI Protein
URL:   https://www.ncbi.nlm.nih.gov/protein/?term={fasta_id}
```

**The URL must start with `http://` or `https://`.** Anything else is rejected — including
`ftp://`, which the browser's own URL field will happily accept, so the check is
server-side. A row missing either its label or its URL is rejected too.

Rejected rows are reported back to you by name. If you save five links and one is malformed,
you get four saved and a message naming the fifth — it is not silently dropped. (It used to
be, before 2026-07-21.)

Per-database links additionally need a database selected. Internally the key is
`organism|assembly|sequence_type`, but the UI gives you a dropdown — you never type it.

---

## Genome-browser links and HSP joining

A BLAST hit is usually several HSPs — separate aligned blocks along the subject sequence.
The browser link draws them as one feature so you can see the alignment structure, but only
when joining them is sensible. Three settings control that:

| Setting | Default | Effect |
|---|---|---|
| `jbrowse_hsp_min_score` | `0` | HSPs scoring below this are shown but not joined |
| `jbrowse_hsp_max_span` | `500000` | if the HSPs span more than this many bases, they are not joined |
| `jbrowse_hsp_max_link` | `10` | at most this many HSPs per hit, highest-scoring first |

The span limit is the one that matters in practice. Two HSPs on the same scaffold but
megabases apart are far more likely to be separate paralogous regions than one alignment, and
joining them draws a feature spanning the whole distance — which is both wrong and unreadable.
HSPs that fall outside a limit are still displayed; they are labelled with the reason
(for example "below score threshold") rather than hidden.

---

## The coordinate index

Gene-page and genome-browser links need to know **where** a hit sequence lives. BLAST reports
a subject ID, not a genomic position, so MOOP keeps a per-gene-set index:

```
organisms/{organism}/{assembly}/{gene_set}/feature_coords.tsv
```

Six tab-separated columns: `uniquename`, `gene_id`, `chr`, `start`, `end`, `strand`.

It is **generated proactively when an assembly is registered with JBrowse**, from the gene
set's GFF. This is deliberate — there is no lazy "build it on first BLAST" fallback, because
that would put a multi-minute file scan inside a user's search.

**Manage BLAST Linkouts shows the index status for every registered assembly**, with a button
to build or rebuild one. Rebuild after replacing a gene set's GFF; the coordinates come from
that file and will otherwise be stale.

Generation needs the gene set's genes GFF to be present and readable. If it is missing, the
build reports that rather than producing an empty index.

> The same file also backs coordinate-range filtering in MOOPmart, so it is worth having even
> if you do not use linkouts.

---

## Where the settings live

Saved under the `blast_linkouts` key in `config/config_editable.json`, which is included in
the site-data backup. Defaults ship in `config/site_config.php`; the admin page writes only
what you change, and unset sub-keys keep their shipped default via the config deep-merge.

> **Historical note worth knowing.** Until 2026-07-21 `blast_linkouts` was missing from
> `ConfigManager::$editableConfigKeys`, so every setting on this page was written to
> `config_editable.json` and then **ignored on load** — and a later Site Configuration save
> would have deleted the key entirely. The page appeared to work and did nothing. If you are
> reading a very old backup and wondering why the key is absent, that is why.

---

## Troubleshooting

**A linkout does not appear on any hit.** Check the toggle is on, then check the coordinate
index exists for that gene set — gene-page and browser links are skipped for hits MOOP
cannot place.

**Links appear for some assemblies but not others.** Almost always a missing
`feature_coords.tsv`. The status table on the admin page shows exactly which are missing.

**An assembly is missing from the index status table entirely.** The table lists JBrowse
registrations, and skips any whose `organisms/` directory is not there. A registration
without its data directory means the assembly was renamed or removed after registration —
fix that first, in Manage JBrowse, because it also breaks the genome browser for that
assembly.

**A saved URL vanished.** It was rejected — re-open the page and read the message. The usual
cause is a scheme other than `http`/`https`.

**Browser links open at the wrong place.** The index is stale relative to the GFF. Rebuild it.

---

## Related

- [JBrowse2 admin guide](JBrowse2/ADMIN_GUIDE.md) — registering assemblies
- [Setting up a new organism](JBrowse2/SETUP_NEW_ORGANISM.md)
- [README — common tasks](../README.md#common-tasks)
