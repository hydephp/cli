<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

use function defined;
use function is_string;
use function openssl_verify;
use function file_get_contents;
use function openssl_pkey_get_public;

/**
 * Verifies the OpenSSL signature published alongside a release artifact.
 *
 * The public key is supplied by the caller so that the verification logic can be
 * exercised against an ephemeral key pair in tests. The self-update command
 * always constructs it with the key embedded in the executable.
 */
final class SignatureVerifier
{
    public function __construct(private readonly string $publicKey)
    {
        //
    }

    /**
     * Is the given signature a valid signature of the given file?
     *
     * @throws RuntimeException If the public key cannot be loaded, or the algorithm is unavailable.
     */
    public function verify(string $file, string $signatureFile): bool
    {
        $key = openssl_pkey_get_public($this->publicKey);

        if ($key === false) {
            throw new RuntimeException('Failed to load the public key.');
        }

        if (! defined('OPENSSL_ALGO_SHA512')) {
            throw new RuntimeException('The OpenSSL extension is missing the SHA-512 algorithm.');
        }

        $data = @file_get_contents($file);
        $signature = @file_get_contents($signatureFile);

        if (! is_string($data) || ! is_string($signature)) {
            return false;
        }

        return openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA512) === 1;
    }
}
