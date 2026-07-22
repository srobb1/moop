# Installing MOOP's prerequisites

Everything MOOP needs from the operating system, per distribution. You need this page
**once**, before running the installer.

**You probably do not need to read it end to end.** Run the preflight first — it names
exactly what is missing and prints the fix command for each:

```bash
php setup-check.php
```

Then come back for whichever sections it flagged. Return to the
[Quick Start](../README.md#quick-start) when it passes.

**Only PHP and a web server are required to reach the installer.** Everything else can be
added afterwards; MOOP degrades feature-by-feature rather than failing to start.

| Tool | Needed for | Skippable? |
|---|---|---|
| PHP 7.4+ (`posix` `json` `sqlite3` `openssl` `curl`) | everything | no |
| Apache or nginx | everything | no |
| Composer | JWT library for JBrowse2 track auth | no |
| SQLite 3 CLI | database maintenance and debugging | yes, until you need it |
| BLAST+ | the BLAST tool | yes — that tool is unavailable |
| samtools, bgzip, tabix | genome/GFF indexing for JBrowse2 | yes — no genome browser |
| Node.js 18+ / `@jbrowse/cli` | `jbrowse text-index` feature search | yes — no feature-name search |
| bigWigSummary | Expression Explorer | yes — that feature is unavailable |
| jq | convenience for editing JSON by hand | yes |

---

## Contents

- [PHP and extensions](#php-and-extensions)
- [Web server](#web-server)
- [Composer](#composer)
- [SQLite 3](#sqlite-3)
- [BLAST+](#blast)
- [samtools, bgzip, tabix](#samtools-bgzip-and-tabix)
- [Node.js and the JBrowse CLI](#nodejs-and-the-jbrowse-cli)
- [bigWigSummary](#bigwigsummary)
- [jq](#jq)
- [Identifying your web server user](#identifying-your-web-server-user)
- [Manual setup, without the installer](#manual-setup-without-the-installer)

---

## PHP and extensions

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y php php-cli php-sqlite3 php-json php-curl php-xml

# RHEL/CentOS/Rocky
sudo dnf install -y php php-cli php-pdo php-json php-curl php-xml

php --version                                          # 7.4 or higher
php -m | grep -E "sqlite3|json|posix|openssl|curl"     # all five should appear
```

- `openssl` — JWT track authentication for JBrowse2. Usually bundled with PHP.
- `curl` — Galaxy integration; on by default, disable in config if unused.
- `posix` — file-permission reporting; scripts degrade gracefully without it.

See [SETUP/PHP_VERSION_SAFETY.md](SETUP/PHP_VERSION_SAFETY.md) before changing PHP versions
on a running site.

## Web server

```bash
# Ubuntu/Debian — Apache
sudo apt-get install -y apache2 libapache2-mod-php
sudo a2enmod rewrite headers
sudo systemctl restart apache2

# Ubuntu/Debian — nginx
sudo apt-get install -y nginx php-fpm
sudo systemctl enable --now php-fpm nginx

# RHEL/CentOS/Rocky — Apache
sudo dnf install -y httpd php
sudo systemctl enable --now httpd

# RHEL/CentOS/Rocky — nginx
sudo dnf install -y nginx php-fpm
sudo systemctl enable --now nginx php-fpm
```

Apache needs `mod_rewrite` and `mod_headers`. `mod_rewrite` in particular is required by
the JBrowse2 auth gateway, and **if it is missing the rewrite silently does nothing** —
no error anywhere, and the gateway is bypassed. Confirm with `apachectl -M | grep rewrite`.

## Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Installs `firebase/php-jwt`, used to sign JBrowse2 track tokens. The installer runs
`composer install` for you; run it yourself if you are setting up by hand.

## SQLite 3

```bash
sudo apt-get install -y sqlite3      # Ubuntu/Debian
sudo dnf install -y sqlite           # RHEL/CentOS/Rocky
sqlite3 --version
```

The `php-sqlite3` **extension** and the `sqlite3` **command-line tool** are different
things. PHP needs the extension to serve pages; you need the CLI to inspect and repair
databases.

## BLAST+

```bash
# Ubuntu/Debian
sudo apt-get install -y ncbi-blast+

# RHEL/CentOS/Rocky — not in EPEL, install from NCBI
# Check https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/LATEST/ for the current version
curl -O https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/LATEST/ncbi-blast-2.17.0+-x64-linux.tar.gz
tar xzf ncbi-blast-2.17.0+-x64-linux.tar.gz
sudo cp ncbi-blast-2.17.0+/bin/* /usr/local/bin/

blastn -version
```

## samtools, bgzip and tabix

```bash
# Ubuntu/Debian
sudo apt-get install -y samtools tabix

# RHEL/CentOS/Rocky — not in EPEL, build from source
sudo dnf install -y gcc make zlib-devel bzip2-devel xz-devel curl-devel openssl-devel ncurses-devel

curl -LO https://github.com/samtools/htslib/releases/download/1.21/htslib-1.21.tar.bz2
tar xjf htslib-1.21.tar.bz2 && cd htslib-1.21 && ./configure && make && sudo make install && cd ..

curl -LO https://github.com/samtools/samtools/releases/download/1.21/samtools-1.21.tar.bz2
tar xjf samtools-1.21.tar.bz2 && cd samtools-1.21 && ./configure && make && sudo make install && cd ..

samtools --version
bgzip --version
tabix --version
```

## Node.js and the JBrowse CLI

Node 18+ is required by `@jbrowse/cli` (v20 recommended).

```bash
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# RHEL/CentOS/Rocky
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

# Without root — nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
source ~/.nvm/nvm.sh && nvm install 20 && nvm use 20

node --version    # v18+
```

Install the CLI project-locally so the web server can find it:

```bash
# from the MOOP root
mkdir -p tools/jbrowse-cli
npm install -g @jbrowse/cli --prefix tools/jbrowse-cli

cat > tools/jbrowse-cli/jbrowse-run.sh << 'EOF'
#!/bin/bash
exec "$(which node)" tools/jbrowse-cli/lib/node_modules/@jbrowse/cli/dist/bin.js "$@"
EOF
chmod 755 tools/jbrowse-cli/jbrowse-run.sh
tools/jbrowse-cli/jbrowse-run.sh --version
```

The wrapper exists so the right Node version is used regardless of how the web server's
environment is set up. Admin → JBrowse → Track Listing picks this up automatically.

## bigWigSummary

A UCSC kent tool that reads mean signal over an interval from a BigWig, local **or
remote** — which is how MOOP queries a tracks server without downloading anything.

```bash
sudo wget -q https://hgdownload.soe.ucsc.edu/admin/exe/linux.x86_64/bigWigSummary \
    -O /usr/local/bin/bigWigSummary
sudo chmod +x /usr/local/bin/bigWigSummary

bigWigSummary 2>&1 | head -1
# bigWigSummary - Extract summary information from a bigWig file.
```

> Anything MOOP executes from a web request runs under php-fpm's `PrivateTmp`, so it gets
> its own `/tmp` that you cannot see from a shell. Pass kent tools an explicit cache
> directory (`-udcDir=…`) — "it works in my terminal" proves nothing here.

## jq

```bash
sudo apt-get install -y jq     # Ubuntu/Debian
sudo dnf install -y jq         # RHEL/CentOS/Rocky
```

Convenience only, for hand-editing JSON config.

---

## Identifying your web server user

You need this for file ownership.

```bash
dpkg -l | grep -E 'apache2|nginx'      # Ubuntu/Debian
rpm -q httpd nginx                     # RHEL/CentOS/Rocky

# if it is already running, ask it directly
ps aux | grep -E 'httpd|nginx|apache2|php-fpm' | head -5
```

| Platform | Apache | nginx |
|---|---|---|
| Ubuntu/Debian | `www-data:www-data` | `www-data:www-data` |
| RHEL/CentOS/Rocky | `apache:apache` | `nginx:nginx` |

The model is: **your login account owns the files, the web server reads them via group.**
That way you can run git and CLI scripts normally.

```bash
WEB_GROUP=apache                                  # <-- yours, from above
sudo usermod -aG $WEB_GROUP $(whoami)             # log out and back in to take effect
sudo chown -R $(whoami):$WEB_GROUP /path/to/moop
chmod -R g+rX /path/to/moop
```

> **Files you edit later save as mode 640 owned by you, which php-fpm cannot read — and the
> result is a site-wide 500.** `chmod 644` any web-served file after editing it. The real
> error is in the php-fpm error log, which is usually root-only; it is not an opcache
> problem. This catches everyone at least once.

---

## Manual setup, without the installer

`setup.php` does all of this for you and is the recommended path. Use this section if you
are automating a deployment, or if the installer cannot run.

```bash
composer install

# config and metadata from the shipped templates
cp config/config_editable.json.example        config/config_editable.json
cp config/secrets.php.example                 config/secrets.php          # optional
cp metadata/annotation_config.json.example    metadata/annotation_config.json
cp metadata/group_descriptions.json.example   metadata/group_descriptions.json
cp metadata/organism_assembly_groups.json.example metadata/organism_assembly_groups.json
cp metadata/taxonomy_tree_config.json.example metadata/taxonomy_tree_config.json

# directories the app writes to
mkdir -p logs data/genomes data/tracks images metadata/change_log

# JWT keys — without these JBrowse2 cannot load any track
mkdir -p certs
openssl genrsa -out certs/jwt_private_key.pem 2048
openssl rsa -in certs/jwt_private_key.pem -pubout -out certs/jwt_public_key.pem
chmod 640 certs/*.pem

# admin account
sudo php setup-admin.php
```

`setup-admin.php` prompts for a username and password, bcrypt-hashes the password, and
writes the users file with restrictive permissions. The plaintext is never stored and
never committed.

Creating `config/config_editable.json` is what **disables `setup.php`** — the installer
refuses to run once it exists. That is deliberate: it is how the installer stops being a
live entry point on a configured site.

Finally, block direct access to track files so every request goes through the JWT-checking
API endpoint. `data/tracks/.htaccess`:

```apache
# Apache 2.4+
<RequireAll>
    Require all denied
</RequireAll>
ErrorDocument 403 "Access denied. Track files must be accessed through the API endpoint with valid JWT token."
```

On nginx this file does nothing — use the [shipped guard](nginx/moop-security.conf) instead.

---

## Related

- [Quick Start](../README.md#quick-start) — the normal path
- [SELinux and hardening](SELINUX_AND_HARDENING.md) — required reading on Enforcing hosts
- [PHP version safety](SETUP/PHP_VERSION_SAFETY.md)
- [Hardware sizing](../tools/pages/help/system-requirements.php) — capacity planning
