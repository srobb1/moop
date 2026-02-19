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

#### Prerequisites

Install required dependencies before setting up MOOP:

**1. Install PHP and required extensions:**
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y php php-cli php-sqlite3 php-json php-mbstring php-curl

# Verify PHP version (7.4 or higher required)
php --version

# Verify extensions are enabled
php -m | grep -E "sqlite3|json|posix"
```

**2. Install web server:**
```bash
# Apache
sudo apt-get install -y apache2 libapache2-mod-php
sudo a2enmod rewrite
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
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Composer is needed to install the `firebase/php-jwt` library, which provides JWT (JSON Web Token) authentication for secure JBrowse2 track access.

**5. Install BLAST+ suite:**
```bash
# Ubuntu/Debian
sudo apt-get install -y ncbi-blast+

# Verify installation
blastn -version
```

**6. Install samtools and related tools (for JBrowse2):**
```bash
# Ubuntu/Debian
sudo apt-get install -y samtools tabix

# Verify installations
samtools --version    # Should be 1.x or higher
bgzip --version       # Part of tabix package
tabix --version
```

**7. Install JBrowse2:**

JBrowse2 v4.1.3 is included in the repository at `/jbrowse2/`. No additional installation needed.

**8. Install additional utilities:**
```bash
# JSON processor (jq is useful for working with JSON config files)
sudo apt-get install -y jq

# Verify
jq --version
```

#### Setup MOOP

**1. Clone the repository:**
```bash
# Clone to your web server directory (e.g., /var/www/html or /home/user/public_html)
git clone https://github.com/srobb1/moop.git /var/www/html/moop
cd /var/www/html/moop
```

**2. Install PHP dependencies:**
```bash
composer install
```

**3. Create the users file with your admin account:**
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

**4. Set up filesystem permissions:**
```bash
# Set proper ownership and permissions for web server access
sudo chown -R www-data:www-data /var/www/html/moop
sudo chmod 2775 /var/www/html/moop
sudo chmod 2775 /var/www/html/moop/metadata
sudo chmod 2775 /var/www/html/moop/logs
# See docs/CONFIG_ADMIN_GUIDE.md for complete permission setup
```

**5. Verify BLAST+ installation (optional but recommended):**
```bash
# Test BLAST binaries are accessible
which blastn blastp blastx tblastn tblastx
```

If BLAST+ is installed in a custom location, update paths in `config/site_config.php` (see BLAST+ TOOL PATHS section).

**6. Access the site:**
- Visit: `http://localhost/moop/` (or your server URL)
- Login with username `admin` and your chosen password
- You'll be redirected to the main dashboard

### Verifying Installation

Check that all components are working:

```bash
# Verify PHP extensions
php -m | grep -E "sqlite3|json|posix"

# Verify BLAST+ tools
blastn -version

# Verify samtools (for JBrowse2)
samtools --version

# Verify tabix (for JBrowse2)
tabix --version

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
1. Visit: `http://localhost:8000/moop/admin/organism_checklist.php`
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



