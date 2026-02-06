# Track Config Files: Structure & Examples

## Overview

Instead of generating one monolithic config per organism-assembly, use modular track configs:

- **Stored separately**: `/moop/metadata/jbrowse2-configs/tracks/`
- **One file per track type/dataset**
- **Version-controlled**: Can update tracks without touching assembly configs
- **Reusable**: Same track can appear in multiple assemblies
- **Permission-aware**: Each track specifies which access levels can see it

---

## Track Config Structure

```json
{
  "name": "Display name for user interface",
  "description": "Longer description shown in track details",
  "track_id": "unique_identifier_in_config",
  "type": "quantitative|alignment|feature|variant|etc",
  "access_levels": ["Public", "Collaborator", "ALL"],
  "groups": ["Category1", "Category2"],
  "file_format": "bigwig|bam|gff3|vcf",
  "file_template": "{organism}_{assembly}_{track_name}.ext",
  
  "color": "#RRGGBB",
  "description_url": "https://...",
  
  "display_config": {
    "type": "WiggleYScaleQuantitativeTrack|LinearAlignmentsDisplay|...",
    "defaultRendering": "density|svg",
    "height": 100
  },
  
  "adapter_config": {
    "type": "BigWigAdapter|BamAdapter|GffAdapter|...",
    "extra": "adapter-specific options"
  },
  
  "metadata": {
    "source": "RNA-seq pipeline v2.1",
    "conditions": ["Leaf", "Root"],
    "replicates": 3,
    "publication": "doi:..."
  }
}
```

---

## Example Track Configs

### 1. Public RNA-seq Coverage (BigWig)

```json
// /moop/metadata/jbrowse2-configs/tracks/rna_seq_coverage.json

{
  "name": "RNA-seq Coverage",
  "description": "Whole transcriptome sequencing coverage across all tissues",
  "track_id": "rna_seq_coverage",
  "type": "quantitative",
  "access_levels": ["Public", "Collaborator", "ALL"],
  "groups": ["Expression", "Quantitative"],
  "file_format": "bigwig",
  "file_template": "{organism}_{assembly}_rna_coverage.bw",
  
  "color": "#1f77b4",
  
  "display_config": {
    "type": "WiggleYScaleQuantitativeTrack",
    "defaultRendering": "density",
    "height": 100,
    "scale": "linear"
  },
  
  "adapter_config": {
    "type": "BigWigAdapter"
  },
  
  "metadata": {
    "source": "TopHat/Cufflinks pipeline",
    "sequencer": "Illumina HiSeq",
    "coverage_type": "log2(FPKM)",
    "url": "https://..."
  }
}
```

### 2. Admin-Only DNA Alignment (BAM)

```json
// /moop/metadata/jbrowse2-configs/tracks/dna_whole_genome_seq.json

{
  "name": "Whole Genome Sequencing",
  "description": "High-coverage whole genome sequencing alignment (30x)",
  "track_id": "dna_whole_genome_seq",
  "type": "alignment",
  "access_levels": ["ALL"],  // Only admins
  "groups": ["Sequencing", "Raw Data"],
  "file_format": "bam",
  "file_template": "{organism}_{assembly}_wgs_30x.bam",
  
  "color": "#2ca02c",
  
  "display_config": {
    "type": "LinearAlignmentsDisplay",
    "defaultRendering": "svg",
    "height": 200,
    "maxHeight": 500
  },
  
  "adapter_config": {
    "type": "BamAdapter"
  },
  
  "metadata": {
    "source": "Illumina sequencing",
    "coverage_depth": "30x",
    "mapper": "bwa-mem",
    "raw_data_url": "ftp://ncbi-sra/..."
  }
}
```

### 3. Collaborator-Only ChIP-seq (BigWig)

