# JBrowse2 Tracks Server Synchronization

## Overview

When deploying JBrowse2 with a remote tracks server, you need to keep reference genomes and track data synchronized between the MOOP server and the tracks server. This document explains what gets synced, why, and how to implement it.

## What Files Get Synced?

### THREE TYPES OF FILES

**TYPE 1: Reference Genomes (FASTA + GFF Annotations)**
- Location on MOOP: `/data/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/`
- Files:
  - `reference.fasta` (symlink to genome.fa from `/organisms/`)
  - `reference.fasta.fai` (FASTA index)
  - `annotations.gff3` (symlink to genomic.gff from `/organisms/`)
  - `annotations.gff3.gz` (compressed annotations)
  - `annotations.gff3.gz.tbi` (GFF index)
- Size: ~2.2 GB (FASTA) + ~500 MB (GFF compressed)
- What it is: The actual genome sequence and gene annotations
- Used by: JBrowse2 to display reference sequence and gene locations

**TYPE 2: Track Data (BigWig, BAM, etc.)**
- Location on MOOP: `/data/moop/data/tracks/`
- Files:
  - `bigwig/*.bw` (RNA-seq coverage, ChIP-seq, etc.)
  - `bam/*.bam` (DNA alignments)
  - `bam/*.bai` (BAM indices)
- Size: Variable, can be gigabytes
- What it is: Genomic tracks overlaid on the reference genome
- Used by: JBrowse2 to display track data in the browser

**TYPE 3: Security Keys (JWT)**
- Location on MOOP: `/data/moop/certs/`
- Files:
  - `jwt_private_key.pem` (secret, NEVER synced)
  - `jwt_public_key.pem` (public, gets synced)
- Size: Small (few KB)
- What it is: Cryptographic keys for token-based access control
- Used by: Remote server to validate that track requests are authorized

### WHAT GETS SYNCED TO REMOTE SERVER?

**All three should be synced:**
- ✅ `/data/genomes/` — Reference sequences + annotations
- ✅ `/data/tracks/` — All track data (BigWig, BAM, etc.)
- ✅ `jwt_public_key.pem` — For verifying access tokens

**What NEVER leaves MOOP:**
- ❌ `jwt_private_key.pem` — Secret key, always stays on MOOP only

### WHY BOTH GENOMES AND TRACKS?

Even though reference genomes are static and don't change:
1. **MOOP keeps them** for API use (building configs, generating tokens)
2. **Remote server gets them** so the remote server can serve them to the browser
3. **Browser loads from remote server** instead of MOOP, reducing MOOP load
4. **Track data also on remote server** so all genomic data is in one place

This way, the browser can fetch the complete dataset from the remote server with token validation, while MOOP focuses on authentication and configuration.

## Synchronization Architecture

When deploying JBrowse2 with a remote tracks server, you need to keep track data synchronized between the MOOP server and the tracks server. This document explains the synchronization options and how to implement them.

## Architecture Reminder

```
MOOP Server (moop.local):
  ├── /data/moop/data/genomes/        ← Reference genomes (KEPT for API use)
  │   └── Anoura_caudifer/GCA_004027475.1/
  │       ├── reference.fasta → symlink to /organisms/.../genome.fa
  │       ├── reference.fasta.fai (index)
  │       └── annotations.gff3.gz (compressed GFF)
  ├── /data/moop/data/tracks/         ← Track data (BigWig, BAM)
  ├── /api/jbrowse2/assembly.php      ← Generates JBrowse2 configs
  ├── /certs/jwt_private_key.pem      ← Keeps secret, never syncs
  └── /certs/jwt_public_key.pem       ← Syncs to remote server

Remote Tracks Server (tracks.example.com or local-remoteTest):
  ├── /data/genomes/                  ← SYNCED from MOOP
  │   └── Anoura_caudifer/GCA_004027475.1/
  │       ├── reference.fasta
  │       ├── reference.fasta.fai
  │       └── annotations.gff3.gz
  ├── /data/tracks/                   ← SYNCED from MOOP (BigWig, BAM, etc.)
  ├── /certs/jwt_public_key.pem       ← SYNCED from MOOP
  └── nginx/Apache serving all these files

Flow:
  Browser → loads JBrowse2 from http://moop.local/jbrowse2/
         → requests config from /api/jbrowse2/assembly.php (MOOP)
         → receives URLs pointing to remote server
         → loads reference genome from remote server (with JWT)
         → loads tracks from remote server (with JWT)
```

## Synchronization Methods

### 1. MANUAL (Default)

**What it is:**
- No automatic synchronization
- User manually copies/syncs files when needed
- Best for: Development, testing, small datasets

