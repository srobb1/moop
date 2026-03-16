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
- **PHP** 7.4+ with extensions: `posix`, `json`, `sqlite3`, `openssl`, `curl`
- **Web Server**: Apache (with `mod_rewrite` and `mod_headers`) or Nginx
- **Node.js** 16+ and npm (for JBrowse2 upgrades)
- **Disk Space**: Minimum 50GB for organism data (scales with number of organisms)
- **Operating System**: Linux/Unix (for POSIX functions)

See [docs/system-requirements.php](tools/pages/help/system-requirements.php) for detailed hardware sizing and capacity planning.

### Installation

#### Prerequisites

Install required dependencies before setting up MOOP:

**1. Install PHP and required extensions:**
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y php php-cli php-sqlite3 php-json php-curl php-xml

# Verify PHP version (7.4 or higher required)
php --version

# Verify extensions are enabled
php -m | grep -E "sqlite3|json|posix|openssl|curl"
```

Extension notes:
- `openssl` — required for JBrowse2 JWT track authentication (usually included with PHP)
- `curl` — required for Galaxy integration (enabled by default; disable in `config/site_config.php` if not needed)
- `posix` — used for file permission management; scripts handle gracefully if missing

**2. Install web server:**
```bash
# Apache (recommended)
sudo apt-get install -y apache2 libapache2-mod-php
sudo a2enmod rewrite headers
sudo systemctl restart apache2

# OR Nginx (with PHP-FPM)
sudo apt-get install -y nginx php-fpm
sudo systemctl start php-fpm nginx
```

**3. Install SQLite3:**
```bash
# Ubuntu/Debian
sudo apt-get install -y sqlite3

# Verify installation
sqlite3 --version
```

Note: The `php-sqlite3` extension (installed in step 1) is different from the `sqlite3` command-line tool. Both are required - the PHP extension allows PHP to interact with SQLite databases, while the command-line tool is used for database maintenance and debugging.

**4. Install Composer (for PHP dependencies):**
```bash
# Download and install Composer globally
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Composer installs the `firebase/php-jwt` library for JBrowse2 track authentication.

**5. Install Node.js and npm (for JBrowse2 management):**
```bash
# Ubuntu/Debian (via NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify
node --version    # Should be 16+
npm --version
```

Node.js is not needed to run MOOP day-to-day — it's only used to upgrade JBrowse2 (see [Upgrading JBrowse2](#upgrading-jbrowse2) below).

**6. Install BLAST+ suite:**
```bash
# Ubuntu/Debian
sudo apt-get install -y ncbi-blast+

# Verify installation
blastn -version
```

**7. Install samtools and related tools (for JBrowse2):**
```bash
# Ubuntu/Debian
sudo apt-get install -y samtools tabix

# Verify installations
samtools --version    # Should be 1.x or higher
bgzip --version       # Part of tabix package
tabix --version
```

**8. Install additional utilities:**
```bash
# JSON processor (useful for working with JSON config files)
sudo apt-get install -y jq

# Verify
jq --version
```

#### Setup MOOP

**1. Clone the repository:**
```bash
# Clone to your web server directory
git clone https://github.com/srobb1/moop.git /var/www/html/moop
cd /var/www/html/moop
```

**2. Install PHP dependencies:**
```bash
composer install
```

**3. Set up initial configuration files:**
```bash
# Copy example configs (then edit with your site-specific values)
cp config/config_editable.json.example config/config_editable.json
cp metadata/annotation_config.json.example metadata/annotation_config.json
cp metadata/group_descriptions.json.example metadata/group_descriptions.json
cp metadata/organism_assembly_groups.json.example metadata/organism_assembly_groups.json
cp metadata/taxonomy_tree_config.json.example metadata/taxonomy_tree_config.json
```

These files will be customized through the Admin Dashboard after you log in.

**4. Create required directories:**
```bash
# Create directories that the app writes to
mkdir -p logs
mkdir -p data/genomes
mkdir -p data/tracks
mkdir -p images
mkdir -p metadata/change_log
```