```json
// /moop/metadata/jbrowse2-configs/tracks/histone_h3k4me3.json

{
  "name": "H3K4me3 ChIP-seq",
  "description": "Chromatin immunoprecipitation sequencing for H3K4me3 mark",
  "track_id": "histone_h3k4me3",
  "type": "quantitative",
  "access_levels": ["Collaborator", "ALL"],  // Not public
  "groups": ["Epigenetics", "ChIP-seq"],
  "file_format": "bigwig",
  "file_template": "{organism}_{assembly}_h3k4me3.bw",
  
  "color": "#ff7f0e",
  
  "display_config": {
    "type": "WiggleYScaleQuantitativeTrack",
    "defaultRendering": "density",
    "height": 120,
    "scale": "linear"
  },
  
  "adapter_config": {
    "type": "BigWigAdapter"
  },
  
  "metadata": {
    "source": "ChIP-seq protocol v3",
    "antibody": "Abcam ab4729",
    "cell_type": "Root meristem",
    "replicate_count": 2
  }
}
```

### 4. Feature Track (GFF Annotations)

```json
// /moop/metadata/jbrowse2-configs/tracks/annotations_refseq.json

{
  "name": "RefSeq Annotations",
  "description": "Gene annotations from RefSeq (NCBI curated)",
  "track_id": "annotations_refseq",
  "type": "feature",
  "access_levels": ["Public"],
  "groups": ["Annotations", "Reference"],
  "file_format": "gff3",
  "file_template": "{organism}_{assembly}_refseq.gff3.gz",
  
  "color": "#d62728",
  
  "display_config": {
    "type": "LinearBigRseqDisplay",
    "defaultRendering": "svg",
    "height": 150,
    "style": {
      "featureCss": "fill: lightblue; stroke: blue"
    }
  },
  
  "adapter_config": {
    "type": "GffAdapter"
  },
  
  "metadata": {
    "source": "RefSeq, NCBI",
    "feature_types": ["gene", "mRNA", "CDS", "exon"],
    "version": "GRCh38.p14"
  }
}
```

### 5. Variant Track (VCF)

```json
// /moop/metadata/jbrowse2-configs/tracks/snp_calls_population.json

{
  "name": "SNP Calls (Population)",
  "description": "Variant calls from population whole-genome sequencing",
  "track_id": "snp_calls_population",
  "type": "variant",
  "access_levels": ["Public"],
  "groups": ["Variants", "Population"],
  "file_format": "vcf",
  "file_template": "{organism}_{assembly}_snps.vcf.gz",
  
  "color": "#9467bd",
  
  "display_config": {
    "type": "LinearVariantDisplay",
    "defaultRendering": "svg"
  },
  
  "adapter_config": {
    "type": "VcfTabixAdapter"
  },
  
  "metadata": {
    "source": "1000 Genomes Project",
    "variant_callers": ["GATK", "bcftools"],
    "filters": "PASS"
  }
}
```

---

## Access Level Reference

### Public
- Visible to: Everyone (logged in or anonymous)
- Use for: Published reference data, basic annotations
- Examples: Gene annotations, published expression data

### Collaborator
- Visible to: Users with explicit access to organism-assembly
- Requires: User has `organism` → `[assembly]` in `$_SESSION['access']`
- Use for: Pre-publication data, collaborative projects
- Examples: Draft ChIP-seq, unpublished RNA-seq

### ALL
- Visible to: Administrators only
- Requires: `$_SESSION['access_level'] === 'ALL'`
- Use for: Raw data, internal processing results, sensitive data
- Examples: Raw BAM files, proprietary annotations

---

## Common File Formats & Adapter Types

| Format | File Extension | Adapter Type | Notes |
|--------|---|---|---|
| BigWig | .bw | BigWigAdapter | Range request compatible ✓ |
| BAM | .bam | BamAdapter | Requires .bai index; range compatible ✓ |
| GFF3 | .gff3.gz | GffAdapter | Must be sorted and tabix-indexed (.tbi) |
| VCF | .vcf.gz | VcfTabixAdapter | Must be tabix-indexed (.tbi) |
| FASTA | .fasta | IndexedFastaAdapter | Must have .fai index |
| SAM | .sam | SamAdapter | Large files; use BAM instead |