**How to use:**

```bash
# Copy reference genomes
rsync -av /data/moop/data/genomes/ user@tracks.example.com:/data/genomes/

# Copy track data
rsync -av /data/moop/data/tracks/ user@tracks.example.com:/data/tracks/

# Copy JWT public key
scp /data/moop/certs/jwt_public_key.pem user@tracks.example.com:/etc/jbrowse2/

# Verify on remote server
ssh user@tracks.example.com ls -la /data/genomes/ /data/tracks/
```

**Pros:**
- Simple, no automation overhead
- Full control over when sync happens
- Easy to test before committing

**Cons:**
- Easy to forget to sync
- Manual work for each organism added
- No verification that sync completed

**site_config setting:**
```php
'tracks_server' => [
    'enabled' => true,
    'url' => 'https://tracks.example.com/',
    'sync_method' => 'manual',
    'sync_schedule' => '',
]
```

### 2. RSYNC (Recommended for Production)

**What it is:**
- Automated one-way sync from MOOP → Tracks Server
- Only copies changed files (efficient)
- Can be scheduled with cron
- Best for: Production with regular data updates

**How to set up:**

**Step 1: Configure SSH key authentication (no password)**
```bash
# On MOOP server, generate SSH key if you don't have one
ssh-keygen -t rsa -b 4096 -f ~/.ssh/jbrowse2_sync

# Copy public key to tracks server
ssh-copy-id -i ~/.ssh/jbrowse2_sync.pub user@tracks.example.com

# Test connection
ssh -i ~/.ssh/jbrowse2_sync user@tracks.example.com "echo 'Connected!'"
```

**Step 2: Create sync script**
```bash
#!/bin/bash
# /data/moop/tools/sync_to_tracks_server.sh

REMOTE_USER="jbrowse"
REMOTE_HOST="tracks.example.com"
SSH_KEY="/home/www-data/.ssh/jbrowse2_sync"
LOG_FILE="/var/log/jbrowse2_sync.log"

echo "[$(date)] Starting sync to $REMOTE_HOST" >> $LOG_FILE

# Sync genomes
rsync -av --delete \
  -e "ssh -i $SSH_KEY" \
  /data/moop/data/genomes/ \
  ${REMOTE_USER}@${REMOTE_HOST}:/data/genomes/ >> $LOG_FILE 2>&1

# Sync tracks
rsync -av --delete \
  -e "ssh -i $SSH_KEY" \
  /data/moop/data/tracks/ \
  ${REMOTE_USER}@${REMOTE_HOST}:/data/tracks/ >> $LOG_FILE 2>&1

# Sync JWT public key
rsync -av \
  -e "ssh -i $SSH_KEY" \
  /data/moop/certs/jwt_public_key.pem \
  ${REMOTE_USER}@${REMOTE_HOST}:/etc/jbrowse2/ >> $LOG_FILE 2>&1

echo "[$(date)] Sync completed" >> $LOG_FILE
```

**Step 3: Schedule with cron**
```bash
# Run sync every day at 2 AM
0 2 * * * /data/moop/tools/sync_to_tracks_server.sh

# Or run every 6 hours
0 */6 * * * /data/moop/tools/sync_to_tracks_server.sh

# Or run every time new data is added (manual trigger)
# Just call the script: /data/moop/tools/sync_to_tracks_server.sh
```

**Pros:**
- Efficient (only syncs changes)
- Automated, no manual work
- Can be scheduled to off-peak hours
- Can log success/failure
- Supports --delete to remove old files

**Cons:**
- Requires SSH key setup
- Network bandwidth for initial sync
- Potential for out-of-sync state if cron fails

**site_config setting:**
```php
'tracks_server' => [
    'enabled' => true,
    'url' => 'https://tracks.example.com/',
    'sync_method' => 'rsync',
    'sync_schedule' => 'daily',  // or 'hourly', '6hourly', 'manual'
]
```

### 3. NFS (Shared Network Storage)

**What it is:**
- Reference genomes and track data mounted via NFS from MOOP server
- Real-time, always in sync
- Best for: High-performance setups, frequent updates

**How to set up:**

**On MOOP server:**
```bash
# Install NFS server
sudo apt-get install nfs-kernel-server

# Edit /etc/exports
sudo nano /etc/exports

# Add these lines:
/data/moop/data/genomes 192.168.1.100(ro,sync,no_subtree_check)
/data/moop/data/tracks 192.168.1.100(ro,sync,no_subtree_check)

# Apply changes
sudo exportfs -ra

# Verify
showmount -e localhost
```

