#!/usr/bin/env sh

#
# Build the Linux executable inside a container.
#
# The CLI resolves the framework from the sibling `develop@master` checkout through a
# Composer path repository, so the container needs both trees. They are mounted so that
# the relative path in `composer.json` resolves the same way it does on the host:
# `/src/../develop` is `/develop`.
#
#   docker run --rm -v "$PWD/cli":/src -v "$PWD/develop":/develop -w /src \
#       php:8.4-cli-alpine sh /src/bin/build-in-container.sh
#
# The mount points matter beyond taste: static-php-cli bakes absolute paths into the
# buildroot it caches under `.build-linux`, so building the same tree at a different
# path discards that cache and reconfigures PHP from scratch.
#

set -eux

apk add --no-cache bash git curl wget unzip xz build-base autoconf automake libtool \
    pkgconf bison re2c linux-headers cmake

curl -fsSL -o /usr/local/bin/composer https://getcomposer.org/composer-stable.phar
chmod +x /usr/local/bin/composer

if [ ! -d /develop/packages ]; then
    echo "The develop@master checkout is not mounted at /develop." >&2
    echo "See the docker run line at the top of this script." >&2
    exit 1
fi

cd /src

export SPC_WORK_DIR=/src/.build-linux
export COMPOSER_ALLOW_SUPERUSER=1

# Composer and git both refuse a tree owned by another user, which is what a bind mount
# from the host looks like from inside the container.
git config --global --add safe.directory '*'

bash bin/build-native.sh --build=dev
