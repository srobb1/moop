# MOOP: Many Organisms One Platform  
__M__ any   
__O__ rganisms  
__O__ ne  
__P__ latform  

In Scottish English `moop` is a verb meaning to keep company or associate closely. 

Code to build a genome db that can work with multiple organisms

- One sqlite db for each organism enables adding new organsims quick and clean.
- Searches can be built depending on need, a user can select a group of organisms, a single organism, or an assembly as the base for each search.

## Getting Started

### System Requirements

**Required:**
- **PHP** 7.4+ with extensions: `posix`, `json`, `sqlite3`
- **Web Server**: Apache (with `mod_rewrite`) or Nginx
- **Disk Space**: Minimum 50GB for organism data (scales with number of organisms)
- **Operating System**: Linux/Unix (for POSIX functions)

See [docs/system-requirements.php](tools/pages/help/system-requirements.php) for detailed hardware sizing and capacity planning.

### Installation

**1. Clone the repository:**
```bash
# Clone to your web server directory (e.g., /var/www/html or /home/user/public_html)
git clone https://github.com/srobb1/moop.git /var/www/html/moop
cd /var/www/html/moop
```

**2. Create the users file with your admin account:**
```bash
# Run the interactive setup script
sudo php setup-admin.php
```

This script will:
- Prompt you for an admin username (default: `admin`)
- Prompt you for a strong password (minimum 8 characters)
- Securely hash the password using bcrypt
- Create `users.json` with restricted permissions (600)

⚠️ **Your password is NOT public** - it's only stored in the local `users.json` file which is never committed to git.

**3. Set up filesystem permissions:**
```bash
# Set proper ownership and permissions for web server access
sudo chown -R www-data:www-data /var/www/html/moop
sudo chmod 2775 /var/www/html/moop
sudo chmod 2775 /var/www/html/moop/metadata
sudo chmod 2775 /var/www/html/moop/logs
# See docs/CONFIG_ADMIN_GUIDE.md for complete permission setup
```

**4. Access the site:**
- Visit: `http://localhost/moop/` (or your server URL)
- Login with username `admin` and your chosen password
- You'll be redirected to the main dashboard

### Initial Configuration

Once logged in as admin:

**1. Go to Site Configuration:**
- Click the **Admin** menu (top navigation)
- Select **Manage Site Configuration**

**2. Update basic settings:**
- **Site Title**: Change from "MOOP" to your organization name
- **Admin Email**: Update to your email address
- **Sequence Types**: Add custom sequence file types if needed

All changes are saved to `config/config_editable.json` and take effect immediately.

### Adding Your First Organism

For detailed instructions on:
- Setting up organism directory structure
- Creating SQLite databases
- Configuring organism metadata

See: [docs/CONFIG_ADMIN_GUIDE.md](docs/CONFIG_ADMIN_GUIDE.md) and [docs/MOOP_COMPREHENSIVE_OVERVIEW.md](docs/MOOP_COMPREHENSIVE_OVERVIEW.md) 



