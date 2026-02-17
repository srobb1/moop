# Track ID Generation Strategies for Google Sheets

**Purpose:** Generate reproducible, non-revealing track IDs for JBrowse2 tracks to prevent information disclosure in shared sessions.

**Requirements:**
- ✅ Reproducible (same input → same output)
- ✅ No sequential numbering
- ✅ Hide sensitive information (sample IDs, experiment codes)
- ✅ Deterministic from file path/metadata
- ❌ No sequential counters

**Date:** 2026-02-17

---

## Strategy 1: Hash-Based IDs (Recommended)

### Concept
Use a hash function (MD5, SHA256) to create a unique, reproducible ID from the file path.

### Google Sheets Formula

```javascript
=CONCATENATE("track_", LEFT(MD5(A2), 10))
```

Where A2 contains the file path.

**Example:**
```
Input:  /path/to/MOLNG-2707_S3-body-wall.bam
Output: track_a7f3d2e942
```

### Pros:
- ✅ Completely reproducible
- ✅ Reveals nothing about original filename
- ✅ Collision-resistant
- ✅ Short and manageable

### Cons:
- ❌ Not human-readable
- ❌ Can't infer content from ID
- ❌ Requires MD5 custom function in Google Sheets

### Implementation (Google Apps Script):

```javascript
/**
 * Generate hash-based track ID
 * @param {string} filePath - Full file path
 * @return {string} Track ID like "track_a7f3d2e942"
 * @customfunction
 */
function generateHashTrackId(filePath) {
  if (!filePath) return "";
  
  // Create MD5 hash
  var hash = Utilities.computeDigest(
    Utilities.DigestAlgorithm.MD5, 
    filePath,
    Utilities.Charset.UTF_8
  );
  
  // Convert to hex string
  var hashString = hash.map(function(byte) {
    var v = (byte < 0) ? 256 + byte : byte;
    return ("0" + v.toString(16)).slice(-2);
  }).join("");
  
  // Take first 10 characters
  return "track_" + hashString.substring(0, 10);
}
```

**Usage in Sheet:**
```
=generateHashTrackId(A2)
```

---

## Strategy 2: Encoded Metadata Hash (Semi-Readable)

### Concept
Combine metadata into a meaningful prefix, then hash the file path for uniqueness.

### Google Apps Script:

```javascript
/**
 * Generate encoded metadata track ID
 * @param {string} filePath - Full file path
 * @param {string} technique - Technique (RNASeq, WGS, etc)
 * @param {string} tissue - Tissue type
 * @return {string} Track ID like "rnaseq_bodywall_a7f3d2e942"
 * @customfunction
 */
function generateEncodedTrackId(filePath, technique, tissue) {
  if (!filePath) return "";
  
  // Normalize metadata
  var techShort = normalizeTechnique(technique);
  var tissueShort = normalizeTissue(tissue);
  
  // Hash the file path
  var hash = Utilities.computeDigest(
    Utilities.DigestAlgorithm.MD5, 
    filePath,
    Utilities.Charset.UTF_8
  );
  
  var hashString = hash.map(function(byte) {
    var v = (byte < 0) ? 256 + byte : byte;
    return ("0" + v.toString(16)).slice(-2);
  }).join("");
  
  // Combine: technique_tissue_hash
  return techShort + "_" + tissueShort + "_" + hashString.substring(0, 10);
}

/**
 * Normalize technique names
 */
function normalizeTechnique(technique) {
  if (!technique) return "track";
  
  var mapping = {
    "RNASeq": "rnaseq",
    "ChIPSeq": "chipseq",
    "ATACSeq": "atacseq",
    "WGS": "wgs",
    "Sequence": "seq",
    "Hi-C": "hic"
  };
  
  return mapping[technique] || technique.toLowerCase().replace(/[^a-z0-9]/g, "");
}

/**
 * Normalize tissue names
 */
function normalizeTissue(tissue) {
  if (!tissue) return "unknown";
  
  // Remove spaces, special characters, lowercase
  return tissue.toLowerCase()
              .replace(/[^a-z0-9]/g, "")
              .substring(0, 12); // Limit length
}
```

**Example:**
```
Input:  filePath="/path/MOLNG-2707_S3-body-wall.bam"
        technique="RNASeq"
        tissue="body_wall"
Output: rnaseq_bodywall_a7f3d2e942
```

