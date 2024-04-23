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

### Public Key Information

The public key used for signing builds in the 0.x series range is a PGP key that has the fingerprint `3B829782D5B7BA59`. It is an `rsa3072` key expiring on `2026-04-20`, and is as follows:

```
657B4D97184E9E6E596E6EA13B829782D5B7BA59 (HydePHP CLI Alpha Key <hello@hydephp.com>)
```

<details>
<summary>View full Public Key</summary>

```plaintext
-----BEGIN PGP PUBLIC KEY BLOCK-----
Comment: 657B 4D97 184E 9E6E 596E  6EA1 3B82 9782 D5B7 BA59
Comment: HydePHP CLI Alpha Key <hello@hydephp.com>

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
=L0Bf
-----END PGP PUBLIC KEY BLOCK-----
```
</details>

We also provide an experimental fallback `.bin` signature that uses an OpenSSL key for increased compatibility with systems that do not support GPG.


<details>
<summary>View OpenSSL Public Key</summary>

```plaintext
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAs3cVirZlZhS/zl2svR09
6gcoQg1QNbyHQzomRWwiO3Zk0TphFzRJ/wATFQ+BjytgQzjOEi6YKSVZLgu0CKHd
JG27wpFyuLO0OkykCnHOQ/O81K9YI0WpgAd/pA60BpOh+5LUx0lsjRqPzV/O2Rk4
YekJk7bdLMgwoAM6fTpg1gM1/5ytFd0Gc1461s4cmQCH51pX2NPdldGYNOjgSZKk
qJMYpvDNfLNqzhc2gXHqenswAwWGspWgC32lcm0TVknC5+wt1SDGei5IyP/hv/L5
Hr2C9QvzH3nDuK3qea8Hpk5IbcRoiUm+HIBQ/wRzCa3UOkNGmipNlVicOHxaSwpn
M2x94TIjR2f3adUA9hmjHicPPPmDCc8wUfLmfktF2+4C6NL4BwdRuC2bdp/Dfsys
pW2Rjq4KDU06IzfPq1B6PNs6vwwCbQ4AT/X3hhFl1e25ygRaneB1NRLBCj+/X9j3
lhlxVDo6y83E9QkqebiBJpJ0aGFPfi8vpAt+IgRr2C7rAFiCrjDUIRQaNZfC19W1
UxkBzXPe+HXIOc9CVSWtVgf2fPkyn0WkZmSrN5M0UA12snMoLPDzPJ+K50TwO5Y1
60NQorbPFpjIy5WSAn+a+F5SwZ+3umk1eL+17SjqEmQ/jHYWTX1Hn+LJY+CVUqCz
Xys3FeRJy25FQ/J/npGcxRcCAwEAAQ==
-----END PUBLIC KEY-----
```
</details>

#### Validating certificate authenticity

In order to validate the authenticity of the public key and that it comes from HydePHP you can visit https://trustservices.hydephp.com/certificates/ for a listing of all our public keys and certificates.
You can also see the listing on [GitHub](https://github.com/hydephp/certificates) for the same information. Note that this repository is handled independently of the trust services meaning that two separate platforms would need to be compromised to spoof the key.

The certificate is also listed on several keyservers:
- [OpenPGP Keyserver](https://keys.openpgp.org/vks/v1/by-fingerprint/657B4D97184E9E6E596E6EA13B829782D5B7BA59)
- [Ubuntu Keyserver](https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x657B4D97184E9E6E596E6EA13B829782D5B7BA59)
- [MIT Keyserver](https://pgp.mit.edu/pks/lookup?op=get&search=0x657B4D97184E9E6E596E6EA13B829782D5B7BA59)

#### Certificate security handling

If you are curious how we secure our certificates and private keys, here is a brief overview:

To ensure the security of our certificates and private keys, we implement a multi-layered approach:

- Our private key is safeguarded through several redundancy measures. This includes encrypted storage via GitHub Actions Secrets for cloud builds, alongside a master copy securely managed by our core maintainer. Additionally, there's a physical recovery key stored in a vault in an undisclosed location.
- The current key in use is scoped to only be used for alpha builds in the 0.x series range, this means that security protocols can be field tested before the final key is used for general availability.
- In the unlikely event of a compromise, the key can be revoked and replaced with a new key. The new key will be signed by the old key to ensure continuity of trust.
- Additionally, the public key is made available on several independent platforms, making it much more difficult for an attacker to spoof the key.
- Of course all private keys are protected by unique highly secure and complex passphrases that are never stored in plaintext.

This comprehensive strategy reinforces our commitment to safeguarding the integrity and security of our cryptographic assets.
This document has been assembled to provide transparency and assurance to our users and contributors,
but has been designed in an interactive process with risk assessments for each piece of information disclosed.
