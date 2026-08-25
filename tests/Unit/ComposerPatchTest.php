<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The patches carried against the bundled Composer
|--------------------------------------------------------------------------
|
| The executable ships Composer, so a Composer bug that breaks one of our
| platforms breaks the CLI there. bin/lib/composer-patches.php carries the
| minimum fix; these are the tests that say what each one has to do.
|
| The build asserts a patch still applies to the archive. These assert that
| what it applies is right, using the extension info the platforms actually
| report — so they need no Composer, no network and no built artifact.
|
*/

/** @return array<string, array{file: string, search: string, replace: string, issue: string, summary: string}> */
function composerPatches(): array
{
    return require dirname(__DIR__, 2).'/bin/lib/composer-patches.php';
}

/**
 * The platform package Composer would name, given a pattern and a block of `php --ri curl`.
 *
 * This is what PlatformRepository does with the match: the library goes into the
 * package name, lowercased.
 */
function curlSslPackage(string $pattern, string $info): ?string
{
    return preg_match($pattern, $info, $matches) === 1
        ? 'lib-curl-'.strtolower($matches['library'])
        : null;
}

/**
 * What a curl built against Schannel reports. Every static-php-cli Windows build does:
 * it compiles curl with `-DCURL_USE_SCHANNEL=ON -DCURL_USE_OPENSSL=OFF`.
 *
 * Line endings are what PHP actually emits here, which the URL in the upstream report
 * confirms: `lib-curl-schannel%0Azlib version` — one LF, no carriage return.
 */
function schannelCurlInfo(string $break = "\n"): string
{
    return implode($break, [
        'cURL support => enabled',
        'cURL Information => 8.15.0',
        'Age => 11',
        'Host => x86_64-pc-win32',
        'SSL Version => Schannel',
        'ZLib Version => 1.3.2',
        'libSSH Version => libssh2/1.11.1',
        '',
    ]);
}

it('is a patch against a Composer bug that is still present upstream', function () {
    $patch = composerPatches()['composer-12615'];

    expect($patch['file'])->toBe('src/Composer/Repository/PlatformRepository.php')
        ->and($patch['issue'])->toContain('composer/composer/issues/12615');
});

it('changes one character class and nothing else', function () {
    // The narrowest patch that fixes the bug is the one to carry: every character that
    // differs from the release is a character somebody has to re-check on every bump.
    $patch = composerPatches()['composer-12615'];

    expect(str_replace('[^\r\n/]', '[^/]', $patch['replace']))->toBe($patch['search']);
});

it('writes the escapes into the patched source rather than the characters they stand for', function () {
    // A double-quoted string here would put a real carriage return inside Composer's
    // source. It would even work, and it would be indefensible to read.
    $patch = composerPatches()['composer-12615'];

    expect($patch['replace'])->toContain('\r\n')
        ->and($patch['replace'])->not->toContain("\r")
        ->and($patch['replace'])->not->toContain("\n");
});

it('names a package out of three lines of curl info before the patch', function () {
    // The bug, stated as the thing it produces. Composer rejects this name as invalid
    // and aborts the resolution, so no install of any kind can run on Windows.
    $package = curlSslPackage(composerPatches()['composer-12615']['search'], schannelCurlInfo());

    expect($package)->toBe("lib-curl-schannel\nzlib version => 1.3.2\nlibssh version => libssh2");
});

it('names no ssl backend package at all after the patch', function (string $break) {
    // Schannel advertises no version, so there is no `lib-curl-<backend>` version to
    // register. Not matching is the right answer: `ext-curl` and `lib-curl` are both
    // registered earlier and are untouched by this.
    $package = curlSslPackage(composerPatches()['composer-12615']['replace'], schannelCurlInfo($break));

    expect($package)->toBeNull();
})->with(['unix line endings' => ["\n"], 'windows line endings' => ["\r\n"]]);

it('still reads an ssl backend that reports one', function (string $line, string $expected) {
    $info = "cURL support => enabled\nHost => x86_64-linux-gnu\n$line\nZLib Version => 1.3.1\nlibSSH Version => libssh2/1.11.1\n";

    expect(curlSslPackage(composerPatches()['composer-12615']['replace'], $info))->toBe($expected);
})->with([
    'openssl' => ['SSL Version => OpenSSL/3.5.4', 'lib-curl-openssl'],
    'libressl' => ['SSL Version => LibreSSL/3.3.6', 'lib-curl-libressl'],
    // The case upstream did fix, in the commit that closed the issue this patch is for.
    // The patch must not take it back away.
    'securetransport with libressl' => ['SSL Version => (SecureTransport) LibreSSL/2.8.3', 'lib-curl-(securetransport) libressl'],
]);

it('reads the same version the unpatched pattern reads', function (string $break) {
    // The version capture is untouched, so whatever upstream made of a working platform
    // is what this still makes of it — carriage return and all, on a CRLF host.
    $patch = composerPatches()['composer-12615'];
    $info = "SSL Version => OpenSSL/3.5.4{$break}ZLib Version => 1.3.1{$break}";

    preg_match($patch['search'], $info, $before);
    preg_match($patch['replace'], $info, $after);

    expect($after['version'])->toBe($before['version'])
        ->and(trim($after['version']))->toBe('3.5.4');
})->with(['unix line endings' => ["\n"], 'windows line endings' => ["\r\n"]]);