**Usage in Sheet:**
```
=generateEncodedTrackId(A2, B2, C2)
```

### Pros:
- ✅ Reproducible
- ✅ Semi-readable (shows technique/tissue)
- ✅ Hides sample IDs
- ✅ Still unique via hash

### Cons:
- ❌ Reveals technique and tissue type
- ❌ Longer IDs

---

## Strategy 3: Base64-Encoded Path Hash (Compact)

### Concept
Hash the file path and encode in Base64 for shorter IDs.

### Google Apps Script:

```javascript
/**
 * Generate compact Base64 track ID
 * @param {string} filePath - Full file path
 * @return {string} Track ID like "track_p7Y3mQrB"
 * @customfunction
 */
function generateCompactTrackId(filePath) {
  if (!filePath) return "";
  
  // Create MD5 hash
  var hash = Utilities.computeDigest(
    Utilities.DigestAlgorithm.MD5, 
    filePath,
    Utilities.Charset.UTF_8
  );
  
  // Convert to Base64
  var base64 = Utilities.base64Encode(hash);
  
  // Take first 8 characters, remove special chars
  var cleanId = base64.substring(0, 8)
                      .replace(/\+/g, 'a')
                      .replace(/\//g, 'b')
                      .replace(/=/g, '');
  
  return "track_" + cleanId;
}
```

**Example:**
```
Input:  /path/to/MOLNG-2707_S3-body-wall.bam
Output: track_p7Y3mQrB
```

### Pros:
- ✅ Very compact
- ✅ Reproducible
- ✅ URL-safe
- ✅ Reveals nothing

### Cons:
- ❌ Not human-readable
- ❌ Looks random

---

## Strategy 4: Semantic Hash (Metadata + Path Hash)

### Concept
Create semantic categories, then use hash for uniqueness within category.

### Google Apps Script:

```javascript
/**
 * Generate semantic hash track ID
 * @param {string} filePath - Full file path
 * @param {string} trackType - Track type (alignments, coverage, annotations)
 * @param {string} organism - Organism name
 * @return {string} Track ID like "nv_alignments_a7f3d2e9"
 * @customfunction
 */
function generateSemanticTrackId(filePath, trackType, organism) {
  if (!filePath) return "";
  
  // Organism abbreviation (first 2 chars of each word)
  var orgParts = organism.split('_');
  var orgAbbrev = "";
  for (var i = 0; i < Math.min(2, orgParts.length); i++) {
    orgAbbrev += orgParts[i].substring(0, 2).toLowerCase();
  }
  
  // Track type mapping
  var typeMap = {
    "bam": "alignments",
    "cram": "alignments",
    "bigwig": "coverage",
    "bw": "coverage",
    "gff": "annotations",
    "gtf": "annotations",
    "bed": "features",
    "vcf": "variants",
    "maf": "maf"
  };
  
  var trackCategory = typeMap[trackType] || "track";
  
  // Hash file path
  var hash = Utilities.computeDigest(
    Utilities.DigestAlgorithm.MD5, 
    filePath,
    Utilities.Charset.UTF_8
  );
  
  var hashString = hash.map(function(byte) {
    var v = (byte < 0) ? 256 + byte : byte;
    return ("0" + v.toString(16)).slice(-2);
  }).join("");
  
  return orgAbbrev + "_" + trackCategory + "_" + hashString.substring(0, 8);
}
```

**Example:**
```
Input:  filePath="/path/MOLNG-2707_S3-body-wall.bam"
        trackType="bam"
        organism="Nematostella_vectensis"
Output: neve_alignments_a7f3d2e9
```

### Pros:
- ✅ Human-readable structure
- ✅ Reproducible
- ✅ Organized by organism and type
- ✅ Hides sensitive sample info

### Cons:
- ❌ Longer IDs
- ❌ Reveals organism and track type

---

## Strategy 5: Hierarchical Hash (Recommended for Large Datasets)

### Concept
Create a hierarchy: organism/assembly/type, then hash for uniqueness.

### Google Apps Script:

```javascript
/**
 * Generate hierarchical hash track ID
 * @param {string} filePath - Full file path
 * @param {string} organism - Organism name
 * @param {string} assembly - Assembly ID
 * @param {string} fileType - File type (bam, bigwig, etc)
 * @return {string} Track ID like "nv_gca033_bam_a7f3d2e9"
 * @customfunction
 */
function generateHierarchicalTrackId(filePath, organism, assembly, fileType) {
  if (!filePath) return "";
  
  // Organism abbreviation
  var orgParts = organism.split('_');
  var orgAbbrev = "";
  for (var i = 0; i < Math.min(2, orgParts.length); i++) {
    orgAbbrev += orgParts[i].substring(0, 2).toLowerCase();
  }
  
  // Assembly abbreviation (extract GCA/GCF number)
  var assemblyMatch = assembly.match(/(GCA|GCF)_(\d{3})/);
  var assemblyAbbrev = assemblyMatch ? 
    assemblyMatch[1].toLowerCase() + assemblyMatch[2] : 
    assembly.substring(0, 6).toLowerCase();
  
  // File type
  var typeClean = fileType.toLowerCase().replace(/[^a-z0-9]/g, '');
  
  // Hash file path
  var hash = Utilities.computeDigest(
    Utilities.DigestAlgorithm.MD5, 
    filePath,
    Utilities.Charset.UTF_8
  );
  
  var hashString = hash.map(function(byte) {
    var v = (byte < 0) ? 256 + byte : byte;
    return ("0" + v.toString(16)).slice(-2);
  }).join("");
  
  return orgAbbrev + "_" + assemblyAbbrev + "_" + typeClean + "_" + hashString.substring(0, 8);
}
```

**Example:**
```
Input:  filePath="/path/MOLNG-2707_S3-body-wall.bam"
        organism="Nematostella_vectensis"
        assembly="GCA_033964005.1"
        fileType="bam"
Output: neve_gca033_bam_a7f3d2e9
```

### Pros:
- ✅ Organized and browsable
- ✅ Reproducible
- ✅ Shows context (organism/assembly/type)
- ✅ Hides sensitive sample/experiment info
- ✅ Works well with many tracks

### Cons:
- ❌ Longer IDs
- ❌ Reveals organism/assembly/type (but that's usually public anyway)

---

## Comparison Table

| Strategy | Example ID | Length | Readable | Reveals | Best For |
|----------|-----------|--------|----------|---------|----------|
| Hash-Based | `track_a7f3d2e942` | Short | No | Nothing | Maximum privacy |
| Encoded Metadata | `rnaseq_bodywall_a7f3d2e942` | Medium | Partial | Technique/tissue | Moderate privacy |
| Compact Base64 | `track_p7Y3mQrB` | Very Short | No | Nothing | Space-constrained |
| Semantic Hash | `neve_alignments_a7f3d2e9` | Medium | Yes | Organism/type | Organized datasets |
| Hierarchical Hash | `neve_gca033_bam_a7f3d2e9` | Long | Yes | Organism/assembly/type | Large multi-organism datasets |

---

## Recommended Approach for MOOP

### **Option: Hierarchical Hash** (Strategy 5)

**Reasoning:**
- Organism and assembly are already public (in assembly list)
- Track type helps users understand what they're looking at
- Hash portion completely hides sample IDs and experiment codes
- Still reproducible and manageable

**Implementation in Google Sheets:**

1. Add the `generateHierarchicalTrackId()` function to your Apps Script
2. In your track generation sheet, add column for Track ID:
   ```
   =generateHierarchicalTrackId(A2, B2, C2, D2)
   ```
   Where:
   - A2 = File path
   - B2 = Organism
   - C2 = Assembly
   - D2 = File type

3. Use this Track ID when generating track JSON files

**Result:**
- Shared sessions show: `neve_gca033_bam_a7f3d2e9`
- Users can't determine: Sample ID (MOLNG-2707), tissue, experiment details
- Admins can still: Look up track by ID in metadata, organize by type

---

## Testing Your Choice

After implementing, test by:

1. Generate track IDs for same file multiple times → Should be identical
2. Generate track IDs for different files → Should be different
3. Share a session and inspect JSON → Track IDs should not reveal sensitive info
4. Check if you can map track ID back to file using your metadata

---

## Migration Plan

If you want to change existing track IDs:

1. **Generate new IDs** with chosen function
2. **Update track metadata JSON files** with new trackId
3. **Test** that tracks still load
4. **Clear browser cache** to force JBrowse2 to reload configs
5. **Regenerate any saved sessions** (old sessions will have old IDs)

---

**Last Updated:** 2026-02-17  
**Status:** Ready for implementation  
**Recommended:** Hierarchical Hash (Strategy 5)
