# Security Policy

## Supported Versions

These are the version ranges of HydePHP, and their support status. We follow [Semantic Versioning](https://semver.org), and you can read about our [Backwards Compatability](https://github.com/hydephp/policies/blob/master/backwards-compatability.md) promise here.

| Version | Supported          | Classification       |
|---------|--------------------|----------------------|
| 1.x     | :white_check_mark: | General Availability |
| < 0.64  | :x:                | Beta (legacy)        |
| < 0.8   | :x:                | Alpha stage          |


## Reporting a Vulnerability

If you discover a security vulnerability within this package, please send an e-mail to the creator, Caen De Silva, via caen@desilva.se.
You can also report a vulnerability through GitHub on the [Security Advisory](https://github.com/hydephp/develop/security/advisories) page.

All security vulnerabilities will be promptly addressed.

## Phar Security

The HydePHP CLI is distributed as a Phar archive, which is a PHP executable file format.
The Phar is signed with a GPG key, and the public key is available in the repository.
You can verify the integrity of the Phar by checking the signature against the public key.
This verification is also done automatically when self-updating the CLI using the direct download strategy.