---

## Creating New Track Configs

### Process:

1. **Plan the track**
   - What data does it show?
   - Who should access it? (Public/Collaborator/ALL)
   - What format is the file? (BigWig/BAM/GFF/VCF)
   - What JBrowse2 display type fits best?

2. **Prepare the data file**
   ```bash
   # BigWig: ready to serve
   
   # BAM: index it
   samtools index file.bam  # Creates file.bam.bai
   
   # GFF3: sort, compress, index
   sort -k1,1 -k4,4n annotations.gff3 > annotations.sorted.gff3
   bgzip annotations.sorted.gff3
   tabix -p gff annotations.sorted.gff3.gz
   
   # VCF: compress and index
   bgzip variants.vcf  # Creates variants.vcf.gz
   tabix -p vcf variants.vcf.gz
   ```

3. **Create track config JSON**
   - Start from example above
   - Set name, description, track_id
   - Set file_template with placeholders
   - Set access_levels
   - Set appropriate adapter_config/display_config

4. **Copy file to tracks server**
   ```bash
   # Using file_template pattern: {organism}_{assembly}_{track_name}.ext
   # Example: Anoura_caudifer_GCA_004027475.1_rna_coverage.bw
   scp file.bw tracks@tracks.example.com:/var/tracks/data/bigwig/
   ```

5. **Save track config to MOOP**
   ```bash
   cp track_config.json /moop/metadata/jbrowse2-configs/tracks/
   ```

6. **Test**
   - Request assembly config: `curl /api/jbrowse2/assembly?organism=...&assembly=...`
   - Verify track appears in JSON
   - Load in JBrowse2, verify track shows

---

## Updating Existing Track

If you need to update a track (new data, new metadata):

```bash
# 1. Upload new file to tracks server
scp new_file.bw tracks@tracks.example.com:/var/tracks/data/bigwig/

# 2. Update JSON config (if metadata changed)
vim /moop/metadata/jbrowse2-configs/tracks/track_id.json

# 3. Git commit (version control)
git add metadata/jbrowse2-configs/tracks/track_id.json
git commit -m "Update track_id: improved pipeline, new data"

# 4. Done - no regeneration needed
# Users get updated config automatically on next request
```

---

## Organizing Tracks by Category

Use the `groups` field to organize visually in JBrowse2:

```json
{
  "groups": ["Expression", "Time Series"]
}
```

JBrowse2 will hierarchically organize:
```
├── Expression
│   ├── RNA-seq Coverage
│   └── Microarray
└── Time Series
    ├── Hour 0
    ├── Hour 6
    └── Hour 24
```

Recommended group structure:
```
Expression
  ├── RNA-seq
  ├── Microarray
  └── In situ

Epigenetics
  ├── ChIP-seq
  ├── ATAC-seq
  └── DNase-seq

Sequencing
  ├── Raw Data
  ├── Alignments
  └── Variants

Reference
  ├── Annotations
  ├── Repeats
  └── Conservation
```

---

## Best Practices

1. **Use consistent file naming**: `{organism}_{assembly}_{track_name}.ext`
2. **Keep track_id lowercase**: `rna_seq_coverage` not `RNA-seq Coverage`
3. **Version tracks**: If you update data significantly, create new track instead of replacing
   - `rna_seq_coverage_v1.json`
   - `rna_seq_coverage_v2.json` (new pipeline)
4. **Document metadata**: Include source, methods, URLs in metadata section
5. **Test access levels**: Verify Public/Collaborator/ALL filtering works
6. **Use meaningful colors**: Pick colors that distinguish related tracks
7. **Set appropriate heights**: Alignments need more space than quantitative tracks

