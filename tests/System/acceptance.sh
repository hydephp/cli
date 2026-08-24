#!/bin/sh

# Runtime acceptance test for the HydeCLI native executable.
#
# This script is the authoritative proof that the artifact works on a machine with no
# PHP, no Composer and no vendor directory. It is written in POSIX shell and needs
# only `sh` and `curl`, so it can run inside a container where PHP was never
# installed and where the PHP test suite therefore cannot run at all.
#
# Usage: tests/System/acceptance.sh /path/to/hyde-linux-x86_64

set -eu

HYDE="${1:-}"

if [ -z "$HYDE" ] || [ ! -x "$HYDE" ]; then
    echo "Usage: $0 /path/to/hyde-executable" >&2
    exit 2
fi

WORK="$(mktemp -d)"
FAILURES=0
CHECKS=0

cleanup() {
    if [ -n "${SERVER_PID:-}" ]; then
        kill "$SERVER_PID" 2>/dev/null || true
    fi

    rm -rf "$WORK"
}

trap cleanup EXIT INT TERM

pass() {
    CHECKS=$((CHECKS + 1))
    echo "  ok    $1"
}

fail() {
    CHECKS=$((CHECKS + 1))
    FAILURES=$((FAILURES + 1))
    echo "  FAIL  $1"
    if [ -n "${2:-}" ]; then
        echo "        $2"
    fi
}

assert_contains() {
    # assert_contains <description> <haystack> <needle>
    case "$2" in
        *"$3"*) pass "$1" ;;
        *) fail "$1" "expected to contain: $3" ;;
    esac
}

assert_file_contains() {
    if [ ! -f "$2" ]; then
        fail "$1" "missing file: $2"
        return
    fi

    assert_contains "$1" "$(cat "$2")" "$3"
}

assert_missing() {
    if [ -e "$2" ]; then
        fail "$1" "unexpectedly exists: $2"
    else
        pass "$1"
    fi
}

echo "==> Environment"

if command -v php >/dev/null 2>&1; then
    fail "no PHP is installed" "found $(command -v php)"
else
    pass "no PHP is installed"
fi

if command -v composer >/dev/null 2>&1; then
    fail "no Composer is installed" "found $(command -v composer)"
else
    pass "no Composer is installed"
fi

echo "==> The executable runs"

if OUTPUT="$("$HYDE" --no-ansi 2>&1)"; then
    assert_contains "the executable runs" "$OUTPUT" "USAGE:"
else
    fail "the executable runs" "$OUTPUT"
fi

VERSION_OUTPUT="$("$HYDE" --version --no-ansi 2>&1)"
assert_contains "hyde --version works" "$VERSION_OUTPUT" "HydePHP"

echo "==> Portable project"

SITE="$WORK/site"
mkdir -p "$SITE/_pages"
printf -- "---\ntitle: Test Page\n---\n\n# Hello Portable World\n" > "$SITE/_pages/index.md"

INFO_OUTPUT="$(cd "$SITE" && "$HYDE" info --no-ansi 2>&1)"
assert_contains "info reports a portable project" "$INFO_OUTPUT" "Project type: Portable"
assert_contains "info reports an embedded framework" "$INFO_OUTPUT" "(embedded)"
assert_contains "info reports a bundled runtime" "$INFO_OUTPUT" "(bundled)"

BUILD_OUTPUT="$(cd "$SITE" && "$HYDE" build --no-ansi 2>&1)"
assert_contains "a portable project builds" "$BUILD_OUTPUT" "Your static site has been built!"
assert_file_contains "the built page has the expected content" "$SITE/_site/index.html" "Hello Portable World"

assert_missing "building creates no vendor directory" "$SITE/vendor"
assert_missing "building creates no composer manifest" "$SITE/composer.json"

ROUTES_OUTPUT="$(cd "$SITE" && "$HYDE" route:list --no-ansi 2>&1)"
assert_contains "route:list works" "$ROUTES_OUTPUT" "_pages/index.md"

MAKE_OUTPUT="$(cd "$SITE" && "$HYDE" make:page About --no-ansi --no-interaction 2>&1)"
assert_contains "make:page works" "$MAKE_OUTPUT" "About"

if [ -f "$SITE/_pages/about.md" ]; then
    pass "make:page wrote the file"
else
    fail "make:page wrote the file"
fi

echo "==> HydePHP v3"

# The version constant is a claim about the code; the checks after it are the evidence.

assert_contains "info reports a v3 framework version" "$INFO_OUTPUT" "3.0.0-dev"

LIST_OUTPUT="$(cd "$SITE" && "$HYDE" list --no-ansi 2>&1)"

if printf '%s' "$LIST_OUTPUT" | grep -q 'rebuild'; then
    fail "the rebuild command v3 removed is absent"
else
    pass "the rebuild command v3 removed is absent"
fi

printf -- '# Probe\n\n```php title="app/Model.php"\necho 1;\n```\n' > "$SITE/_pages/v3-probe.md"
mkdir -p "$SITE/_static"
printf 'example.com\n' > "$SITE/_static/CNAME"
printf 'stray\n' > "$SITE/_site/stray.txt"

