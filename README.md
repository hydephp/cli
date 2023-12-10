# Experimental Standalone HydePHP Executable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hyde/cli?include_prereleases)](https://packagist.org/packages/hyde/cli)
[![Total Downloads on Packagist](https://img.shields.io/packagist/dt/hyde/cli)](https://packagist.org/packages/hyde/cli)
[![License MIT](https://img.shields.io/github/license/hydephp/cli) ](https://github.com/hydephp/cli/blob/master/LICENSE.md)
[![Test Coverage](https://codecov.io/gh/hydephp/cli/branch/master/graph/badge.svg?token=G6N2161TOT)](https://codecov.io/gh/hydephp/cli)
[![Test Suite](https://github.com/hydephp/cli/actions/workflows/tests.yml/badge.svg)](https://github.com/hydephp/cli/actions/workflows/tests.yml)

## About

This is an experimental standalone executable for the static site generator HydePHP.

With this global binary, you can use the HydePHP CLI to generate quality static sites faster than ever before!

### ⚠ Beta software notice

Please note that the standalone HydePHP version is **experimental**, and that there may be breaking changes and bugs until the 1.0 release.
- In the meantime, you may want to use the standard HydePHP project: https://github.com/hydephp/hyde

## Installation

### Using Composer

```bash
composer global require hyde/cli
```

### Direct Download (Unix)

```bash
curl -L https://github.com/hydephp/cli/releases/latest/download/hyde -o hyde
chmod +x hyde && sudo mv hyde /usr/local/bin/hyde
```

## Usage

```bash
# List available commands
hyde

# Create a new full HydePHP project
hyde new

# Build a site using source files in the working directory
hyde build
```

## Resources

### Changelog

Please see [CHANGELOG](https://github.com/hydephp/cli/blob/master/CHANGELOG.md) for more information on what has changed recently.

### Contributing

HydePHP is an open-source project, contributions are very welcome!


### Security

If you discover any security-related issues, please email caen@desilva.se instead of using the issue tracker.
All vulnerabilities will be promptly addressed.

### Credits

-   [Caen De Silva](https://github.com/caendesilva), feel free to buy me a coffee! https://www.buymeacoffee.com/caen
-   [All Contributors](../../contributors)

### License

The MIT License. Please see the [License File](LICENSE.md) for more information.
