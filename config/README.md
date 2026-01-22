# Configuration System Documentation

The MOOP configuration system provides centralized access to all application settings through the **ConfigManager** singleton class.

---

## Architecture: Defaults + Overrides

**Key Principle:** Separate static defaults from dynamic overrides

```
ConfigManager (singleton)
    ↓
1. Load site_config.php (defaults)
    ↓
2. Load config_editable.json (overrides)
    ↓
3. Merge: editable values override defaults
    ↓
Result: Final merged configuration
```

---

## Files in the Config Directory

### 1. site_config.php - Static Defaults

**Purpose:** Define all default configuration values

**Never edited by users** - only by admins deploying/configuring MOOP

**Contains:**
- File system paths (root_path, organism_data, metadata, logs, etc.)
- Site title (default)
- Admin email (default)
- Sequence types (protein, nucleotide, etc.)
- Tool registry
- Default security settings
- All other application defaults

**Characteristics:**
- PHP file with large associative array
- Version controlled
- Deployment-specific (paths may differ per server)
- Single source of truth for default values
- Loaded fresh each page load by ConfigManager

**When to edit:**
- Changing installation path
- Adding new tools
- Adjusting security defaults
- Multi-site deployments
- Structural/architectural changes

**Example:**
```php
return [
    'root_path' => '/data/moop',
    'organism_data' => '/path/to/organisms',
    'metadata_path' => '/data/moop/metadata',
    'siteTitle' => 'MOOP Database',  // Can be overridden
    'admin_email' => 'admin@example.com',  // Can be overridden
    // ... more settings
];
```

---

### 2. config_editable.json - User Editable Overrides

**Purpose:** Runtime configuration that can be changed via admin interface

**What admins CAN edit** through "Manage Site Configuration":
- Site title
- Admin email
- Header image/favicon
- Auto-login IP ranges
- Sequence type display labels

**Characteristics:**
- JSON file
- Created automatically on first admin edit
- Contains ONLY values that differ from defaults
- Backed up automatically when changed
- Has `_metadata` section with edit history

**Security Design:**
- Only whitelisted keys can be edited (8 keys)
- Prevents accidental override of critical paths
- ConfigManager validates on load
- Cannot edit file paths or sensitive settings

**Example:**
```json
{
  "siteTitle": "My Custom Database",
  "admin_email": "newemail@example.com",
  "header_img": "custom_banner.png",
  "favicon_filename": "favicon.ico",
  "auto_login_ip_ranges": ["192.168.1.0/24", "10.0.0.0/8"],
  "_metadata": {
    "last_updated": "2026-01-22 15:30:00",
    "version": "1.0"
  }
}
```

**When created:**
- Doesn't exist initially (all defaults come from site_config.php)
- Created when admin first saves changes
- Must be writable by web server (www-data)

---

### 3. tools_config.php - Tool Definitions

**Purpose:** Define all available tools in MOOP

**Contains:**
- Tool names and display labels
- Tool icons
- Tool descriptions
- Tool availability (which organisms/groups support them)
- Tool-specific settings

**Characteristics:**
- PHP file with tool registry array
- Loaded by ConfigManager
- Defines what tools are available
- Can reference site_config paths

**Used by:**
- Navbar/toolbar to display available tools
- Access control to validate tool access
- Tool initialization

**Example:**
```php
return [
    'organism' => [
        'name' => 'Organism Browser',
        'icon' => 'fa-database',
        'description' => 'Browse organisms and assemblies',
    ],
    'blast' => [
        'name' => 'BLAST Search',
        'icon' => 'fa-search',
        'description' => 'Search using BLAST',
    ],
    // ... more tools
];
```

---

### 4. build_and_load_db/ - Database Setup Scripts

**Purpose:** Scripts for creating and populating organism databases

**Contains:**
- `create_schema_sqlite.sql` - SQLite schema definition
- `import_genes_sqlite.pl` - Perl script to import gene data
- `load_annotations_fast.pl` - Perl script to load annotations
- `setup_new_db_and_load_data_fast_per_org.sh` - Bash orchestration script

**When used:**
- Setting up a new organism
- Importing genome data
- Loading annotation data
- One-time database initialization

**Not used during normal operations** - only during setup/administration

---

## How ConfigManager Works

### Initialization

Every page loads configuration via `includes/config_init.php`:

```php
include_once __DIR__ . '/config_init.php';

// Now available globally:
$config = ConfigManager::getInstance();
```

### Load Process

```
ConfigManager::getInstance()->initialize(
    '/path/to/site_config.php',
    '/path/to/tools_config.php'
)
    ↓
1. Load site_config.php into memory
    ↓
2. Check if config_editable.json exists
    ↓
3. If exists, load it into memory
    ↓
4. For each whitelisted key:
   - If in editable config → use that value
   - Otherwise → use default from site_config
    ↓
Final merged configuration ready to use
```

