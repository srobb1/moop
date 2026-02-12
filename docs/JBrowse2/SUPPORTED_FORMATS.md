# JBrowse2 Supported File Formats

This document lists all file formats supported by JBrowse2, based on the installed adapters.

**Source:** JBrowse2 v2.x installation in `/data/moop/jbrowse2/`

---

## ✅ Currently Implemented in MOOP

| Format | Extensions | Adapter | Script | Status |
|--------|------------|---------|--------|--------|
| **BigWig** | `.bw`, `.bigwig` | `BigWigAdapter` | `add_bigwig_track.sh` | ✅ Working |
| **BAM** | `.bam` + `.bai` | `BamAdapter` | `add_bam_track.sh` | ✅ Working |
| **VCF** | `.vcf.gz` + `.tbi` | `VcfTabixAdapter` | `add_vcf_track.sh` | ✅ Working |
| **GFF3** | `.gff`, `.gff3.gz` + `.tbi` | `Gff3TabixAdapter` | `add_gff_track.sh` | ✅ Working |
| **FASTA** | `.fa`, `.fasta` + `.fai` | `IndexedFastaAdapter` | (assembly setup) | ✅ Working |

---

## 🔧 Easy to Add (High Priority)

| Format | Extensions | Adapter | Use Case | Effort |
|--------|------------|---------|----------|--------|
| **CRAM** | `.cram` + `.crai` | `CramAdapter` | More efficient than BAM | 1 hour |
| **PAF** | `.paf` | `PAFAdapter` | Long-read alignments | 1 hour |
| **GTF** | `.gtf` | `GtfAdapter` | Gene annotations (alternative to GFF) | 30 min |
| **BED** | `.bed` + `.tbi` | `BedTabixAdapter` | Generic genomic features | 1 hour |

---

## 📊 All JBrowse2 Supported Formats

### Sequence/Reference Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **Indexed FASTA** | `IndexedFastaAdapter` | `.fa`, `.fasta` + `.fai` | Standard reference sequences |
| **Bgzip FASTA** | `BgzipFastaAdapter` | `.fa.gz` + `.gzi` | Compressed FASTA |
| **2bit** | `TwoBitAdapter` | `.2bit` | UCSC compact format |

### Alignment Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **BAM** | `BamAdapter` | `.bam` + `.bai` | ✅ Implemented |
| **CRAM** | `CramAdapter` | `.cram` + `.crai` | More efficient than BAM |
| **HTSGET BAM** | `HtsgetBamAdapter` | N/A | Streaming BAM via HTSGET protocol |
| **PAF** | `PAFAdapter` | `.paf` | Long-read alignments (minimap2) |
| **Pairwise PAF** | `PairwiseIndexedPAFAdapter` | `.paf` | Indexed PAF for synteny |

### Variant Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **VCF** | `VcfAdapter` | `.vcf` | ✅ Implemented (tabix version) |
| **VCF Tabix** | `VcfTabixAdapter` | `.vcf.gz` + `.tbi` | ✅ Implemented |

### Annotation/Feature Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **GFF3** | `Gff3Adapter` | `.gff`, `.gff3` | ✅ Implemented (tabix version) |
| **GFF3 Tabix** | `Gff3TabixAdapter` | `.gff.gz` + `.tbi` | ✅ Implemented |
| **GTF** | `GtfAdapter` | `.gtf` | Gene annotations (Ensembl format) |
| **BED** | `BedAdapter` | `.bed` | Unindexed BED files |
| **BED Tabix** | `BedTabixAdapter` | `.bed.gz` + `.tbi` | Indexed BED files |
| **BigBed** | `BigBedAdapter` | `.bb`, `.bigbed` | Binary indexed BED |
| **NCList** | `NCListAdapter` | (JBrowse 1 format) | Legacy format |

### Quantitative/Signal Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **BigWig** | `BigWigAdapter` | `.bw`, `.bigwig` | ✅ Implemented |
| **BedGraph** | `BedGraphAdapter` | `.bedgraph`, `.bg` | Text-based signal data |
| **Multi-BigWig** | `MultiWiggleAdapter` | Multiple `.bw` | ✅ Implemented (combo tracks) |