(cd "$SITE" && "$HYDE" build --no-ansi >/dev/null 2>&1) || true

assert_file_contains "a code block title renders a v3 label" "$SITE/_site/v3-probe.html" "hyde-code-block-label"
assert_file_contains "_static files are copied to the site root" "$SITE/_site/CNAME" "example.com"
assert_missing "the output directory is emptied completely" "$SITE/_site/stray.txt"

rm -f "$SITE/_pages/v3-probe.md"

echo "==> Configuration"

printf 'name: "Configured Site Name"\n' > "$SITE/hyde.yml"
printf '{{ config("hyde.name", "not-set") }}\n' > "$SITE/_pages/site-name.blade.php"

(cd "$SITE" && "$HYDE" build --no-ansi >/dev/null 2>&1) || true
assert_file_contains "hyde.yml configuration is honoured" "$SITE/_site/site-name.html" "Configured Site Name"

echo "==> Creating a project"

NEW="$WORK/workspace"
mkdir -p "$NEW"

NEW_OUTPUT="$(cd "$NEW" && "$HYDE" new my-site --portable --no-ansi --no-interaction 2>&1)"
assert_contains "hyde new --portable succeeds" "$NEW_OUTPUT" "Created a portable Hyde site"
assert_missing "the new project has no composer manifest" "$NEW/my-site/composer.json"
assert_missing "the new project has no vendor directory" "$NEW/my-site/vendor"

NEW_BUILD="$(cd "$NEW/my-site" && "$HYDE" build --no-ansi 2>&1)"
assert_contains "the new project builds immediately" "$NEW_BUILD" "Your static site has been built!"

echo "==> hyde new --composer without Composer"

COMPOSER_OUTPUT="$(cd "$NEW" && "$HYDE" new composer-site --composer --no-ansi --no-interaction 2>&1)" && COMPOSER_STATUS=0 || COMPOSER_STATUS=$?

if [ "$COMPOSER_STATUS" -eq 0 ]; then
    fail "hyde new --composer fails without Composer" "it reported success"
else
    pass "hyde new --composer fails without Composer"
fi

assert_contains "the failure explains what to do" "$COMPOSER_OUTPUT" "Creating a Composer project requires Composer."
assert_missing "no directory is left behind" "$NEW/composer-site"

echo "==> Project detection"

UNRELATED="$WORK/unrelated"
mkdir -p "$UNRELATED/_pages"
printf '# Unrelated\n' > "$UNRELATED/_pages/index.md"
printf '{"name":"acme/thing","description":"mentions hyde/framework","require":{"monolog/monolog":"^3.0"}}' > "$UNRELATED/composer.json"

UNRELATED_OUTPUT="$(cd "$UNRELATED" && "$HYDE" info --no-ansi 2>&1)"
assert_contains "an unrelated manifest stays portable" "$UNRELATED_OUTPUT" "Project type: Portable"

BROKEN="$WORK/broken"
mkdir -p "$BROKEN/_pages"
printf '# Broken\n' > "$BROKEN/_pages/index.md"
printf '{"name":"acme/site","require":{"hyde/framework":"^2.0"}}' > "$BROKEN/composer.json"

BROKEN_OUTPUT="$(cd "$BROKEN" && "$HYDE" build --no-ansi 2>&1)" && BROKEN_STATUS=0 || BROKEN_STATUS=$?

if [ "$BROKEN_STATUS" -eq 0 ]; then
    fail "a Composer project with no vendor fails" "it reported success"
else
    pass "a Composer project with no vendor fails"
fi

assert_contains "the failure names composer install" "$BROKEN_OUTPUT" "composer install"
assert_missing "nothing was built" "$BROKEN/_site"

echo "==> Serving"

PORT=8${$}
PORT=$((8000 + (PORT % 1000)))

(cd "$SITE" && "$HYDE" serve --host=127.0.0.1 --port="$PORT" --no-ansi > "$WORK/serve.log" 2>&1) &
SERVER_PID=$!

READY=0
i=0
while [ "$i" -lt 60 ]; do
    if curl -fsS -m 2 "http://127.0.0.1:$PORT/" > "$WORK/response.html" 2>/dev/null; then
        READY=1
        break
    fi

    i=$((i + 1))
    sleep 1
done

if [ "$READY" -eq 1 ]; then
    pass "hyde serve answers an HTTP request"
    assert_file_contains "the served page has the expected content" "$WORK/response.html" "Hello Portable World"

    if curl -fsS -m 5 "http://127.0.0.1:$PORT/dashboard" > "$WORK/dashboard.html" 2>/dev/null; then
        assert_file_contains "the realtime compiler dashboard is served" "$WORK/dashboard.html" "Dashboard"
    else
        fail "the realtime compiler dashboard is served"
    fi
else
    fail "hyde serve answers an HTTP request" "$(cat "$WORK/serve.log" 2>/dev/null || true)"
fi

echo
echo "==> $((CHECKS - FAILURES))/$CHECKS checks passed"

if [ "$FAILURES" -gt 0 ]; then
    exit 1
fi
