#!/bin/bash
# Install the JBrowse CLI where the web server may run it.
#
# MOOP runs `jbrowse text-index` from admin requests (the gene search index JBrowse's search
# box uses). The CLI used to live in tools/jbrowse-cli/, inside the document root, where it
# carries the SELinux label httpd_sys_content_t -- which the web server may read but never
# EXECUTE. Every text-index from the web failed with "jbrowse CLI not found", while the same
# command worked from a shell, so no gene set ever had a search index (found 2026-10-21).
#
# /usr/local/bin gets bin_t by default, like blastn and bgzip, so no semanage rule is needed:
#   /usr/local/bin/jbrowse-node      the Node runtime (executed)
#   /usr/local/lib/jbrowse-cli/      the CLI's JavaScript (only read)
#   /usr/local/bin/jbrowse           wrapper -- moop_find_jbrowse_cli() looks here first
#
# Source: tools/jbrowse-cli/ if present (node + lib/node_modules/@jbrowse/cli), else give
# a directory holding the same layout as $1.
#
# Usage: sudo scripts/install_jbrowse_cli.sh [source_dir]
set -euo pipefail

SRC="${1:-$(cd "$(dirname "$0")/.." && pwd)/tools/jbrowse-cli}"
BIN_JS="lib/node_modules/@jbrowse/cli/dist/bin.js"

[ "$(id -u)" -eq 0 ] || { echo "Run with sudo." >&2; exit 1; }
[ -x "$SRC/node" ] && [ -f "$SRC/$BIN_JS" ] || { echo "No node + $BIN_JS under $SRC" >&2; exit 1; }

install -d -m 755 /usr/local/lib/jbrowse-cli
rm -rf /usr/local/lib/jbrowse-cli/lib
cp -r "$SRC/lib" /usr/local/lib/jbrowse-cli/
install -m 755 "$SRC/node" /usr/local/bin/jbrowse-node
# --jitless: V8 normally compiles to executable memory, which SELinux denies the web server
# (httpd_execmem off) -- node then aborts with "Check failed: 12 == errno" in SetPermissions.
# Turning httpd_execmem on would grant that to EVERY web-server process. Interpreted only, the
# NV2 text index takes 8 s instead of 3 s and is byte-identical.
cat > /usr/local/bin/jbrowse <<EOF
#!/bin/bash
# JBrowse CLI for MOOP -- installed by scripts/install_jbrowse_cli.sh
exec /usr/local/bin/jbrowse-node --jitless /usr/local/lib/jbrowse-cli/$BIN_JS "\$@"
EOF
chmod 755 /usr/local/bin/jbrowse
chown -R root:root /usr/local/lib/jbrowse-cli /usr/local/bin/jbrowse /usr/local/bin/jbrowse-node
chmod -R a+rX,go-w /usr/local/lib/jbrowse-cli
command -v restorecon >/dev/null && restorecon -R /usr/local/lib/jbrowse-cli /usr/local/bin/jbrowse /usr/local/bin/jbrowse-node

echo "Installed: $(/usr/local/bin/jbrowse --version 2>&1 | head -1)"
command -v ls >/dev/null && ls -Z /usr/local/bin/jbrowse /usr/local/bin/jbrowse-node 2>/dev/null || true
