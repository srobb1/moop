# Configuration Management Guide for Admins

## Quick Start (2 minutes)

All configuration is in `/data/moop/config/`

### I want to...

#### Change the site title
Edit `/data/moop/config/site_config.php`:
```php
'siteTitle' => 'My New Site Name',
```
Restart web server. Done!

#### Change admin email
Edit `/data/moop/config/site_config.php`:
```php
'admin_email' => 'newemail@example.com',
```
Restart web server. Done!

#### Add a new tool
1. Create your tool file: `/data/moop/tools/my_tool.php`
2. Edit `/data/moop/config/tools_config.php` and add:
```php
'my_tool' => [
    'id'              => 'my_tool',
    'name'            => 'My Tool',
    'icon'            => 'fa-star',
    'description'     => 'What it does',
    'btn_class'       => 'btn-primary',
    'url_path'        => '/tools/my_tool.php',
    'context_params'  => ['organism'],
    'pages'           => 'all',
],
```
3. Restart web server
4. Tool now appears in the UI!

#### Disable a tool temporarily
Edit `/data/moop/config/tools_config.php` and comment out the tool entry:
```php
// 'blast_search' => [  // DISABLED - tool won't show
//     ...
// ],
```

#### Add a new sequence type
Edit `/data/moop/config/site_config.php` and add to `sequence_types` array:
```php
'sequence_types' => [
    // ... existing types ...
    'my_sequences' => [
        'pattern' => 'my_sequences.fa',
        'label' => 'My Sequences',
    ],
],
```

#### Deploy to a different site directory
If you want to run this app as a different site (not "moop"):

1. Copy the moop directory: `cp -r /var/www/html/moop /var/www/html/easy_gdb`
2. Edit `/var/www/html/easy_gdb/config/site_config.php`
3. Change this line: `$site = 'easy_gdb';` (was 'moop')
4. All paths and URLs automatically update!
5. Restart web server

## File Reference

### `/data/moop/config/site_config.php`
Main application configuration. Contains paths, URLs, settings, and data definitions.

**What to edit:**
- `root_path` - if server structure changes
- `site` - to deploy for a different site directory
- `admin_email` - your email
- `siteTitle` - your site name
- `sequence_types` - add new file types

**What NOT to edit:**
- Derived path calculations (images_path, absolute_images_path, etc.) - these auto-calculate

### `/data/moop/config/tools_config.php`
Tool registry. Defines which tools are available and where they appear.

**What to edit:**
- Add new tool entries following the template
- Change `name`, `icon`, `description`, `btn_class` to customize appearance
- Change `pages` to control where each tool shows
- Comment out tools to disable them

### `/data/moop/includes/ConfigManager.php`
Core configuration manager class. Read-only for admins - used by developers.

### `/data/moop/includes/config_init.php`
Initialization file. Loaded once per page automatically. Read-only.

### `/data/moop/config/config_schema.php`
Full schema documentation. Reference this for all available config options.

## Debugging

### Check if a config value exists
```php
$config = ConfigManager::getInstance();
$value = $config->get('key_name', 'DEFAULT_VALUE');
```

### See all loaded configuration
```php
$config = ConfigManager::getInstance();
$all = $config->getAllConfig();
var_dump($all);
```

### Validate all required configs
```php
$config = ConfigManager::getInstance();
if ($config->validate()) {
    echo "All config OK!";
} else {
    print_r($config->getMissingKeys());
}
```

### Check which pages a tool appears on
Tools config uses `'pages'` setting:
- `'pages' => 'all'` - shows on every page
- `'pages' => ['index', 'organism']` - shows only on index and organism pages
- Omitting 'pages' defaults to 'all'

Available page names: `index`, `organism`, `group`, `assembly`, `parent`, `multi_organism_search`

## Security

### Access Control is SEPARATE
- User permissions and access levels are in `includes/access_control.php`
- Assembly-level access is stored in `$_SESSION` (user's session data)
- **Configuration system does NOT touch user access**
- Your security model is 100% unaffected by this consolidation

### What ConfigManager handles:
✅ Site paths and URLs  
✅ Application settings (title, email, etc.)  
✅ Tool registry (which tools exist)  
✅ Data configurations (sequence types)  

### What ConfigManager does NOT handle:
❌ User authentication  
❌ User permissions (public/collaborator/admin)  
❌ Assembly-level access control  
❌ Session data  

## Common Issues & Solutions

| Problem | Solution |
|---------|----------|
| Tool doesn't appear | Check `pages` setting in tools_config.php. Check access_control.php - user may not have permission. |
| Site won't load | Check required paths in site_config.php exist on your server. Check web server error logs. |
| Config changes don't apply | Restart web server. Clear browser cache. |
| Not sure what a setting does | See comments above the setting in site_config.php or check config_schema.php |
| Can't add new tool | Make sure tool entry in tools_config.php matches template exactly. Check tool file exists. Restart web server. |
| Site title won't change | Edit siteTitle in site_config.php, restart web server. Check your theme actually uses $siteTitle. |
| Paths are wrong | Check root_path and site values at top of site_config.php. All other paths auto-calculate from these. |

## How a New Admin Gets Started

1. **First Day:** Read this guide (10 min)
2. **Need to Change Something:** Find the task above and follow the steps
3. **Want to Add a Tool:** Follow "Add a new tool" section
4. **Something Broken:** Check "Common Issues" section
5. **Need Full Details:** Read `/data/moop/config/config_schema.php`

**Result:** You can modify config confidently in minutes!

## Questions?

Check `/data/moop/config/config_schema.php` for full technical documentation of all configuration options.

Look for comments in `site_config.php` and `tools_config.php` - they explain what each setting does.
