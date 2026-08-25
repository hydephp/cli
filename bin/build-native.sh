#!/usr/bin/env bash

# Builds the native `hyde` executable for the host platform.
#
# The runtime version and the extension set both come from build/runtime.json,
# which is the single configuration for the native build. See docs/ARCHITECTURE.md.
#
# Usage: bin/build-native.sh [--skip-spc] [--build=<sha>]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# The static-php-cli working directory holds several gigabytes of sources and build
# output for one platform. Override it to keep separate platforms side by side.
WORK="${SPC_WORK_DIR:-$ROOT/.build}"
CONFIG="$ROOT/build/runtime.json"

SKIP_SPC=0
BUILD_ID=""

for argument in "$@"; do
    case "$argument" in
        --skip-spc) SKIP_SPC=1 ;;
        --build=*) BUILD_ID="${argument#*=}" ;;
        *) echo "Unknown option: $argument" >&2; exit 1 ;;
    esac
done

read_config() {
    php -r '$c = json_decode(file_get_contents($argv[1]), true); echo $c[$argv[2]];' "$CONFIG" "$1"
}

# The extension set, minus anything build/runtime.json records as impossible on the
# platform being built for. bin/build-native.ps1 filters against the same key, so
# the two scripts cannot come to different conclusions about what goes in.
read_extensions() {
    php -r '$c = json_decode(file_get_contents($argv[1]), true); echo implode(",", array_diff(array_keys($c["extensions"]), $c["unsupported-extensions"][$argv[2]] ?? []));' "$CONFIG" "$1"
}

read_composer() {
    php -r '$c = json_decode(file_get_contents($argv[1]), true); echo $c["composer"][$argv[2]];' "$CONFIG" "$1"
}

PHP_VERSION="$(read_config php)"
COMPOSER_VERSION="$(read_composer version)"
COMPOSER_CHECKSUM="$(read_composer sha256)"

case "$(uname -s)" in
    Darwin) TARGET="macos" ;;
    Linux)  TARGET="linux" ;;
    *)      TARGET="$(uname -s | tr '[:upper:]' '[:lower:]')" ;;
esac

EXTENSIONS="$(read_extensions "$TARGET")"

echo "==> HydeCLI native build"
echo "    PHP version: $PHP_VERSION"
echo "    Composer:    $COMPOSER_VERSION"
echo "    Extensions:  $EXTENSIONS"

mkdir -p "$WORK"

# Composer is bundled inside the executable, so that a machine with neither PHP nor
# Composer can still install a project's dependencies. It is pinned by version and
# verified on every build, cached copy included: a Composer that does not hash to
# what build/runtime.json records is not the Composer this release ships.
COMPOSER_PHAR="$WORK/composer-$COMPOSER_VERSION.phar"

if [ ! -f "$COMPOSER_PHAR" ]; then
    echo "==> Downloading Composer $COMPOSER_VERSION"
    curl -fsSL -o "$COMPOSER_PHAR.download" "https://getcomposer.org/download/$COMPOSER_VERSION/composer.phar"
    mv "$COMPOSER_PHAR.download" "$COMPOSER_PHAR"
fi

# Hashed with PHP rather than with `sha256sum` or `shasum`, which are not the same
# command on every host this script runs on. PHP is already a requirement here.
COMPOSER_ACTUAL="$(php -r 'echo hash_file("sha256", $argv[1]);' "$COMPOSER_PHAR")"

if [ "$COMPOSER_ACTUAL" != "$COMPOSER_CHECKSUM" ]; then
    echo "The downloaded Composer does not match the checksum in build/runtime.json." >&2
    echo "  expected: $COMPOSER_CHECKSUM" >&2
    echo "  actual:   $COMPOSER_ACTUAL" >&2
    rm -f "$COMPOSER_PHAR"
    exit 1
fi

echo "==> Composer $COMPOSER_VERSION verified"

if [ "$SKIP_SPC" -eq 0 ]; then
    if [ ! -x "$WORK/spc" ]; then
        echo "==> Downloading static-php-cli"

        case "$(uname -s)-$(uname -m)" in
            Darwin-arm64)   SPC_ASSET="spc-macos-aarch64" ;;
            Darwin-x86_64)  SPC_ASSET="spc-macos-x86_64" ;;
            Linux-aarch64)  SPC_ASSET="spc-linux-aarch64" ;;
            Linux-arm64)    SPC_ASSET="spc-linux-aarch64" ;;
            Linux-x86_64)   SPC_ASSET="spc-linux-x86_64" ;;
            *) echo "Unsupported build host: $(uname -s)-$(uname -m)" >&2; exit 1 ;;
        esac

        curl -fsSL -o "$WORK/spc" "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/$SPC_ASSET"
        chmod +x "$WORK/spc"
    fi

    echo "==> Checking the build environment"
    (cd "$WORK" && ./spc doctor --auto-fix)

    echo "==> Downloading sources"
    (cd "$WORK" && ./spc download --with-php="$PHP_VERSION" --for-extensions="$EXTENSIONS" --prefer-pre-built --retry=3)

    echo "==> Building the PHP CLI and micro SAPI"
    (cd "$WORK" && ./spc build "$EXTENSIONS" --build-cli --build-micro)
fi

MICRO="$WORK/buildroot/bin/micro.sfx"
RUNTIME="$WORK/buildroot/bin/php"

for artifact in "$MICRO" "$RUNTIME"; do
    if [ ! -f "$artifact" ]; then
        echo "Missing build artifact: $artifact" >&2
        exit 1
    fi
done

echo "==> Installing production dependencies"
composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader --working-dir="$ROOT"

# A path repository's contents change whenever the develop checkout moves, while the lock
# stays identical, so an install that reused an existing vendor directory can be holding a
# stale framework. Re-mirroring is a local copy.
echo "==> Re-mirroring the development packages"
composer reinstall hyde/framework hyde/realtime-compiler --no-interaction --no-progress --working-dir="$ROOT"

echo "==> Verifying the embedded dependency graph is v3"
php "$ROOT/bin/verify-v3-graph.php"

echo "==> Building the executable"
php -d phar.readonly=0 "$ROOT/bin/build-phar.php" \
    --micro="$MICRO" \
    --runtime="$RUNTIME" \
    --composer="$COMPOSER_PHAR" \
    ${BUILD_ID:+--build="$BUILD_ID"}

echo "==> Restoring development dependencies"
composer install --no-interaction --no-progress --prefer-dist --working-dir="$ROOT"

echo "==> Done"
ls -lh "$ROOT/builds/"
