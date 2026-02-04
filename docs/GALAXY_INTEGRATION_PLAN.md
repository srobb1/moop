# Galaxy Integration Plan for MOOP

## Overview
MOOP will integrate with **UseGalaxy.org** for running bioinformatics tools without needing local installations.

## Implementation Status

### Phase 1: Infrastructure & Configuration ✅
- **Created Galaxy library structure:**
  - `/lib/galaxy/index.php` - Main loader
  - `/lib/galaxy/client.php` - Galaxy API client wrapper
  - `/lib/galaxy/mafft.php` - MAFFT tool integration
  
- **Created API endpoint:**
  - `/api/galaxy/mafft.php` - Handles MAFFT requests

- **Configuration:**
  - API key stored in `/config/secrets.php`
  - Galaxy settings in `/config/site_config.php`
  - Supports enable/disable toggle

### Phase 2: API Key & Authentication 🔄 IN PROGRESS

**Current Issue:** API key `572d0be92e585b105efea8879b73fe86` works for:
- ✅ Reading current user status (`/api/users/current`)
- ✅ Listing existing histories (read-only)
- ❌ Creating new histories (403 - permission denied)
- ❌ Uploading files (not tested yet)
- ❌ Running jobs (not tested yet)

**Possible Causes:**
1. Account needs email verification on UseGalaxy.org
2. API key needs elevated permissions (may require clicking "API Key" in user settings)
3. Account is new and needs approval
4. UseGalaxy.org has special restrictions on shared/test accounts

**Next Steps:**
1. Verify the Galaxy account in browser:
   - Log in to https://usegalaxy.org
   - Go to User > Preferences > Manage API Key
   - Verify API key matches `572d0be92e585b105efea8879b73fe86`
   - Check if email is verified
   
2. If permissions are still restricted, consider:
   - Using a different Galaxy instance (galaxy.pasteur.fr, genomics.usegalaxy.eu, etc.)
   - Requesting elevated permissions from Galaxy administrators
   - Using Galaxy's OAuth/login flow instead of API key

3. Test alternatives:
   - Try with `galaxy.usegalaxy.eu` instead of `usegalaxy.org`
   - Create a new account and verify email first
   - Check Galaxy API documentation for permission requirements

## Architecture

### Galaxy Client Wrapper (`lib/galaxy/client.php`)
```php
$client = new GalaxyClient($url, $apiKey, $mode);

// Core methods:
$client->testConnection()              // Verify API connectivity
$client->createHistory($userId, $name) // Create analysis history
$client->uploadFile($historyId, $file) // Upload FASTA file
$client->runTool($historyId, $toolId, $inputs) // Execute tool
$client->getJobStatus($jobId)          // Check progress
$client->getDatasetContent($outputId)  // Download results
```

### MAFFT Tool Wrapper (`lib/galaxy/mafft.php`)
```php
$mafft = new MAFFTTool($galaxyConfig);

$result = $mafft->align($userId, $sequences, $options);
// Returns: ['success' => bool, 'job_id' => string, 'history_id' => string]

$result = $mafft->getResults($outputId);
// Returns: ['success' => bool, 'alignment' => string]
```

### API Endpoint (`api/galaxy/mafft.php`)
```
POST /api/galaxy/mafft.php
Content-Type: application/json

{
    "sequences": [
        { "id": "seq1", "header": "...", "sequence": "..." },
        { "id": "seq2", "header": "...", "sequence": "..." }
    ],
    "options": { "method": "auto" }
}

Response:
{
    "success": true,
    "job_id": "...",
    "history_id": "...",
    "output_id": "..."
}
```

## Tools Planned for Integration

1. **MAFFT** (Multiple Sequence Alignment)
   - Tool ID: `toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.1`
   - Category: Alignment
   - Status: Implementation in progress

2. **ClustalW** (Alternative alignment)
   - Tool ID: `toolshed.g2.bx.psu.edu/repos/devteam/clustalw/clustalw/2.1+galaxy1`
   - Category: Alignment
   - Status: Planned

3. **RAxML** (Phylogenetic inference)
   - Tool ID: `toolshed.g2.bx.psu.edu/repos/iuc/raxml/raxml/8.2.12+galaxy0`
   - Category: Phylogenetics
   - Status: Planned

## User Interface Integration

### Search Results Page
- Add checkbox selection for sequences across species
- "Align Selected Sequences" button appears when 2+ sequences selected
- Opens modal with:
  - Tool selection (MAFFT, ClustalW, etc.)
  - Algorithm options
  - Submit button

### Analysis Results Display
- Live job status updates (polling)
- Download alignment in multiple formats
- Integration with visualization tools

## Security Considerations

- API key stored in gitignored `secrets.php` file
- Per-user histories on Galaxy (track which MOOP user initiated analysis)
- No credentials exposed in API responses
- Future: Per-user Galaxy accounts (Phase 3)

## Testing

Run the test script:
```bash
cd /data/moop
php test_galaxy_api.php
```

Expected output:
1. ✅ Sequences submitted to Galaxy
2. ✅ Job polling (waits for completion)
3. ✅ Results retrieved and displayed

## Future Enhancements

- **Phase 3:** Per-user Galaxy accounts (users create own Galaxy accounts)
- **Phase 4:** Results caching and analysis history in MOOP
- **Phase 5:** Integration with other bioinformatics workflows
- **Phase 6:** Support for multiple Galaxy instances (user selects preferred server)

## References

- Galaxy API Docs: https://docs.galaxyproject.org/en/latest/api_doc.html
- UseGalaxy.org: https://usegalaxy.org
- MAFFT: https://mafft.cbrc.jp/
