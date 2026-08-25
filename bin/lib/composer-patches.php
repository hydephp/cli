<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Patches applied to the bundled Composer
|--------------------------------------------------------------------------
|
| The executable ships Composer, so a bug that stops Composer working on one
| of our platforms stops the CLI working there. This is where we carry the
| minimum change needed, applied by bin/build-phar.php after the published
| archive has been verified against the checksum pinned in build/runtime.json
| and before it is embedded.
|
| Every patch here is a liability: it is divergence from a release everybody
| else audits, and it has to be re-checked against every Composer bump. Each
| one carries the upstream issue it is waiting on, and is deleted the day a
| release contains the fix.
|
| The build fails if a patch no longer applies exactly once, so a Composer
| version that moved the code cannot be shipped unpatched by accident.
|
*/

return [

    /*
     * Composer invents a platform package named after several lines of `php --ri curl`.
     *
     * `[^/]+` matches newlines, and a curl built against Schannel reports
     *
     *     SSL Version => Schannel
     *
     * with no `/version` after it. So the capture runs past the end of that line and
     * on to the next `/` in the block — the one in `libSSH Version => libssh2/1.11.1`
     * — and Composer builds a package called
     *
     *     lib-curl-schannel\nZLib Version => 1.3.2\nlibSSH Version => libssh2
     *
     * which fails its own name validation and aborts *every* dependency resolution.
     * Every Windows build of static-php-cli uses Schannel: it compiles curl with
     * `-DCURL_USE_SCHANNEL=ON -DCURL_USE_OPENSSL=OFF`, and that is not configurable.
     *
     * The fix is one character class: the library capture stops at a line boundary. The
     * Schannel line then simply does not match, which is the correct outcome — it
     * advertises no SSL library version, so there is no `lib-curl-<backend>` version to
     * register. `ext-curl` and `lib-curl` are both registered before this code runs and
     * are unaffected.
     *
     * The version capture is deliberately left as upstream wrote it. `.` already cannot
     * cross a newline, so it was never part of the bug — and it absorbs the carriage
     * return of a CRLF line, which is what lets `$` match there. Narrowing it to
     * `[^\r\n]+` looks tidier and stops the pattern matching CRLF input at all.
     *
     * Reported as composer/composer#12615, which was closed by 47cde53 — a commit that
     * fixed the *later* report on that issue (macOS SecureTransport with LibreSSL,
     * where a slash is present and only the naming was wrong) and left the original
     * Schannel case untouched. Still present on Composer's main branch.
     */
    'composer-12615' => [
        'file' => 'src/Composer/Repository/PlatformRepository.php',
        'search' => '{^SSL Version => (?<library>[^/]+)/(?<version>.+)$}im',
        'replace' => '{^SSL Version => (?<library>[^\r\n/]+)/(?<version>.+)$}im',
        'issue' => 'https://github.com/composer/composer/issues/12615',
        'summary' => "curl's SSL backend is parsed across line boundaries, which breaks every install on Windows",
    ],

];
