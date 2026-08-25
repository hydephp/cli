# The HydePHP CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hyde/cli?include_prereleases)](https://packagist.org/packages/hyde/cli)
[![Total Installs on GitHub and Packagist](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Fhydephp%2Fcli%2Ftraffic%2Fdatabase.json&query=%24._database.total_installs&label=Packagist%20Installs)](https://github.com/hydephp/cli)
[![Total Downloads on GitHub](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Fhydephp%2Fcli%2Ftraffic%2Fdatabase.json&query=%24._database.total_binary_downloads&label=Downloads)](https://github.com/hydephp/cli)
[![License MIT](https://img.shields.io/github/license/hydephp/cli)](https://github.com/hydephp/cli/blob/master/LICENSE.md)
[![Test Coverage](https://codecov.io/gh/hydephp/cli/branch/master/graph/badge.svg?token=G6N2161TOT)](https://codecov.io/gh/hydephp/cli)
[![Test Suite](https://github.com/hydephp/cli/actions/workflows/tests.yml/badge.svg)](https://github.com/hydephp/cli/actions/workflows/tests.yml)

## About

The HydePHP CLI is a single-file executable for the static site generator HydePHP.

It carries its own PHP runtime and its own copy of the framework, so you can build a site on a
machine with **no PHP and no Composer installed**. Point it at a directory of Markdown files
and it will build a site; point it at a full HydePHP Composer project and it will run that
project through its own dependencies.

## The two kinds of project

**Portable** — content and configuration only:

```
_pages/
_posts/
_media/
hyde.yml     # optional
```

No `composer.json`, no `vendor/`, nothing to install. The framework, the runtime, and every
dependency come from the executable itself. Fastest builds and easiest deployment.

**Composer** — a full HydePHP project with a `composer.json` that declares Hyde. The project
owns its dependency graph: its own Hyde version and its own addons. The executable supplies
the PHP runtime and hands control to the project's own `hyde` entry point.

The CLI works out which one it is looking at before it does anything else. `hyde info` will
tell you what it decided:

```
$ hyde info

Hyde CLI:     3.0.0
Project type: Portable
Framework:    3.0.0-dev (embedded)
PHP:          8.4.24 (bundled)
Root:         /Users/emma/Sites/foo
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full model.

## Installation

### Direct download

Download the executable for your platform from the [latest release](https://github.com/hydephp/cli/releases/latest):

| Platform | Asset |
| --- | --- |
| Linux (x86_64) | `hyde-linux-x86_64` |
| Linux (arm64) | `hyde-linux-arm64` |
| macOS (Apple Silicon) | `hyde-macos-arm64` |
| macOS (Intel) | `hyde-macos-x86_64` |
| Windows (x86_64) | `hyde-windows-x86_64.exe` |

```bash
# macOS, Apple Silicon
curl -L https://github.com/hydephp/cli/releases/latest/download/hyde-macos-arm64 -o hyde
chmod +x hyde && sudo mv hyde /usr/local/bin/hyde
```

> On macOS, download with `curl` rather than through a browser. A browser marks the file as
> quarantined, and Gatekeeper will refuse to run it until you clear that with
> `xattr -d com.apple.quarantine hyde`.

Every asset is published with a detached GPG signature (`<asset>.sig`) and an OpenSSL
signature (`<asset>.sig.bin`), which is the one `hyde self-update` verifies.

### Using Composer <a href="https://packagist.org/packages/hyde/cli"><img alt="Total Installs on Packagist" src="https://img.shields.io/packagist/dt/hyde/cli?label=installs" align="right"></a>

```bash
composer global require hyde/cli
```

Make sure to place the Composer system-wide vendor bin directory in your `$PATH` so the `hyde` executable can be located by your system. This directory is typically located at `$HOME/.composer/vendor/bin`.

### Docker

```bash
docker pull ghcr.io/hydephp/cli:latest
docker run --rm -it -v "$(pwd):/site" ghcr.io/hydephp/cli:latest build
```

The image is a bare Alpine with the Linux executable in it: it needs no PHP layer, because
the executable brings its own.

## Usage

```bash
# List available commands
hyde

# See what the CLI is, and what it is about to run against
hyde info

# Create a new site
hyde new my-site                # asks which kind you want
hyde new my-site --portable     # content only, nothing to install
hyde new my-site --composer     # a full Composer project (requires Composer)

# Build a site using source files in the working directory
hyde build

# Preview it, with live recompilation
hyde serve
```

## Resources

### Changelog

Please see [CHANGELOG](https://github.com/hydephp/cli/blob/master/CHANGELOG.md) for more information on what has changed recently.

### Contributing

HydePHP is an open-source project, contributions are very welcome! See [CONTRIBUTING.md](https://github.com/hydephp/cli/blob/master/CONTRIBUTING.md) for guidance.


### Security

If you discover any security-related issues, please email emma@desilva.se instead of using the issue tracker.
All vulnerabilities will be promptly addressed.

### Credits

-   [Emma De Silva](https://github.com/emmadesilva), feel free to buy me a coffee! https://buymeacoffee.com/emmads
-   [All Contributors](https://github.com/hydephp/cli/graphs/contributors)

### License

The MIT License. Please see the [License File](https://github.com/hydephp/cli/blob/master/LICENSE.md) for more information.