**On tracks server:**
```bash
# Install NFS client
sudo apt-get install nfs-common

# Create mount points
sudo mkdir -p /data/genomes /data/tracks

# Mount NFS shares
sudo mount -t nfs 192.168.1.50:/data/moop/data/genomes /data/genomes
sudo mount -t nfs 192.168.1.50:/data/moop/data/tracks /data/tracks

# Verify mount
df -h

# Make persistent in /etc/fstab
192.168.1.50:/data/moop/data/genomes /data/genomes nfs ro,hard,intr 0 0
192.168.1.50:/data/moop/data/tracks  /data/tracks  nfs ro,hard,intr 0 0
```

**Pros:**
- Real-time synchronization
- No bandwidth for copying
- Single source of truth
- Automatic updates

**Cons:**
- NFS performance can be slower
- Network dependency (outage = no tracks)
- More complex setup
- Potential security concerns

**site_config setting:**
```php
'tracks_server' => [
    'enabled' => true,
    'url' => 'https://tracks.example.com/',
    'sync_method' => 'nfs',
    'sync_schedule' => '',  // N/A for NFS
]
```

## Comparison Table

| Feature | Manual | Rsync | NFS |
|---------|--------|-------|-----|
| Setup complexity | ⭐ Simple | ⭐⭐ Medium | ⭐⭐⭐ Complex |
| Performance | Good | Good | Excellent |
| Real-time sync | ❌ No | ❌ No | ✅ Yes |
| Bandwidth efficient | ✅ Yes | ✅ Yes | ⚠️ Network mounted |
| Automation | ❌ Manual | ✅ Automated | ✅ Transparent |
| Failure recovery | Manual | Can retry | Depends on NFS |
| Best for | Dev/Test | Production | High-performance |

## Implementation in Code

The `setup_jbrowse_assembly.sh` script doesn't handle sync—it just prepares files locally. Sync happens **after** files are ready:

```bash
# Example workflow:
1. Create organism in /organisms/Anoura_caudifer/GCA_004027475.1/
2. Run: ./setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1/
   - Creates /data/genomes/Anoura_caudifer/GCA_004027475.1/
   - Indexes reference genome
   - Compresses annotations
3. Run sync script (manual, cron, or NFS):
   - Copies /data/genomes/ and /data/tracks/ to remote server
   - Copies /certs/jwt_public_key.pem to remote server
4. Remote server nginx/Apache serves the files
5. MOOP API returns URLs pointing to remote server (when enabled in config)
```

## Step-by-Step for Each Sync Method

### For MANUAL sync:
```bash
# When you add a new organism:
./setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1/

# Then manually sync:
rsync -av /data/moop/data/genomes/ user@tracks.example.com:/data/genomes/
rsync -av /data/moop/data/tracks/ user@tracks.example.com:/data/tracks/
```

### For RSYNC sync:
```bash
# Initial setup:
./setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1/

# Create sync script (see above)
# Add to crontab
# That's it! Cron handles the rest

# To manually force sync:
/data/moop/tools/sync_to_tracks_server.sh
```

### For NFS sync:
```bash
# Initial setup:
./setup_jbrowse_assembly.sh /organisms/Anoura_caudifer/GCA_004027475.1/

# Setup NFS mounts on tracks server (one time)
# Files are automatically accessible on remote server!
# That's it! No ongoing sync needed
```

## Monitoring and Verification

```bash
# Check if sync is working (for rsync/manual):
ssh user@tracks.example.com du -sh /data/genomes/ /data/tracks/

# Compare sizes to confirm sync:
du -sh /data/moop/data/genomes/ /data/moop/data/tracks/
ssh user@tracks.example.com du -sh /data/genomes/ /data/tracks/

# Check sync logs (for rsync with cron):
tail -f /var/log/jbrowse2_sync.log

# Verify JWT key is present on tracks server:
ssh user@tracks.example.com ls -la /etc/jbrowse2/jwt_public_key.pem

# Test that tracks server can serve files:
curl -I https://tracks.example.com/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai
```

## Current Status (Development)

For **now (development on single machine):**
```php
'tracks_server' => [
    'enabled' => false,
    'sync_method' => 'manual',
]
```

All files stay local, no sync needed. The `fake-tracks-server.php` serves them locally.

**When deploying to production:**
1. Choose sync method (RSYNC recommended)
2. Set up infrastructure
3. Update site_config: set `enabled` => true, `url` => remote URL
4. Test

## References

- **rsync documentation:** https://linux.die.net/man/1/rsync
- **NFS setup guide:** https://help.ubuntu.com/community/SettingUpNFSHowTo
- **JBrowse2 deployment:** https://jbrowse.org/jb2/docs/deployment/