### Usage

**Type-safe getters:**
```php
$config = ConfigManager::getInstance();

$root = $config->getPath('root_path');           // Path: /data/moop
$title = $config->getString('siteTitle');       // String: MOOP Database
$types = $config->getArray('sequence_types');   // Array: [...]
```

**Get tools:**
```php
$all_tools = $config->getAllTools();    // All tools array
$tool = $config->getTool('blast');      // Single tool
```

**Management:**
```php
$all = $config->getAll();                       // Entire config
$config->saveEditableConfig($data, $config_dir); // Save changes
```

---

## Editing Configuration

### Via Admin Interface (Recommended)

1. Login as admin
2. Navigate to "Manage Site Configuration"
3. Edit whitelisted fields:
   - Site Title
   - Admin Email
   - Header Image
   - Favicon
   - Auto-login IP ranges
4. Click Save
5. Changes written to config_editable.json

**Advantages:**
- ✓ No server access needed
- ✓ Easy for non-technical admins
- ✓ Changes logged
- ✓ Validated before saving
- ✓ Automatic backups

### Via Direct File Edit (Deployment)

Edit `/data/moop/config/site_config.php` directly for:
- Changing file paths
- Adding new tools
- Adjusting security settings
- Structural changes

**Only when:**
- Deploying to new server
- Changing directory structure
- Adding custom tools
- Multi-site setup

**Note:** Requires SSH access and server restart may be needed

---

## Whitelisted Editable Keys

These 8 keys can be edited through admin interface:

1. **siteTitle** - Site display name (string)
2. **admin_email** - Admin contact email (string)
3. **header_img** - Header image filename (string)
4. **favicon_filename** - Favicon filename (string)
5. **auto_login_ip_ranges** - IP ranges for auto-login (array)
6. **sequence_types** - Sequence type labels (array)
7. **sample_feature_ids** - Example feature IDs (array)
8. **blast_sample_sequences** - Example FASTA sequences (array)

**All other settings require direct site_config.php edit**

---

## Security Model

### Separation of Concerns

- **site_config.php**: System paths, defaults (controlled by admins)
- **config_editable.json**: User-facing settings (edited via UI)
- **ConfigManager**: Loads and merges both safely

### Protection Mechanisms

1. **Whitelist System**
   - Only 8 keys can be edited via admin
   - Prevents accidental override of critical paths
   - Defined in ConfigManager

2. **File Permissions**
   - site_config.php: read-only after deployment
   - config_editable.json: writable by web server
   - Prevents unauthorized modifications

3. **Validation**
   - ConfigManager validates on load
   - Missing keys detected
   - Type checking on getters

4. **Atomic Updates**
   - Changes written atomically
   - No partial config states
   - Automatic backups on change

---

## Common Tasks

### Adding a Configuration Setting

**For static settings (paths, structural):**
1. Edit `site_config.php`
2. Add key to array
3. Document in this README
4. Test with `$config->getPath()` or appropriate getter

**For editable settings (appearance, email, etc.):**
1. Add to whitelisted keys in ConfigManager
2. Edit `site_config.php` for default value
3. Make admin interface field for it
4. Users can override via admin panel

### Viewing All Configuration

```php
$config = ConfigManager::getInstance();
$all_settings = $config->getAll();
print_r($all_settings);
```

### Checking for Missing Keys

```php
$config = ConfigManager::getInstance();
$missing = $config->getMissingKeys();
if (!empty($missing)) {
    echo "Missing configuration keys: " . implode(', ', $missing);
}
```

### Resetting to Defaults

Delete `config_editable.json`:
```bash
rm /data/moop/config/config_editable.json
```

Next page load will use all defaults from `site_config.php`

---

## File References

```
config/
├── site_config.php                  # Default configuration
├── config_editable.json             # Editable overrides (auto-created)
├── tools_config.php                 # Tool registry
├── build_and_load_db/               # Database setup scripts
│   ├── create_schema_sqlite.sql     # Schema
│   ├── import_genes_sqlite.pl       # Gene import
│   ├── load_annotations_fast.pl     # Annotation import
│   └── setup_new_db_and_load_data_fast_per_org.sh # Setup script
└── README.md                        # This file

includes/
├── ConfigManager.php                # Configuration singleton class
└── config_init.php                  # Bootstrap (initializes ConfigManager)
```

---

## Related Documentation

- **Using Configuration:** See `includes/README.md`
- **Page Architecture:** See `MOOP_COMPREHENSIVE_OVERVIEW.md`
- **Admin Tools:** See `admin/DEVELOPER_GUIDE.md`
- **Configuration Management System:** See `MOOP_COMPREHENSIVE_OVERVIEW.md` section

---

**Last Updated:** January 2026
