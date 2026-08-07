#!/usr/bin/env bash
#
# Install primer3 for MOOP.
#
# WHY THIS SCRIPT EXISTS: primer3 is not packaged for every distribution — there
# is no primer3 in RHEL 9 BaseOS, AppStream or EPEL — and upstream publishes a
# binary only for Windows. On Linux it is a source build. That is not hard, but
# it has one trap that silently produces a broken install, so it is scripted
# rather than written down.
#
# ⚠️ THE TRAP: `make install` installs the EXECUTABLES ONLY (primer3's
# src/Makefile, install: target). It does NOT install src/primer3_config/, the
# thermodynamic parameter tables. primer3_core then builds and runs and reports
# a thermodynamic error on every query — an install that looks complete and
# works for nothing. This script copies that directory explicitly and then
# PROVES it by running a real query through it.
#
# Where things land, matching how blastn and samtools are already installed on
# this host (root-owned under /usr/local, not a package):
#
#   /usr/local/bin/primer3_core            the binary
#   /usr/local/share/primer3_config/       the thermodynamic tables
#
# Both are overridable below, and both are config-driven in MOOP
# (config/site_config.php: primer3_tools, primer3_config_path) so a deployment
# that puts them elsewhere needs no code change.
#
# Usage:  sudo bash scripts/install_primer3.sh [--version 2.6.1] [--prefix /usr/local]
#
set -euo pipefail

VERSION="2.6.1"
PREFIX="/usr/local"
KEEP_BUILD=0

while [ $# -gt 0 ]; do
    case "$1" in
        --version) VERSION="$2"; shift 2 ;;
        --prefix)  PREFIX="$2";  shift 2 ;;
        --keep)    KEEP_BUILD=1; shift ;;
        -h|--help) sed -n '2,32p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

CONFIG_DEST="$PREFIX/share/primer3_config"
BIN_DEST="$PREFIX/bin"

