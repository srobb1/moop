# [DESIGN] Store Custom Column Headers for Annotation Sources

## Status
**Design Feature** - Not yet implemented. This issue documents a proposed design for storing custom column headers that map TSV imports to database fields. Feedback and discussion welcome before implementation.

## Problem

Different annotation sources use different column naming conventions:

**GO Annotations:**
```
IDGO_IDGO_DESCRIPTIONNAMESPACE
AT1G01010GO:0008150biological_processprocess
```

**DIAMOND BLASTP:**
```
qseqidsseqidstitleevalue
AT1G01010sp|P12345BRCA11.2e-45
```

**InterProScan:**
```
protein_accessionsignature_accessionsignature_descscore
AT1G01010PF000017tm_142.5
```

When we import these, we lose information about what each column represents. The web UI then displays generic labels ("Score", "Description") instead of meaningful context-specific titles ("Namespace", "E-Value", "Domain Score").

## Desired Behavior

After importing annotations with custom column headers, the web UI should display appropriate titles:

**For GO annotations:**
```
GO ID: GO:0008150
Description: biological_process
Namespace: process
```

**For DIAMOND results:**
```
UniProt Accession: sp|P12345
Gene Name: BRCA1
E-Value: 1.2e-45
```

## Proposed Solution: JSON Column in annotation_source

Add a single `column_map` column to the `annotation_source` table to store header metadata as JSON.

### Schema

```sql
ALTER TABLE annotation_source ADD COLUMN column_map TEXT;
```

### Data Structure

Store column mapping as JSON for each annotation source:

```json
{
  "accession": {
    "tsv_header": "GO_ID",
    "display_title": "GO ID"
  },
  "description": {
    "tsv_header": "GO_DESCRIPTION", 
    "display_title": "GO Term"
  },
  "score": {
    "tsv_header": "NAMESPACE",
    "display_title": "Namespace"
  }
}
```

### Implementation Flow

1. **During import** - Parse TSV metadata headers to extract column mapping
2. **Store in DB** - JSON stored with annotation_source record
3. **Query with JSON** - SQLite `json_extract()` retrieves display titles
4. **Display in UI** - Use dynamic titles instead of hardcoded labels

### Example Database Query

```sql
SELECT 
  a.annotation_accession,
  a.annotation_description,
  fa.score,
  json_extract(ans.column_map, '$.accession.display_title') as accession_title,
  json_extract(ans.column_map, '$.description.display_title') as desc_title,
  json_extract(ans.column_map, '$.score.display_title') as score_title
FROM feature_annotation fa
JOIN annotation a ON fa.annotation_id = a.annotation_id
JOIN annotation_source ans ON a.annotation_source_id = ans.annotation_source_id
WHERE ans.annotation_source_id = ?;
```

## Design Rationale

**Why JSON instead of separate table?**
- Minimal schema change (1 column vs new table)
- Each source/version has independent mapping
- SQLite has native JSON support
- Avoids complex joins for common queries
- Flexible for future extensions

**Why this approach vs configuration file?**
- Data stored with database (versioned)
- Single source of truth
- Easier to query and migrate
- No sync needed between config and DB

## Benefits

- More informative UI labels
- Preserves annotation source metadata
- Supports diverse annotation formats
- Backward compatible (NULL = use defaults)
- Easy to extend with more metadata later

## Questions for Discussion

1. Should `column_map` be required or optional?
2. What are good default titles if `column_map` is NULL?
3. Should we validate the JSON structure?
4. Any other metadata we should store per source/version?
5. How should this integrate with the current loader scripts?

## Example Annotation Source Records

**GO 2024:**
```
annotation_source_id: 1
annotation_source_name: GO
annotation_source_version: 2024-01
annotation_type: GO
column_map: {
  "accession": {"tsv_header": "GO_ID", "display_title": "GO ID"},
  "description": {"tsv_header": "GO_DESCRIPTION", "display_title": "GO Term"},
  "score": {"tsv_header": "NAMESPACE", "display_title": "Namespace"}
}
```

**DIAMOND 2.1.8:**
```
annotation_source_id: 2
annotation_source_name: DIAMOND
annotation_source_version: 2.1.8
annotation_type: BLASTP
column_map: {
  "accession": {"tsv_header": "sseqid", "display_title": "Subject ID"},
  "description": {"tsv_header": "stitle", "display_title": "Subject Title"},
  "score": {"tsv_header": "evalue", "display_title": "E-Value"}
}
```

---

**Next Steps:** Gather feedback on this design before implementation. Consider:
- [ ] Schema implications
- [ ] Implementation complexity
- [ ] UI integration approach
- [ ] Testing strategy
