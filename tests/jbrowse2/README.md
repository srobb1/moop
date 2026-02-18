# JBrowse2 Testing Files

This directory contains testing and development files for JBrowse2 integration.

## Test Files

### jbrowse2-test.php
Comprehensive test page for JBrowse2 assembly and track loading.

**Features:**
- Tests multiple assemblies
- Validates API responses
- Checks track rendering
- Tests authentication scenarios

**Usage:**
```bash
# Via web server
http://yourserver/moop/tests/jbrowse2/jbrowse2-test.php

# Or via PHP dev server
cd /data/moop
php -S localhost:8000
```

### jbrowse2-test-local.php
Specialized for local PHP dev server on port 8888.

**Usage:**
```bash
php -S localhost:8888
# Then open: http://localhost:8888/jbrowse2-test-local.php
```

### jbrowse2-test-ssh.php
For testing via SSH tunnel.

**Usage:**
```bash
# On remote server
php -S localhost:8888

# On local machine
ssh -L 8000:localhost:8888 user@remoteserver

# Then open: http://localhost:8000/jbrowse2-test-ssh.php
```

### jbrowse2-dynamic.html
Standalone HTML test page (no PHP backend needed).

**Features:**
- Tests JBrowse2 React component directly
- Dynamic config loading via API
- No MOOP integration (testing API in isolation)

**Usage:**
Open directly in browser or serve via any web server.

---

## Test Results

### jbrowse2_test_results.log
Output from test runs (February 5, 2026).

**Contents:**
- Directory structure verification
- API endpoint checks
- Track file availability
- JWT token generation tests

---

## Production Files (Not in This Directory)

The actual production JBrowse2 implementation is:
- **Entry point:** `/moop/jbrowse2.php`
- **API:** `/moop/api/jbrowse2/config.php`
- **Tracks server:** `/moop/api/jbrowse2/tracks.php`
- **React build:** `/moop/jbrowse2/` (static assets)

---

## Running Tests

### Quick Test
```bash
# Check if assemblies load
curl http://localhost/moop/api/jbrowse2/config.php | jq '.assemblies | length'

# Check if specific assembly config works
curl "http://localhost/moop/api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1" | jq '.tracks | length'
```

### Full Test Suite
Open `jbrowse2-test.php` in browser and click "Run All Tests"

---

**Created:** February 2026  
**Purpose:** Development and testing files for JBrowse2 integration  
**Status:** Testing utilities (not production code)
