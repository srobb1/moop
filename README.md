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

# RHEL/CentOS/Rocky
sudo dnf install -y php php-cli php-pdo php-json php-curl php-xml

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

# RHEL/CentOS/Rocky — Apache
sudo dnf install -y httpd php
sudo systemctl enable --now httpd

# OR RHEL/CentOS/Rocky — Nginx (with PHP-FPM)
sudo dnf install -y nginx php-fpm
sudo systemctl enable --now nginx php-fpm
```

**3. Install SQLite3:**
```bash
# Ubuntu/Debian
sudo apt-get install -y sqlite3

# RHEL/CentOS/Rocky
sudo dnf install -y sqlite

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

# RHEL/CentOS/Rocky (via NodeSource)
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

# Verify
node --version    # Should be 16+
npm --version
```

Node.js is not needed to run MOOP day-to-day — it's only used to upgrade JBrowse2 (see [Upgrading JBrowse2](#upgrading-jbrowse2) below).

**6. Install BLAST+ suite:**
```bash
# Ubuntu/Debian
sudo apt-get install -y ncbi-blast+

# RHEL/CentOS/Rocky — BLAST+ is not in EPEL, install manually from NCBI
# Check https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/LATEST/ for the latest version
curl -O https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/LATEST/ncbi-blast-2.17.0+-x64-linux.tar.gz
tar xzf ncbi-blast-2.17.0+-x64-linux.tar.gz
sudo cp ncbi-blast-2.17.0+/bin/* /usr/local/bin/

# Verify installation
blastn -version
```

**7. Install samtools and related tools (for JBrowse2):**
```bash
# Ubuntu/Debian
sudo apt-get install -y samtools tabix

# RHEL/CentOS/Rocky — not in EPEL, install from source
# Install build dependencies first
sudo dnf install -y gcc make zlib-devel bzip2-devel xz-devel curl-devel openssl-devel ncurses-devel

# htslib (provides tabix and bgzip)
curl -LO https://github.com/samtools/htslib/releases/download/1.21/htslib-1.21.tar.bz2
tar xjf htslib-1.21.tar.bz2 && cd htslib-1.21 && ./configure && make && sudo make install && cd ..

# samtools
curl -LO https://github.com/samtools/samtools/releases/download/1.21/samtools-1.21.tar.bz2
tar xjf samtools-1.21.tar.bz2 && cd samtools-1.21 && ./configure && make && sudo make install && cd ..

# Verify installations
samtools --version    # Should be 1.x or higher
bgzip --version       # Part of tabix package
tabix --version
```

**8. Install additional utilities:**
```bash
# JSON processor (useful for working with JSON config files)
sudo apt-get install -y jq

# RHEL/CentOS/Rocky
sudo dnf install -y jq

# Verify
jq --version
```

#### Setup MOOP

**1. Identify your web server user/group:**

Before cloning, determine the web server user so you can set permissions correctly.

```bash
# Check which web server is installed
# Ubuntu/Debian
dpkg -l | grep -E 'apache2|nginx'

# RHEL/CentOS/Rocky
rpm -q httpd nginx

# If the web server is already running, check who it runs as
ps aux | grep -E 'httpd|nginx|apache2|php-fpm' | head -5
```

Common web server user/group by platform:

| Platform | Apache | Nginx |
|----------|--------|-------|
| Ubuntu/Debian | `www-data:www-data` | `www-data:www-data` |
| RHEL/CentOS/Rocky | `apache:apache` | `nginx:nginx` |

If your web server is not installed yet, install it first (see Prerequisites step 2 above),
then come back to this step.

**2. Set up the web root and clone the repository:**

The deploy user (your login account) should own the files so you can run git
and CLI scripts. The web server reads files via group membership.

```bash
# Replace WEB_GROUP with your web server group from step 1
# (www-data, apache, or nginx)
WEB_GROUP=apache   # <-- change this to match your system

# Add your user to the web server group (log out and back in to take effect)
sudo usermod -aG $WEB_GROUP $(whoami)

# Make sure you can write to the web root
sudo chown $(whoami):$WEB_GROUP /var/www/html

# Clone the repository
git clone https://github.com/srobb1/moop.git /var/www/html/moop
cd /var/www/html/moop

# Set group on all files so the web server can read them
sudo chown -R $(whoami):$WEB_GROUP /var/www/html/moop
chmod -R g+rX /var/www/html/moop
```

**3. Install PHP dependencies:**
```bash
composer install
```

**4. Set up initial configuration files:**
```bash
# Copy example configs (then edit with your site-specific values)
cp config/config_editable.json.example config/config_editable.json
cp metadata/annotation_config.json.example metadata/annotation_config.json
cp metadata/group_descriptions.json.example metadata/group_descriptions.json
cp metadata/organism_assembly_groups.json.example metadata/organism_assembly_groups.json
cp metadata/taxonomy_tree_config.json.example metadata/taxonomy_tree_config.json
```

These files will be customized through the Admin Dashboard after you log in.

**5. Create required directories:**
```bash
# Create directories that the app writes to
mkdir -p logs
mkdir -p data/genomes
mkdir -p data/tracks
mkdir -p images
mkdir -p metadata/change_log
```

**6. Generate JWT keys for JBrowse2 track authentication:**
```bash
mkdir -p certs
openssl genrsa -out certs/jwt_private_key.pem 2048
openssl rsa -in certs/jwt_private_key.pem -pubout -out certs/jwt_public_key.pem
```

These keys sign JWT tokens that authenticate requests for genome track data.
Without them, JBrowse2 cannot load any tracks.

**7. Set up the tracks security file:**

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

**8. Create the users file with your admin account:**
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

**9. Set up filesystem permissions:**

Files should be owned by your deploy user with the web server group (set in step 1).
This lets you run git and CLI tools normally while the web server reads via group access.

```bash
# Use the same WEB_GROUP from step 2 (www-data, apache, or nginx)
WEB_GROUP=apache   # <-- change this to match your system

# Ensure ownership: deploy user owns, web server group reads
sudo chown -R $(whoami):$WEB_GROUP /var/www/html/moop
chmod -R g+rX /var/www/html/moop

# Directories the web server needs to WRITE to (setgid ensures new files inherit group)
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

**10. Configure web server (required for JBrowse2 auth + recommended security headers):**

Edit your virtual host / server block config:

- Apache Ubuntu/Debian: `/etc/apache2/sites-enabled/000-default.conf`
- Apache RHEL/CentOS/Rocky: `/etc/httpd/conf.d/moop.conf` (create this file)
- Nginx: your server block config file

**Apache** — add inside `<VirtualHost>`:
```apache
# JBrowse2 session auth gateway (required)
RewriteEngine On
RewriteRule ^/moop/jbrowse2/index\.html$ /moop/auth_gateway.php [L,QSA]

# Security headers (recommended)
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "same-origin"
```

Enable required modules and restart:
```bash
# Ubuntu/Debian
sudo a2enmod rewrite headers
sudo systemctl restart apache2

# RHEL/CentOS/Rocky (both modules enabled by default)
sudo systemctl restart httpd
```

**Nginx** — add inside `server {}`:
```nginx
# JBrowse2 session auth gateway (required)
location = /moop/jbrowse2/index.html {
    rewrite ^ /moop/auth_gateway.php last;
}

# Security headers (recommended)
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "same-origin" always;
```

Reload after editing:
```bash
sudo systemctl reload nginx
```

See [JBrowse2 Session Authentication](#jbrowse2-session-authentication-web-server-config) for details on what the rewrite rule does.

**11. Access the site:**
- Visit: `http://localhost/moop/` (or your server URL)
- Login with username `admin` and your chosen password
- You'll be redirected to the main dashboard

**12. Site data backups (automatic):**

MOOP automatically backs up your configuration, metadata, and user accounts
to a separate directory on each admin login. The backup path is configured
via `site_data_path` in `config/site_config.php` (default: `/var/www/html/moop-site-data/`).

The directory is created automatically on first admin login. No manual setup required.

> **Keep this directory private** — it contains user accounts and may contain API keys.

Optionally, you can initialize the backup directory as a git repo for version
history. See the README created inside the backup directory for instructions.

See `lib/housekeeping.php` for details on what gets backed up.

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

# Verify JBrowse2 auth gateway redirects unauthenticated users to login
curl -s -o /dev/null -w "%{http_code}" http://localhost/moop/jbrowse2/index.html
# Should return 302 (redirect to login); 200 means the rewrite rule is not active

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

## Organism Cache

The **Manage Organisms** admin page validates every organism's database, FASTA
files, BLAST indexes, and metadata. With many organisms (50+), this scan can
take over a minute and may exceed the web server's timeout.

To avoid this, scan results are cached in `organisms/.organism_cache.json`.
The cache is automatically invalidated when organism data changes (detected via
file modification times and directory listings).

### Warming the cache

After adding organisms or on a fresh deployment, run the CLI script to build
the cache before visiting the page in the browser:

```bash
php scripts/warm_organism_cache.php
```

Use `--force` to rescan even if the cache appears up to date:

```bash
php scripts/warm_organism_cache.php --force
```

The Manage Organisms page also has a **Rescan** button for manual refresh from
the browser (works fine for smaller sites, but may time out with 50+ organisms).

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

### JBrowse2 Session Authentication (Web Server Config)

When users open JBrowse2 in a new window and share or bookmark the URL, MOOP
must check their session before JBrowse2 loads. Without this, an expired session
shows a cryptic load error instead of a login prompt.

Add the following to your web server config to route `jbrowse2/index.html`
through MOOP's auth gateway (`auth_gateway.php`):

**Apache** — add inside your `<VirtualHost>` block:

- Ubuntu/Debian: `/etc/apache2/sites-enabled/000-default.conf`
- RHEL/CentOS/Rocky: `/etc/httpd/conf.d/moop.conf`

```apache
RewriteEngine On
RewriteRule ^/moop/jbrowse2/index\.html$ /moop/auth_gateway.php [L,QSA]
```

Enable `mod_rewrite` if not already active, then restart:
```bash
# Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2

# RHEL/CentOS/Rocky (mod_rewrite is enabled by default)
sudo systemctl restart httpd
```

**Nginx** — add inside your `server {}` block:

```nginx
location = /moop/jbrowse2/index.html {
    rewrite ^ /moop/auth_gateway.php last;
}
```

Reload after editing:
```bash
sudo systemctl reload nginx
```

After this is in place: unauthenticated users who follow a saved JBrowse2 URL
are redirected to the MOOP login page and returned to their original JBrowse2
URL (with session state intact) after logging in.

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