**5. Generate JWT keys for JBrowse2 track authentication:**
```bash
mkdir -p certs
openssl genrsa -out certs/jwt_private_key.pem 2048
openssl rsa -in certs/jwt_private_key.pem -pubout -out certs/jwt_public_key.pem
```

These keys sign JWT tokens that authenticate requests for genome track data.
Without them, JBrowse2 cannot load any tracks.

**6. Set up the tracks security file:**

The file `data/tracks/.htaccess` blocks direct access to track files, forcing
all requests through the JWT authentication layer. This is critical — without
it, anyone who knows a file path can bypass authentication entirely.

```bash
cat > data/tracks/.htaccess << 'HTACCESS'
# SECURITY: Block direct access to track files
# All track requests MUST go through /api/jbrowse2/tracks.php
# which validates JWT tokens before serving files

# Apache 2.2 style
<IfVersion < 2.4>
    Order Deny,Allow
    Deny from all
</IfVersion>

# Apache 2.4+ style
<IfVersion >= 2.4>
    Require all denied
</IfVersion>

ErrorDocument 403 "Access denied. Track files must be accessed through the API endpoint with valid JWT token."
HTACCESS
```

**7. Create the users file with your admin account:**
```bash
# Run the interactive setup script
sudo php setup-admin.php
```

This script will:
- Prompt you for an admin username (default: `admin`)
- Prompt you for a strong password (minimum 8 characters)
- Securely hash the password using bcrypt
- Create `users.json` with restricted permissions (600)

Your password is only stored as a bcrypt hash in `users.json` — it is never committed to git.

**8. Set up filesystem permissions:**

> **Note:** The examples below use `www-data`, which is the default web server
> user/group on Debian/Ubuntu with Apache. Substitute the correct user for your
> system: `nginx` for Nginx on RHEL/CentOS, `apache` for Apache on RHEL/CentOS,
> or check with `ps aux | grep -E 'apache|nginx|httpd' | head -1`.

```bash
# Set ownership to web server user (www-data for Apache on Debian/Ubuntu)
sudo chown -R www-data:www-data /var/www/html/moop

# Directories the web server needs to write to
sudo chmod 2775 /var/www/html/moop/metadata
sudo chmod 2775 /var/www/html/moop/metadata/change_log
sudo chmod 2775 /var/www/html/moop/logs
sudo chmod 2775 /var/www/html/moop/images
sudo chmod 2775 /var/www/html/moop/data/genomes
sudo chmod 2775 /var/www/html/moop/data/tracks
sudo chmod 2775 /var/www/html/moop/config

# JWT keys must be readable by web server but not world-readable
sudo chmod 640 /var/www/html/moop/certs/*.pem
```

See [docs/current/admin/PERMISSIONS_GUIDE.md](docs/current/admin/PERMISSIONS_GUIDE.md) for complete permission setup.

**9. Configure HTTP security headers (recommended):**

Add these to your Apache virtual host config (e.g., `/etc/apache2/sites-enabled/000-default.conf`):
```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "same-origin"
```

Then enable the headers module and restart:
```bash
sudo a2enmod headers
sudo systemctl restart apache2
```

**10. Access the site:**
- Visit: `http://localhost/moop/` (or your server URL)
- Login with username `admin` and your chosen password
- You'll be redirected to the main dashboard

**11. Set up site data backups (optional but recommended):**

The Admin Dashboard will prompt you with commands to create a site-data
backup repository. This automatically versions your configuration, metadata,
and user accounts on each admin login. See `lib/housekeeping.php` for details.

### Verifying Installation

Check that all components are working:

```bash
# Verify PHP extensions
php -m | grep -E "sqlite3|json|posix|openssl|curl"

# Verify BLAST+ tools
blastn -version

# Verify samtools (for JBrowse2)
samtools --version

# Verify tabix (for JBrowse2)
tabix --version

# Verify JWT keys exist
ls -la /var/www/html/moop/certs/*.pem

# Verify tracks security
curl -s -o /dev/null -w "%{http_code}" http://localhost/moop/data/tracks/
# Should return 403 (access denied)

# Check web server is running
# For Apache:
sudo systemctl status apache2

# For Nginx:
sudo systemctl status nginx
```

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

