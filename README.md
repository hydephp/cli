# Experimental Standalone HydePHP Executable

## About

This is an experimental standalone executable for the static site generator HydePHP.

With this global binary, you can use the HydePHP HydeCLI to generate static sites from source files anywhere on your system.

## Installation

### Using Composer

```bash
composer global require hyde/cli
```

### Direct Download (Unix)

```bash
curl -L https://github.com/caendesilva/hyde-global/releases/latest/download/hyde -o hyde
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

Please see [CHANGELOG](https://github.com/hydephp/develop/blob/master/CHANGELOG.md) for more information on what has changed recently.

### Contributing

HydePHP is an open-source project, contributions are very welcome!

Development is made in the HydePHP Monorepo, which you can find here https://github.com/hydephp/develop.

### Security

If you discover any security-related issues, please email caen@desilva.se instead of using the issue tracker.
All vulnerabilities will be promptly addressed.

### Credits

-   [Caen De Silva](https://github.com/caendesilva), feel free to buy me a coffee! https://www.buymeacoffee.com/caen
-   [All Contributors](../../contributors)

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