say()  { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "Run with sudo — this installs into $PREFIX."

# ---------------------------------------------------------------- build deps
# primer3 is mostly C, but libprimer3.cc is C++, so a C compiler alone is not
# enough. On this host gcc was present and gcc-c++ was not, and the build got
# all the way to the last object file before failing — which is why the check
# is for the C++ compiler specifically.
say "Checking build tools"
MISSING=""
command -v make >/dev/null 2>&1 || MISSING="$MISSING make"
command -v gcc  >/dev/null 2>&1 || MISSING="$MISSING gcc"
command -v g++  >/dev/null 2>&1 || MISSING="$MISSING g++"
command -v curl >/dev/null 2>&1 || MISSING="$MISSING curl"

if [ -n "$MISSING" ]; then
    echo "Missing:$MISSING"
    if   command -v dnf     >/dev/null 2>&1; then dnf install -y gcc gcc-c++ make curl
    elif command -v yum     >/dev/null 2>&1; then yum install -y gcc gcc-c++ make curl
    elif command -v apt-get >/dev/null 2>&1; then apt-get update && apt-get install -y build-essential curl
    elif command -v zypper  >/dev/null 2>&1; then zypper install -y gcc gcc-c++ make curl
    elif command -v pacman  >/dev/null 2>&1; then pacman -Sy --noconfirm base-devel curl
    elif command -v apk     >/dev/null 2>&1; then apk add --no-cache build-base curl
    else fail "No known package manager. Install a C++ compiler, make and curl, then re-run."
    fi
else
    echo "make, gcc, g++, curl all present."
fi

# -------------------------------------------------------------------- fetch
BUILD_DIR="$(mktemp -d)"
cleanup() { [ "$KEEP_BUILD" -eq 1 ] || rm -rf "$BUILD_DIR"; }
trap cleanup EXIT

say "Downloading primer3 $VERSION"
# The archive is ~32 MB because it bundles the test suite, and it can be slow
# from a lab network — measured at ~200 KB/s here, so allow real time for it
# rather than letting a default timeout kill a working download.
URL="https://codeload.github.com/primer3-org/primer3/tar.gz/refs/tags/v${VERSION}"
curl -fL --max-time 900 --retry 2 -o "$BUILD_DIR/primer3.tar.gz" "$URL" \
    || fail "Download failed: $URL"

tar xzf "$BUILD_DIR/primer3.tar.gz" -C "$BUILD_DIR"
SRC="$BUILD_DIR/primer3-${VERSION}/src"
[ -d "$SRC" ] || fail "Unexpected archive layout — no $SRC"

# -------------------------------------------------------------------- build
say "Building"
make -C "$SRC" -j"$(nproc 2>/dev/null || echo 2)" >"$BUILD_DIR/build.log" 2>&1 \
    || { tail -20 "$BUILD_DIR/build.log"; fail "Build failed — full log: $BUILD_DIR/build.log"; }

[ -x "$SRC/primer3_core" ] || fail "Build produced no primer3_core"

# ------------------------------------------------------------------ install
say "Installing binaries into $BIN_DEST"
install -d "$BIN_DEST"
for exe in primer3_core ntdpal oligotm long_seq_tm_test; do
    [ -x "$SRC/$exe" ] && install -m 755 "$SRC/$exe" "$BIN_DEST/" && echo "  $exe"
done

# THE STEP `make install` DOES NOT DO. Without this, primer3_core is installed
# and fails on every query.
say "Installing thermodynamic parameters into $CONFIG_DEST"
[ -d "$SRC/primer3_config" ] || fail "No primer3_config/ in the source tree"
rm -rf "$CONFIG_DEST"
install -d "$CONFIG_DEST"
cp -r "$SRC/primer3_config/." "$CONFIG_DEST/"
chmod -R a+rX "$CONFIG_DEST"
echo "  $(find "$CONFIG_DEST" -type f | wc -l) parameter files"

# ------------------------------------------------------------------- verify
# A real query, not `--version`. The whole point is that a broken thermodynamic
# path only shows up when something actually asks for a primer, and the web
# server is not the place to discover that.
say "Verifying with a real query"
TEST_OUT="$BUILD_DIR/verify.out"
"$BIN_DEST/primer3_core" >"$TEST_OUT" 2>&1 <<EOF || true
SEQUENCE_ID=install_check
SEQUENCE_TEMPLATE=GTAGTCAGTAGACNATGACNACTGACGATGCAGACNACACACACACACACAGCACACAGGTATTAGTGGGCCATTCGATCCCGACCCAAATCGATAGCTACGATGACG
PRIMER_TASK=generic
PRIMER_PICK_LEFT_PRIMER=1
PRIMER_PICK_RIGHT_PRIMER=1
PRIMER_THERMODYNAMIC_OLIGO_ALIGNMENT=1
PRIMER_THERMODYNAMIC_PARAMETERS_PATH=$CONFIG_DEST/
PRIMER_PRODUCT_SIZE_RANGE=75-100
=
EOF

if grep -q "PRIMER_ERROR\|thermodynamic" "$TEST_OUT"; then
    echo "--- primer3 said: ---"; cat "$TEST_OUT"
    fail "primer3_core ran but reported an error. The thermodynamic path is the usual cause."
fi
grep -q "PRIMER_LEFT_0_SEQUENCE" "$TEST_OUT" \
    || { cat "$TEST_OUT"; fail "primer3_core produced no primer — see output above."; }

echo "  picked: $(grep -m1 PRIMER_LEFT_0_SEQUENCE "$TEST_OUT")"
echo "          $(grep -m1 PRIMER_RIGHT_0_SEQUENCE "$TEST_OUT")"

# --------------------------------------------------------------------- done
say "Installed"
cat <<EOF
  binary            $BIN_DEST/primer3_core   ($("$BIN_DEST/primer3_core" --about 2>&1 | head -1))
  thermodynamic     $CONFIG_DEST/

MOOP picks these up automatically when they are at the paths above. If you used
--prefix, set them in Admin → Site Configuration, or in config_editable.json:

  "primer3_tools":       { "primer3_core": "$BIN_DEST/primer3_core" },
  "primer3_config_path": "$CONFIG_DEST/"

Then check Admin → Dashboard: the environment check reports primer3 alongside
blastn and samtools.
EOF
