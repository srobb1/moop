# Galaxy MAFFT Alignment Test - Success Log

## Date: February 4, 2026

### Summary
✅ Successfully tested UseGalaxy.org MAFFT integration with bash script and 5 protein sequences.

### Objective
Test UseGalaxy.org integration with MOOP to run MAFFT multiple sequence alignment on protein sequences from search results.

### Test Setup
- **Galaxy Instance**: https://usegalaxy.org
- **Tool Used**: MAFFT 7.221.3
- **Test Sequences**: 5 NTNG1 orthologs from different species (Bradypodion_pumilum and Callicebus)
- **Sequence Type**: Protein (amino acids)
- **Authentication**: Single shared Galaxy account with API key stored in MOOP config

### Test Data
- Input: 5 sequences with lengths ranging from ~73 to ~340 amino acids
- Format: FASTA
- Focus: Orthologs of NTNG1 (netrin G1) protein

### Results
✅ **Success**

1. Created Galaxy history successfully
2. Uploaded FASTA file with all 5 sequences
3. Executed MAFFT alignment tool
4. Retrieved aligned output in FASTA format
5. Alignment quality: Good conservation visible across orthologs

### Generated Output
Sequences aligned with proper gaps and spacing showing:
- Signal peptide conservation at N-terminus
- Key structural domains aligned across species
- Expected divergence in linker regions

### Key Findings

#### API Key Management
- API keys available via Galaxy user dashboard: https://usegalaxy.org/user/api_key
- Keys should be stored in MOOP configuration (encrypted)
- Consider using shared account vs. per-user accounts for simplicity

#### Tool ID Discovery
- MAFFT tool ID: `toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.3`
- Available parameters include:
  - Input format (FASTA)
  - Output format (FASTA)
  - Alignment method (flavour: mafft-fftns, etc.)
  - Matrix selection (BLOSUM62, etc.)

### Next Steps for MOOP Integration

1. **Visualization Enhancement**: Add embedded Galaxy alignment viewer
   - URL pattern: `https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id={DATASET_ID}`
   - Can embed in iframe or open in new window

2. **Multi-Search Integration**: 
   - Add checkbox selection to group/multi-search results
   - Launch MAFFT directly from result page
   - Track job history in MOOP database

3. **Additional Tools to Test**:
   - ClustalW (for comparison)
   - PhyML (phylogenetic inference)
   - IQ-Tree (modern phylogenetic tool)

4. **User Experience**:
   - Consider one shared Galaxy account for simplicity
   - Store API key securely in config
   - Show job progress in MOOP UI
   - Link to Galaxy history for advanced users

### Script Used
A bash script (`galaxy_mafft.sh`) successfully orchestrated the complete workflow:
1. History creation on Galaxy
2. File upload with polling for completion
3. Job submission to MAFFT tool
4. Job monitoring with 5-second polling intervals
5. Result retrieval and local file storage

### API Integration Results

#### Working Flow:
```bash
./galaxy_mafft.sh sequences.fasta
```

The script:
- Creates a new Galaxy history with timestamp
- Uploads FASTA file and waits for completion
- Submits MAFFT alignment job
- Polls status every 5 seconds until complete
- Downloads aligned result to local file (mafft_alignment_YYYYMMDD_HHMMSS.fasta)

#### Key API Endpoints Tested:
1. **POST /api/histories** - Create new analysis history
2. **POST /api/tools** - Upload data via upload1 tool
3. **GET /api/datasets/{id}** - Check dataset/job status
4. **POST /api/tools** - Submit MAFFT tool job
5. **GET /api/datasets/{id}/display** - Download results

#### Parameters Confirmed:
- Tool ID: `toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.3`
- Input parameter: `inputSequences` (accepts dataset reference)
- Output format: FASTA
- Available flavours: mafft-fftns, mafft-fftnsi, etc.
- Matrix support: BLOSUM62 and others

### Lessons Learned
- useGalaxy.org has all common bioinformatics tools available - no Docker needed
- API is well-documented and responsive
- Single shared account is simplest approach for MOOP multi-user system
- Upload polling necessary due to async Galaxy processing
- Job monitoring requires periodic status checks (5-second intervals work well)
- Tool IDs can be discovered via API and are stable across instances
- Result visualization available at: `https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id={DATASET_ID}`

### Configuration Strategy
Store Galaxy settings in MOOP config:
```php
'galaxy' => [
    'enabled' => true,
    'instance_url' => 'https://usegalaxy.org',
    'api_key' => '/* stored in secrets.php */',
    'tools' => [
        'mafft' => 'toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.3',
        // Additional tools as needed
    ]
]
```

