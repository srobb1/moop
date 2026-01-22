# BLAST Search Tool - Quick Reference

## 🚀 Quick Start for Users

### Access the Tool
- Navigate to: `/moop/tools/blast.php`
- Or click "BLAST Search" from any page's Tools menu

### Basic Steps
1. **Select organism** from dropdown
2. **Select assembly** (auto-filters for selected organism)
3. **Paste sequence** (FASTA or raw DNA/protein)
4. **Choose BLAST program** (BLASTn, BLASTp, BLASTx, tBLASTn, tBLASTx)
5. **Click Search** button
6. **View results** displayed below

### Example

**Input:**
```
Organism: Homo sapiens
Assembly: GRCh38 (hg38)
Program: BLASTp
Sequence: MGHFDDRRGGYVASSDPDEQAEVERRL...
```

**Output:** Table with matches sorted by E-value

---

## 📊 Program Reference

| Program | Input Type | Database | Best For |
|---------|-----------|----------|----------|
| **BLASTn** | DNA sequence | Nucleotide | Finding similar DNA |
| **BLASTp** | Protein sequence | Protein | Finding similar proteins |
| **BLASTx** | DNA sequence (→protein) | Protein | Finding protein matches for gene |
| **tBLASTn** | Protein (→DNA) | Nucleotide | Finding genes for protein |
| **tBLASTx** | DNA (→DNA) | Nucleotide | Finding DNA matches with translation |

---

## 🔧 Advanced Options

When you need fine-tuning:

- **E-value:** Confidence threshold (default: 1e-6)
  - Higher = more results, lower quality (1e-3)
  - Lower = fewer results, higher quality (1e-100)

- **Max Hits:** Number of matches to show (default: 50)
  - Larger = more results, slower search
  - Smaller = faster search

- **Matrix:** Scoring system (for protein only)
  - BLOSUM62: General purpose (default)
  - BLOSUM80: Similar sequences
  - BLOSUM45: Distant sequences

- **Complexity Filter:** Remove low-complexity regions (default: yes)
  - Removes repetitive/simple sequences
  - Usually improves specificity

---

## 🔍 Common Use Cases

### Find homologous genes
```
Program: BLASTn
Input: Known gene sequence (DNA)
Result: Find similar genes in database
```

### Find protein family members
```
Program: BLASTp
Input: Protein sequence
Result: Find similar proteins across organisms
```

### Find gene for a protein
```
Program: tBLASTn
Input: Protein sequence
Result: Find genes that encode similar proteins
```

### Find protein-coding regions in DNA
```
Program: BLASTx
Input: DNA sequence
Result: Find where it matches known proteins
```

---

## 📁 File Structure

```
/data/moop/
├── tools/
│   ├── blast.php                    ← Controller
│   ├── pages/blast.php              ← View template
│   └── BLAST_*.md                   ← Documentation
│
└── lib/
    ├── blast_functions.php          ← Core functions
    └── blast_results_visualizer.php ← Result formatting
```

---

## 💻 For Developers

### Include Functions

```php
include_once __DIR__ . '/../lib/blast_functions.php';
```

### Get Databases

```php
$dbs = getBlastDatabases('/organism_data/Organism/Assembly');
```

### Filter by Program

```php
$compatible = filterDatabasesByProgram($dbs, 'blastp');
```

### Run Search

```php
$result = executeBlastSearch(
    $sequence,           // FASTA or raw
    '/path/to/database',
    'blastp',            // program name
    [                    // options
        'evalue' => '1e-6',
        'max_target_seqs' => 50
    ]
);
```

### Validate Sequence

```php
$valid = validateBlastSequence($user_input);
if (!$valid['success']) {
    echo "Error: " . $valid['message'];
}
```

---

## ⚡ Performance Tips

### Faster Searches
- Use higher E-value (less stringent)
- Reduce max hits
- Search smaller databases
- Use BLASTn (fastest)

### More Sensitive
- Use lower E-value (more stringent)
- Increase max hits
- Use appropriate program for sequence type

### Typical Times
- BLASTn on small DB: < 1 sec
- BLASTp on large DB: 10-30 sec
- tBLASTx on large DB: 30+ sec

---

## ❓ Troubleshooting

| Problem | Solution |
|---------|----------|
| "No databases found" | Databases not created for assembly |
| "Assembly not accessible" | No permission for that assembly |
| "Invalid sequence format" | Use FASTA or raw ACGT/amino acids |
| "BLAST search failed" | Check `/data/moop/logs/error.log` |
| "Empty results" | No matches found - try different E-value |

---

## 🔐 Security

- ✓ Access control enforced
- ✓ Input validation required
- ✓ Command injection prevention
- ✓ Errors don't expose system paths

---

## 📚 Full Documentation

For complete details, see `BLAST_TOOL_README.md` or `DEVELOPER_GUIDE.md`

---

**Last Updated:** January 2026
