# Directory Permissions Setup Guide

## Goal
Allow `www-data` (web server user) to modify files owned by `ubuntu` while keeping `ubuntu` as the owner.

## Solution: SGID + Group Write Permissions

### Concept
- **Owner**: `ubuntu` (maintains control)
- **Group**: `www-data` (web server access)
- **Permissions**: `664` for files, `775` for directories
- **SGID bit**: Set on directories to auto-assign group to new files

### Directories to Configure
- `/data/moop/metadata/` - Configuration files
- `/data/moop/logs/` - Application logs
- `/data/moop/organisms/` - Organism data
- `/data/moop/admin/` - Admin uploads (optional)

## Setup Commands

```bash
# For metadata directory
sudo chmod g+s /data/moop/metadata
sudo chmod 775 /data/moop/metadata
sudo find /data/moop/metadata -type f -exec chmod 664 {} \;
sudo find /data/moop/metadata -type d -exec chmod 775 {} \;

# For logs directory
sudo chmod g+s /data/moop/logs
sudo chmod 775 /data/moop/logs
sudo find /data/moop/logs -type f -exec chmod 664 {} \;
sudo find /data/moop/logs -type d -exec chmod 775 {} \;

# For organisms directory
sudo chmod g+s /data/moop/organisms
sudo chmod 775 /data/moop/organisms
sudo find /data/moop/organisms -type f -exec chmod 664 {} \;
sudo find /data/moop/organisms -type d -exec chmod 775 {} \;
```

## Verification

After running the commands, verify with:

```bash
ls -ld /data/moop/metadata /data/moop/logs /data/moop/organisms
```

You should see `s` in the group permission position:
```
drwxrwsr-x  ubuntu www-data  /data/moop/metadata
drwxrwsr-x  ubuntu www-data  /data/moop/logs
drwxrwsr-x  ubuntu www-data  /data/moop/organisms
```

Check file permissions:
```bash
ls -l /data/moop/metadata/ | head -5
```

You should see:
```
-rw-rw-r-- ubuntu www-data  file.json
```

## What Each Part Does

### `chmod g+s` - SGID (Set-Group-ID) Bit
- Makes new files inherit the directory's group
- New files created in `/metadata/` get `www-data` as group automatically
- Ensures new uploads/configs are writable by web server

### `chmod 775` - Directory Permissions
- Owner can read/write/execute (`rwx`)
- Group can read/write/execute (`rwx`)
- Others can read/execute only (`rx`)
- Allows web server to create files in directory

### `chmod 664` - File Permissions
- Owner can read/write (`rw`)
- Group can read/write (`rw`)
- Others can read only (`r`)
- Allows web server to modify existing files

## How This Enables Permission Fixes

When `www-data` needs to fix permissions on a file:

1. File is owned by `ubuntu` with perms `444` (read-only)
2. File group is `www-data` (set by SGID)
3. Parent directory `/metadata/` is owned by `ubuntu` with group `www-data`
4. `www-data` can write to parent directory (group has write)
5. `www-data` can `chmod()` files in that directory to make them writable
6. Permission alert shows "Fix Permissions" button
7. Button click succeeds because `www-data` can now modify the file

## Security Notes

- ✓ `ubuntu` retains ownership (main user control)
- ✓ `www-data` only gets write access to specific directories
- ✓ Other users cannot modify files (perms `...r--`)
- ✓ SGID bit prevents accidental permission issues with new files
- ✓ No PHP `sudo` needed (safer than shell commands)

## Maintenance

When adding new subdirectories to these locations, apply the same permissions:

```bash
sudo chmod g+s /data/moop/metadata/new_subdir
sudo chmod 775 /data/moop/metadata/new_subdir
```

When adding files manually, ensure proper permissions:

```bash
sudo chmod 664 /data/moop/metadata/new_file.json
sudo chown ubuntu:www-data /data/moop/metadata/new_file.json
```

## Troubleshooting

**Q: Still can't write to files**
- Verify SGID bit is set: `ls -ld` should show `drwxrwsr-x`
- Check file group: `ls -l` should show `www-data` as group
- Check file perms: `ls -l` should show `rw-rw-r--` (664)

**Q: Permission button still doesn't work**
- Run `sudo groups www-data` to verify www-data is in right group
- Restart PHP-FPM if using FPM: `sudo systemctl restart php8.2-fpm`
- Check logs for errors

**Q: New files aren't getting www-data group**
- Verify SGID bit: `ls -ld` should show `s` in group position
- SGID only applies to new files created AFTER setting it
- Existing files need manual `chgrp`