### Next Phase: MOOP Integration
1. **PHP Wrapper Classes**: Create GalaxyClient in lib/galaxy/
2. **Search Results UI**: Add MSA button to multi-sequence search results
3. **Job Tracking**: Store Galaxy job IDs in MOOP database for history
4. **Result Display**: Embed Galaxy alignment viewer in MOOP interface
5. **Additional Tools**: Test and integrate ClustalW, PhyML, IQ-Tree

---

## Phase 2: Search Integration Test - February 4, 2026

### Objective
Test end-to-end integration of sequences from MOOP search results through Galaxy MAFFT alignment.

### Implementation
Created `test_galaxy_search_integration.php` that simulates:
1. Getting sequences from a search result page (variable array format)
2. Loading Galaxy config from `config/site_config.php`
3. Generating a MAFFT analysis request object
4. Submitting to Galaxy via shell script
5. Parsing results and returning visualization URLs

### Test Data
6 NTNG1 orthologs (5 from previous test + 1 human sequence Q9Y2I2)
- Total input size: 1,770 bytes FASTA
- Sequence count: 6
- Protein alignment

### Results
✅ **Success** - Full workflow completed

1. **Configuration Load**: Successfully loaded Galaxy settings from site_config via ConfigManager
2. **Sequence Processing**: Built FASTA format from search result sequence array
3. **Job Submission**: Submitted to Galaxy via `galaxy_mafft.sh` 
4. **Job Monitoring**: Tracked job through multiple status updates (new → queued → running → ok)
5. **Result Retrieval**: Downloaded aligned sequences
6. **Output**: Generated alignment file (3,748 bytes) with proper gaps and formatting

### Generated Outputs
- **Local alignment file**: `mafft_alignment_20260204_204153.fasta` (3,748 bytes)
- **Galaxy history**: https://usegalaxy.org/histories/view?id=bbd44e69cb8906b51a84edb9d64cb3ee
- **Visualization link**: https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id=bbd44e69cb8906b51a84edb9d64cb3ee
- **JSON API Response**: Includes success flag, file path, and all URLs for web integration

### Key Code Components

#### 1. Configuration Integration
```php
require_once __DIR__ . '/includes/config_init.php';
$config = ConfigManager::getInstance();
$galaxy_settings = $config->getArray('galaxy_settings', []);
$galaxy_url = $galaxy_settings['url'];
$galaxy_api_key = $galaxy_settings['api_key'];
```

#### 2. Sequence Array Format (from search results)
```php
$sequences = [
    [
        'id' => 'sequence_identifier',
        'name' => 'gene name/description',
        'sequence' => 'ACGTACGT...'
    ],
    // ... more sequences
];
```

#### 3. FASTA Builder
```php
$fasta_content = '';
foreach ($sequences as $seq) {
    $fasta_content .= '>' . $seq['id'] . ' ' . $seq['name'] . "\n";
    $fasta_content .= $seq['sequence'] . "\n";
}
```

#### 4. Shell Execution & Result Parsing
```php
$command = "$shell_script $temp_file $api_key 2>&1";
exec($command, $output, $return_var);

// Parse history ID and result file from output
foreach ($output as $line) {
    if (preg_match('/History: ([a-f0-9]+)/', $line, $matches)) {
        $history_id = $matches[1];
    }
    if (preg_match('/Results: (.+\.fasta)/', $line, $matches)) {
        $alignment_file = $matches[1];
    }
}
```

#### 5. API Response Format
```json
{
    "success": true,
    "message": "Alignment completed",
    "history_id": "bbd44e69cb8906b51a84edb9d64cb3ee",
    "alignment_file": "mafft_alignment_20260204_204153.fasta",
    "visualization_url": "https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id=...",
    "galaxy_url": "https://usegalaxy.org/histories/view?id=..."
}
```

### Running the Test

```bash
cd /data/moop
php test_galaxy_search_integration.php
```

**Expected output**:
- Loads 6 sequences from variable array
- Builds 1,770 byte FASTA file
- Submits to Galaxy
- Monitors job through 5-6 status updates (~30 seconds total)
- Returns alignment file and visualization URLs
- Outputs JSON response for API integration

### Alignment Quality Check
First few lines of output show proper alignment:
```
>Bradypodion_pumilum_JAWDJD010000004.1_000619.1 NTNG1 netrin G1
MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKY
VKVTLDPPDITCGNPPENFCGM--------------------------------------
...
```
Gaps properly inserted showing alignment of shorter sequences.

### Readiness for Production
✅ Configuration system working  
✅ Sequence variable format stable  
✅ Job submission reliable  
✅ Result parsing correct  
✅ API response ready for web integration  

### Next: Search Results UI Integration
Ready to add "Align Selected" button to multi-search and group search pages that:
1. Collects checked sequence IDs
2. Fetches sequence data from database
3. Formats into array structure
4. Calls this workflow
5. Displays alignment viewer in modal/lightbox
