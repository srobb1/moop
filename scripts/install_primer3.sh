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

# Root is needed to WRITE $PREFIX, not inherently — so test that rather than the
# uid. A --prefix inside your home then works without sudo, which is also how
# this script gets tested without installing anything system-wide.
if ! { [ -w "$PREFIX" ] || { [ ! -e "$PREFIX" ] && [ -w "$(dirname "$PREFIX")" ]; }; }; then
    [ "$(id -u)" -eq 0 ] || fail "$PREFIX is not writable — re-run with sudo, or pass --prefix somewhere you own."
fi

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
#
# ⚠️ THE BUILD DIRECTORY MUST NOT BE ON A noexec MOUNT, and /tmp — mktemp's
# default — IS noexec on hardened hosts. This one is. The failure is nasty
# because the build SUCCEEDS: make exits 0 and writes a perfectly good
# primer3_core with mode 755, but access(X_OK) honours the mount flag, so every
# [ -x ] test on it returns false and the script reports a binary that was
# never built. So probe by actually running something, rather than trusting a
# path convention.
pick_build_root() {
    local d probe
    for d in "${TMPDIR:-}" /var/tmp /tmp "$HOME"; do
        [ -n "$d" ] && [ -d "$d" ] && [ -w "$d" ] || continue
        probe="$(mktemp -d "$d/primer3build.XXXXXX" 2>/dev/null)" || continue
        printf '#!/bin/sh\nexit 0\n' > "$probe/probe" 2>/dev/null || { rm -rf "$probe"; continue; }
        chmod 755 "$probe/probe" 2>/dev/null || { rm -rf "$probe"; continue; }
        if "$probe/probe" 2>/dev/null; then
            printf '%s' "$probe"
            return 0
        fi
        rm -rf "$probe"
    done
    return 1
}

BUILD_DIR="$(pick_build_root)" \
    || fail "No writable directory that allows execution (tried \$TMPDIR, /var/tmp, /tmp, \$HOME). All are mounted noexec?"
rm -f "$BUILD_DIR/probe"
echo "Build directory: $BUILD_DIR"
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

# -f, not -x: existence is the question here. The build directory may sit on a
# noexec mount, where a perfectly good binary tests as non-executable — and
# `install -m 755` sets the mode at the DESTINATION anyway, which is what has to
# be executable.
[ -f "$SRC/primer3_core" ] || fail "Build produced no primer3_core (log: $BUILD_DIR/build.log)"

# ------------------------------------------------------------------ install
say "Installing binaries into $BIN_DEST"
install -d "$BIN_DEST"
for exe in primer3_core ntdpal oligotm long_seq_tm_test; do
    # if/then, not `test && install && echo`: under `set -e` that chain returns
    # non-zero for a missing optional binary and kills the whole script at the
    # last statement of the loop body.
    if [ -f "$SRC/$exe" ]; then
        install -m 755 "$SRC/$exe" "$BIN_DEST/"
        echo "  $exe"
    fi
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
# A real primer-picking query, NOT `--version`. The whole point is that a broken
# thermodynamic path only shows up when something actually asks for a primer,
# and the web server is not the place to discover that.
#
# ⚠️ primer3_core EXITS 0 EVEN WHEN IT FAILS. A missing parameters directory
# gives "PRIMER_ERROR=Unable to open file .../dangle.dh" on stdout and status 0,
# so the exit code is worthless here and the OUTPUT is what has to be read.
#
# The template is a real 600 bp transcript fragment, chosen because it reliably
# yields pairs. An earlier version used a short synthetic sequence with Ns in
# it, which primer3 handled perfectly correctly by returning no primers at all
# -- and the check then reported a WORKING install as broken.
TEST_OUT="$BUILD_DIR/verify.out"
"$BIN_DEST/primer3_core" >"$TEST_OUT" 2>&1 <<EOF || true
SEQUENCE_ID=install_check
SEQUENCE_TEMPLATE=AGCCGCACCTCTAATCAATTCACATCACGTGGCTTTCTCATCCAATGAGATTGCTCGTTGCTTCAACATAGTGCAACCGGGCATTTGATCCGAGTTCGCATCGTGCTGCGACAGTCGAAGCTTTCGTCTTTCTCGATCTTCCAGTTCCTTCAGCCATTATGTCGAAGTCAATGTCAGGTATAGAGATGACAGAGGAGTGCATAGAGCTCTTCAAGGACATGAAGATTACAACTAAAGGCGCTGATAGACCCAGGTTCAAATACGCGATATTCAAGCTGTCAGATGATAACACTAAAGTGGAGCTGGAGGAAAAAGTTGAAGCAAAATGCCTTGCAAACAATCGTGAAGAAGATGAGGAAATATTTGAAGAGTTAAAGGGAAAACTGTCCAAGAAAGAGCCTAGATTTATTCTGTATGACATGAGATTCTGCAGCAAGTCTGGCTCCCTCAAGGAAATATTGACTTTCATCAAATGGTGTAGTGACGAAGCACCTATCAAGAAGAAAATGTTGGCCGGCTCTACATGGGAGTACTTGAAAAAGAAGTTTGACGGTTTGAAAAAGTACTTCGAAGCTTCTGAAATATGCGAGATGTGTTACA
PRIMER_TASK=generic
PRIMER_PICK_LEFT_PRIMER=1
PRIMER_PICK_RIGHT_PRIMER=1
PRIMER_THERMODYNAMIC_OLIGO_ALIGNMENT=1
PRIMER_THERMODYNAMIC_PARAMETERS_PATH=$CONFIG_DEST/
PRIMER_PRODUCT_SIZE_RANGE=100-400
=
EOF

if grep -q "^PRIMER_ERROR=" "$TEST_OUT"; then
    echo "--- primer3 said: ---"
    grep "^PRIMER_ERROR=" "$TEST_OUT"
    fail "primer3_core ran but reported an error. A missing or wrong thermodynamic parameters path is the usual cause."
fi

if ! grep -q "^PRIMER_LEFT_0_SEQUENCE=" "$TEST_OUT"; then
    echo "--- primer3 output: ---"; cat "$TEST_OUT"
    fail "primer3_core returned no primers for the built-in test template. The install may be incomplete."
fi

echo "  picked $(grep -m1 '^PRIMER_PAIR_NUM_RETURNED=' "$TEST_OUT" | cut -d= -f2) pairs from the test template"
echo "  $(grep -m1 '^PRIMER_LEFT_0_SEQUENCE=' "$TEST_OUT")"
echo "  $(grep -m1 '^PRIMER_RIGHT_0_SEQUENCE=' "$TEST_OUT")"

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