### Interaction/Structural Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **HiC** | `HicAdapter` | `.hic` | Chromatin interaction data |
| **BedPE** | `BedpeAdapter` | `.bedpe` | Paired-end genomic regions |

### Comparative Genomics

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **Chain** | `ChainAdapter` | `.chain` | UCSC liftover chains |
| **Delta** | `DeltaAdapter` | `.delta` | MUMmer alignment format |
| **MCScan** | `MCScanAnchorsAdapter` | `.anchors` | Synteny anchors |
| **MCScan Simple** | `MCScanSimpleAnchorsAdapter` | `.anchors.simple` | Simplified synteny |

### Specialized Formats

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **BLAST Tabular** | `BlastTabularAdapter` | `.blast` | BLAST output |
| **Cytoband** | `CytobandAdapter` | `.txt` | Chromosome banding patterns |
| **ChromSizes** | `ChromSizesAdapter` | `.sizes` | Chromosome sizes |
| **GC Content** | `GCContentAdapter` | N/A | Calculated on-the-fly |

### Text Search

| Format | Adapter | Extensions | Notes |
|--------|---------|------------|-------|
| **Trix** | `TrixTextSearchAdapter` | `.ix` + `.ixx` | ✅ Implemented for GFF |
| **JBrowse 1** | `JBrowse1TextSearchAdapter` | (legacy) | Migration support |

### External Data Sources

| Format | Adapter | Notes |
|--------|---------|-------|
| **UCSC API** | `UCSCAdapter` | Fetch tracks from UCSC Genome Browser |
| **MyGene.info** | `MyGeneV3Adapter` | Gene annotation API |
| **SPARQL** | `SPARQLAdapter` | Query semantic web databases |

### Configuration-Based

| Format | Adapter | Notes |
|--------|---------|-------|
| **From Config** | `FromConfigAdapter` | Features defined in JSON config |
| **RefName Aliases** | `RefNameAliasAdapter` | Chromosome name mappings |
| **NCBI Aliases** | `NcbiSequenceReportAliasAdapter` | NCBI accession mappings |

---

## Priority Recommendations for MOOP

### High Priority (Should Add Soon)

1. **CRAM** - More efficient than BAM, widely used
2. **PAF** - Essential for long-read data (PacBio, Nanopore)
3. **GTF** - Many annotations come in GTF format (Ensembl)
4. **BED** - Very common generic feature format

### Medium Priority

5. **BigBed** - Efficient for large BED files
6. **BedGraph** - Some tools output this instead of BigWig
7. **HiC** - If users work with chromatin conformation

### Low Priority

8. **Chain** - Only if doing liftover/comparative genomics
9. **Delta** - Only if using MUMmer alignments
10. **BLAST Tabular** - Only if integrating BLAST results

---

## Adding New Format Support

See: `docs/JBrowse2/ADDING_NEW_TRACK_TYPES.md`

**Quick steps:**
1. Add extension detection in `generate_tracks_from_sheet.py`
2. Add track handler in same file
3. Create `add_XXX_track.sh` script with appropriate adapter

---

## Format Decision Guide

**Choose format based on data type:**

- **Alignments:** BAM (or CRAM for space efficiency)
- **Gene annotations:** GFF3 (or GTF if from Ensembl)
- **Quantitative signal:** BigWig
- **Variants:** VCF
- **Generic features:** BED or GFF3
- **Long-read alignments:** PAF
- **Chromosome interactions:** HiC
- **Comparative genomics:** Chain, Delta, or PAF

---

## Notes

- **Tabix-indexed formats** (`.gz` + `.tbi`) are preferred for large files
- **Binary formats** (BigWig, BigBed, BAM, CRAM) are more efficient than text
- **Index files** (`.bai`, `.tbi`, `.fai`, `.crai`) are **required** for their respective formats
- **Remote URLs** work with most adapters (HTTP/HTTPS)

---

## References

- **JBrowse2 Config Guide:** https://jbrowse.org/jb2/docs/config_guide/
- **Adapter Documentation:** https://jbrowse.org/jb2/docs/config_guide/#adapter-types
- **Local Installation:** `/data/moop/jbrowse2/`