**Quick Start:**
1. Visit: `http://localhost/moop/admin/organism_checklist.php`
2. Follow the step-by-step checklist
3. Each step links to detailed management tools for fine-grained configuration

**Main Steps:**

1. **Prepare organism data files:**
   - FASTA sequence files (genome, proteins, etc.)
   - SQLite database (one per organism) containing features, annotations, etc.

2. **Copy to the web server** with proper directory structure:
   ```
   /var/www/html/moop/organisms/
   └── Genus_species/
       ├── organism.json              (organism metadata)
       ├── organism.sqlite            (organism database)
       └── assembly_name/             (assembly subdirectory)
           ├── genome.fa              (FASTA file)
           ├── cds.nt.fa              (CDS sequences)
           ├── protein.aa.fa          (Protein sequences)
           └── transcript.nt.fa       (Transcript sequences)
   ```

3. **Create SQLite database** with your genomic data:
   - See [moop-dbtools](https://github.com/MOOPGDB/moop-dbtools) for detailed instructions
   - Includes guides for data analysis, feature loading, and database schema

4. **Configure organism metadata** through the admin interface:
   - Organism name, taxonomy, images
   - Feature types and annotation settings
   - Group assignments

**Detailed Guides:**
- [Organism Setup & Searches](tools/pages/help/organism-setup-and-searches.php) - comprehensive organism configuration
- [Data Organization](tools/pages/help/organism-data-organization.php) - directory structure and file formats
- [moop-dbtools](https://github.com/MOOPGDB/moop-dbtools) - creating and loading SQLite databases

---

## JBrowse2 Genome Browser

JBrowse2 is the integrated genome browser. A pre-built copy (v4.1.3) is
included in the `jbrowse2/` directory. MOOP's PHP backend dynamically
generates JBrowse2 configurations and authenticates track access via JWT tokens.

### Key Directories

| Path | Purpose |
|------|---------|
| `jbrowse2/` | JBrowse2 web app (pre-built, git-tracked) |
| `data/genomes/` | Reference genomes and annotations (per organism/assembly) |
| `data/tracks/` | Additional track files (BigWig, BAM, VCF, etc.) |
| `certs/` | JWT private/public key pair for track authentication |
| `api/jbrowse2/` | PHP endpoints: config generation, track serving |

### How Track Authentication Works

1. User visits a JBrowse2 view — MOOP generates a config with JWT-signed track URLs
2. JBrowse2 requests track data via `api/jbrowse2/tracks.php?file=...&token=JWT`
3. `tracks.php` validates the JWT and serves the file if authorized
4. Direct access to `data/tracks/` is blocked by `.htaccess` (returns 403)

### Upgrading JBrowse2

To upgrade JBrowse2 to a newer version:

```bash
cd /var/www/html/moop/jbrowse2

# Upgrade in-place using the JBrowse CLI
npx @jbrowse/cli upgrade

# Verify the new version
cat version.txt
```

This downloads the latest JBrowse2 web app build and replaces the files in
the `jbrowse2/` directory. Your `config.json` is preserved. After upgrading,
test that tracks load correctly in the browser.

See the [JBrowse2 documentation](https://jbrowse.org/jb2/docs/) for release
notes and upgrade guides.

### JBrowse2 Documentation

- [Admin Guide](docs/JBrowse2/ADMIN_GUIDE.md) — managing tracks, assemblies, and permissions
- [Developer Guide](docs/JBrowse2/DEVELOPER_GUIDE.md) — architecture and API reference
- [Security](docs/JBrowse2/technical/SECURITY.md) — JWT authentication details
- [Setup New Organism](docs/JBrowse2/SETUP_NEW_ORGANISM.md) — adding genome data for JBrowse2
