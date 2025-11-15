# BLAST Tool - Quick Reference

## 🚀 Quick Start for Users

1. **Access the tool**: Click "BLAST Search" from any page's Tools section, or go to `/moop/tools/blast/index.php`
2. **Paste sequence**: Enter your DNA or protein sequence (FASTA format or raw)
3. **Choose program**: Select BLASTn, BLASTp, BLASTx, tBLASTn, or tBLASTx
4. **Select database**: Pick assembly → database automatically updates
5. **Search**: Click "Search" button to run

## 🔍 Quick Reference for Developers

### Using BLAST Functions

```php
include_once __DIR__ . '/tools/blast_functions.php';

// Get databases for an assembly
$dbs = getBlastDatabases('/path/to/assembly');

// Filter for program type
$compatible = filterDatabasesByProgram($dbs, 'blastp');

// Run search
$result = executeBlastSearch($seq, '/db/path', 'blastp', 
    ['evalue' => '1e-6', 'max_hits' => 50]);

// Extract sequences
$extract = extractSequencesFromBlastDb('/db/path', ['seq1', 'seq2']);

// Validate input
$valid = validateBlastSequence($user_input);
```

## 📊 Database Compatibility Matrix

| Program | Input Type | Database Type | File Extension |
|---------|-----------|---------------|----------------|
| BLASTn | DNA | Nucleotide | .nhr + .nin/.nal + .nsq |
| BLASTp | Protein | Protein | .phr + .pin/.pal + .psq |
| BLASTx | DNA (→Protein) | Protein | .phr + .pin/.pal + .psq |
| tBLASTn | Protein (→DNA) | Nucleotide | .nhr + .nin/.nal + .nsq |
| tBLASTx | DNA | Nucleotide | .nhr + .nin/.nal + .nsq |

## 🔧 Troubleshooting

### "No compatible databases found"
- Verify BLAST+ is installed: `which blastp`
- Check database files exist: `ls -l /path/to/organism/*/.*hr`
- Verify file permissions: `ls -l /path/to/*.nhr`

### "BLAST database not found"
- Ensure database basename matches: `/path/to/db.nhr`, `/path/to/db.nin`, etc.
- Check all required files exist together
- Don't include extension in database path

### "You do not have access to the selected assembly"
- Verify assembly is in your user's access list
- Contact administrator if you should have access

## 📁 File Structure

```
/moop/tools/
├── blast_functions.php          ← Core BLAST functions (NEW)
├── blast/
│   └── index.php               ← BLAST search interface (NEW)
├── extract/
│   ├── fasta_extract.php       ← Uses blast_functions.php
│   └── download_fasta.php      ← Uses blast_functions.php
├── display/
│   └── sequences_display.php   ← Uses blast_functions.php
├── tool_config.php             ← BLAST registered here
└── BLAST_TOOL_README.md        ← Full documentation
```

## 🎯 Key Features

- **Dynamic filtering**: Database list updates based on BLAST program
- **Access control**: Only shows accessible assemblies
- **Responsive UI**: Works on desktop and mobile
- **Advanced options**: E-value, matrix, hit count, complexity filter
- **Result download**: Export results as HTML file

## ⚡ Performance Tips

- Use higher e-value (less stringent) for quick screening
- Use lower e-value (more stringent) for focused searches
- Reduce max hits for faster results on large databases
- BLASTn is typically fastest, BLASTp slowest

## 🔒 Security Features

- User permissions respected
- SQL injection prevention (proper escaping)
- BASH command injection prevention (escapeshellarg)
- Input validation before execution
- Error messages don't expose system paths

## 📞 Support

For issues:
1. Check BLAST_TOOL_README.md for detailed docs
2. Verify database setup (see "Database Format Requirements")
3. Check server logs: `/var/log/apache2/error.log`
4. Verify BLAST+ installation: `blastp -version`

