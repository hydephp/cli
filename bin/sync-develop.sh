#!/usr/bin/env bash

#
# Ensure the HydePHP v3 development monorepo is checked out beside this repository.
#
# HydePHP v3 is unreleased. The CLI consumes the framework and the realtime compiler
# from the `hydephp/develop` monorepo through Composer path repositories, so the
# checkout is a build input rather than something a developer is expected to have
# arranged beforehand. CI calls this before `composer install`.
#
# The branch is `master`, which is the v3 development line. `2.x` is the released
# v2 line, and is never what this CLI is built against.
#

set -euo pipefail

REPO="${HYDE_DEVELOP_REPO:-https://github.com/hydephp/develop.git}"
BRANCH="${HYDE_DEVELOP_BRANCH:-master}"
TARGET="${HYDE_DEVELOP_PATH:-"$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/../develop"}"

if [ -d "$TARGET/.git" ]; then
    echo "==> Using existing develop checkout at $TARGET"

    current="$(git -C "$TARGET" rev-parse --abbrev-ref HEAD)"

    if [ "$current" != "$BRANCH" ]; then
        echo "    Checkout is on '$current'; the CLI must be built against '$BRANCH'." >&2
        echo "    Switch it with: git -C \"$TARGET\" checkout $BRANCH" >&2
        exit 1
    fi
else
    echo "==> Cloning $REPO ($BRANCH) into $TARGET"
    git clone --branch "$BRANCH" --single-branch "$REPO" "$TARGET"
fi

if [ ! -f "$TARGET/HYDEPHP_V3_PLANNING.md" ]; then
    echo "    $TARGET does not look like the v3 development line: HYDEPHP_V3_PLANNING.md is missing." >&2
    exit 1
fi

#
# The `hyde new --composer` project template resolves the framework from this checkout, and
# `composer create-project` copies it to an arbitrary directory before installing, so its
# repository path has to be absolute. The manifest is rendered here rather than committed,
# since the absolute path is a property of the machine and not of the fixture.
#
TEMPLATE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/tests/Fixtures/project-template"
PACKAGES="$(cd "$TARGET/packages" && pwd)/*"

sed "s|@DEVELOP_PACKAGES@|$PACKAGES|" "$TEMPLATE/composer.json.dist" > "$TEMPLATE/composer.json"

echo "==> Rendered the project template manifest against $PACKAGES"
echo "==> develop@$BRANCH ready ($(git -C "$TARGET" rev-parse --short HEAD))"
