#!/bin/bash
# Build the JBrowse CLI bundle that scripts/install_jbrowse_cli.sh installs: a Node runtime
# plus @jbrowse/cli, in the layout the installer expects (node + lib/node_modules/@jbrowse/cli).
# Runs as an ordinary user, needs network; the install step is the only one that needs sudo.
#
# Node comes from nodejs.org and is checked against the release's SHASUMS256.txt. Use a
# CURRENT LTS line: the bundle used to carry Node 20, which stopped receiving security fixes
# in April 2026 with nothing on the dashboard saying so (dnf does not know about this copy).
#
# Usage:  scripts/build_jbrowse_cli.sh [out_dir]      (default ~/jbrowse-cli-build)
#         NODE_VERSION=v24.22.1 CLI_VERSION=4.3.0 scripts/build_jbrowse_cli.sh
# Then:   sudo scripts/install_jbrowse_cli.sh <out_dir>
#
# NODE_VERSION defaults to the newest release of the NODE_LINE (24) LTS line.
set -euo pipefail

SCRIPTS="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$HOME/jbrowse-cli-build}"
NODE_LINE="${NODE_LINE:-24}"
CLI_VERSION="${CLI_VERSION:-latest}"

if [ -z "${NODE_VERSION:-}" ]; then
    NODE_VERSION=$(curl -sf --max-time 30 https://nodejs.org/dist/index.json \
        | python3 -c "import json,sys; print(next(r['version'] for r in json.load(sys.stdin) if r['version'].startswith('v$NODE_LINE.') and r['lts']))")
fi
TARBALL="node-$NODE_VERSION-linux-x64.tar.xz"

rm -rf "$OUT"
mkdir -p "$OUT/dl"
cd "$OUT/dl"
echo "Node $NODE_VERSION"
curl -sfO --max-time 300 "https://nodejs.org/dist/$NODE_VERSION/$TARBALL"
curl -sfO --max-time 30  "https://nodejs.org/dist/$NODE_VERSION/SHASUMS256.txt"
grep " $TARBALL\$" SHASUMS256.txt | sha256sum -c -
tar -xJf "$TARBALL"
NODE_DIR="$OUT/dl/node-$NODE_VERSION-linux-x64"
cp "$NODE_DIR/bin/node" "$OUT/node"

echo "@jbrowse/cli $CLI_VERSION"
PATH="$NODE_DIR/bin:$PATH" npm install -g --prefix "$OUT" --no-fund --no-audit --loglevel=error "@jbrowse/cli@$CLI_VERSION"

# Smoke test the way the web server will run it (--jitless: SELinux denies php-fpm execmem).
"$OUT/node" --jitless "$OUT/lib/node_modules/@jbrowse/cli/dist/bin.js" --version
rm -rf "$OUT/dl"
echo "Built in $OUT -- now: sudo $SCRIPTS/install_jbrowse_cli.sh $OUT"
