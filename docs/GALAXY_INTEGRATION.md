# Galaxy Integration for MOOP

This document describes the integration of MOOP with UseGalaxy.org for running bioinformatics analyses without needing local tools.

## Overview

MOOP uses **UseGalaxy.org** (https://usegalaxy.org) as a backend for running multiple sequence alignment and other bioinformatics tools. This approach:

- ✅ No need to install/maintain local tools
- ✅ Access to hundreds of Galaxy tools
- ✅ Shared compute resources
- ✅ Built-in visualization and results management
- ✅ Free for research use

## Setup

### 1. Create a Galaxy Account

1. Go to https://usegalaxy.org
2. Click "Login or Register"
3. Create an account (free for research)
4. Verify your email

### 2. Get Your API Key

1. After logging in, go to https://usegalaxy.org/user/api_key
2. Copy your API key
3. Add it to `/data/moop/config/secrets.php`:

```php
return [
    'galaxy' => [
        'api_key' => 'YOUR_API_KEY_HERE',
    ],
];
```

⚠️ **IMPORTANT**: This file is in `.gitignore` and should NEVER be committed.

### 3. Verify Configuration

Edit `/data/moop/config/site_config.php` to ensure Galaxy settings are configured:

```php
'galaxy_settings' => [
    'enabled' => true,
    'url' => 'https://usegalaxy.org',
    'api_key' => $secrets['galaxy']['api_key'],
    'mode' => 'shared',
    'tools' => [
        'mafft' => [...],
        'clustalw' => [...],
        // More tools...
    ]
]
```

## Testing

### Test 1: Shell Script (Basic)

A simple shell script that tests Galaxy API with hardcoded sequences:

```bash
./run_mafft.sh sequences.fasta YOUR_API_KEY
```

This was our first successful test and demonstrates:
- Creating a Galaxy history
- Uploading a FASTA file
- Running MAFFT alignment
- Downloading results

**Output**: `mafft_alignment_TIMESTAMP.fasta`

### Test 2: PHP with Config Variables ✅ WORKING

A PHP test that uses stored config variables and simulated search result sequences:

```bash
cd /data/moop
php galaxy_testing/test_mafft_with_config.php
```

**Successfully tested on 2026-02-04**

This test demonstrates:
- ✅ Loading Galaxy config from `site_config.php`
- ✅ Connecting to UseGalaxy.org API
- ✅ Creating a history
- ✅ Uploading FASTA sequences
- ✅ Submitting MAFFT alignment job
- ✅ Retrieving job ID and output dataset ID
- ✅ Providing Galaxy URL to view results

**Features**:
- ✅ Uses stored configuration (not hardcoded)
- ✅ Simulates real search result format
- ✅ Full end-to-end workflow from config to job submission
- ✅ Provides Galaxy history and result links

## Available Tools

Tools configured in `site_config.php`:

| Tool | ID | Purpose |
|------|----|----|
| MAFFT | `toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.1` | Multiple sequence alignment |
| ClustalW | `toolshed.g2.bx.psu.edu/repos/devteam/clustalw/clustalw/2.1+galaxy1` | Multiple sequence alignment |
| BLAST | `toolshed.g2.bx.psu.edu/repos/devteam/ncbi_blast_plus/ncbi_blastp_wrapper/0.3.3` | Protein sequence search |
| RAxML | `toolshed.g2.bx.psu.edu/repos/iuc/raxml/raxml/8.2.12+galaxy0` | Phylogenetic analysis |

## API Workflow

### 1. Create History

```php
POST /api/histories
{
  "name": "My Analysis"
}
Response: { "id": "history_id", ... }
```

### 2. Upload File

```php
POST /api/tools
{
  "history_id": "history_id",
  "tool_id": "upload1",
  "inputs": {
    "files_0|url_paste": "FASTA_CONTENT",
    "file_type": "fasta"
  }
}
Response: { "outputs": [{"id": "dataset_id"}] }
```

### 3. Run Tool

```php
POST /api/tools
{
  "history_id": "history_id",
  "tool_id": "tool_id",
  "inputs": {
    "inputSequences": {"src": "hda", "id": "dataset_id"},
    ...
  }
}
Response: { "outputs": [{"id": "job_id"}] }
```

### 4. Monitor Progress

```php
GET /api/datasets/{job_id}
Response: { "state": "queued|running|ok|error" }
```

### 5. Download Results

```php
GET /api/datasets/{job_id}/display
Response: File content (FASTA, etc.)
```

### 6. Visualization

Access results and visualization in Galaxy:
- **History**: `https://usegalaxy.org/histories/view?id=history_id`
- **Alignment Viewer**: `https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id=job_id`

## Next Steps

### Phase 2: Web Interface Integration

1. **Search Results Tool**
   - Add checkboxes to search results
   - Button to "Align Selected Sequences"
   - Modal with MAFFT options

2. **Job Management**
   - Dashboard to view running/completed analyses
   - Link to Galaxy for full history
   - Download results directly from MOOP

3. **Results Visualization**
   - Embed Galaxy alignment viewer in MOOP
   - Display results in results table

### Phase 3: Additional Tools

- Phylogenetic analysis (RAxML)
- BLAST searches
- Sequence filtering and preprocessing

## Troubleshooting

### API Key Issues

```
ERROR: Provided API key is not valid
```

**Solution**: 
- Check that API key is correct: https://usegalaxy.org/user/api_key
- Ensure it's copied exactly (no whitespace)
- Regenerate key if needed

### Tool Not Found

```
ERROR: Tool toolshed.g2.bx.psu.edu/repos/... not found
```

**Solution**:
- Verify tool ID in Galaxy: https://usegalaxy.org/api/tools
- Some tools may be deprecated, use web interface to find latest ID
- Update `site_config.php` with correct ID

### Job Fails

Check Galaxy directly:
- Go to history: https://usegalaxy.org/histories/view?id=history_id
- Click on job and view error message
- Common issues: wrong sequence format, tool parameters

## References

- Galaxy API Docs: https://docs.galaxyproject.org/en/master/api_doc.html
- UseGalaxy.org: https://usegalaxy.org
- Tool Shed: https://toolshed.g2.bx.psu.edu/

## Files

- `/data/moop/config/site_config.php` - Tool configuration and Galaxy URL
- `/data/moop/config/secrets.php` - API key (NOT in git)
- `/data/moop/lib/galaxy/` - Galaxy PHP library (planned)
- `/data/moop/api/galaxy/` - Galaxy API endpoints (planned)
- `/data/moop/run_mafft.sh` - Shell script test
- `/data/moop/test_galaxy_with_config.php` - PHP config test
