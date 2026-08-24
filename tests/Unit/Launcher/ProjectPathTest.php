<?php

declare(strict_types=1);

use App\Launcher\Project;
use App\Launcher\ProjectDetector;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| The canonical path representation
|--------------------------------------------------------------------------
|
| Every path the launcher exposes — a project root, a working directory, a
| dispatch marker — is in one spelling, so that two references to the same
| directory are the same string. Windows is where this stops being free:
| the same directory reaches the CLI with either separator, with either
| case of drive letter, and with or without a trailing one.
|
*/

it('writes every separator the same way', function (string $input, string $expected) {
    expect(Project::normalize($input))->toBe($expected);
})->with([
    'posix' => ['/Users/emma/site', '/Users/emma/site'],
    'trailing separator' => ['/Users/emma/site/', '/Users/emma/site'],
    'several trailing separators' => ['/Users/emma/site///', '/Users/emma/site'],
    'windows separators' => ['C:\\Users\\emma\\site', 'C:/Users/emma/site'],
    'mixed separators' => ['C:\\Users/emma\\site', 'C:/Users/emma/site'],
    'repeated separators' => ['/Users//emma///site', '/Users/emma/site'],
    'relative' => ['docs/guides/', 'docs/guides'],
]);

it('keeps a root that is nothing but a separator', function (string $input, string $expected) {
    expect(Project::normalize($input))->toBe($expected);
})->with([
    'posix root' => ['/', '/'],
    'drive root' => ['C:\\', 'C:/'],
    'drive root, forward slash' => ['C:/', 'C:/'],
    'bare drive' => ['C:', 'C:/'],
]);

it('treats a drive letter as the drive, not as its spelling', function (string $input, string $expected) {
    expect(Project::normalize($input))->toBe($expected);
})->with([
    'lower case drive' => ['c:\\Users\\emma\\site', 'C:/Users/emma/site'],
    'lower case drive root' => ['d:/', 'D:/'],
    'lower case bare drive' => ['z:', 'Z:/'],
]);

it('keeps the two leading separators a UNC share is addressed by', function (string $input, string $expected) {
    // `//server/share` is not `/server/share` with a stray separator: the pair is the
    // root. Collapsing it would point the path at a local directory instead.
    expect(Project::normalize($input))->toBe($expected);
})->with([
    'unc share' => ['\\\\server\\share\\site', '//server/share/site'],
    'unc share, trailing separator' => ['\\\\server\\share\\site\\', '//server/share/site'],
    'unc share, forward slashes' => ['//server/share/site', '//server/share/site'],
    'unc root' => ['\\\\server\\share', '//server/share'],
]);

it('leaves dot segments to the filesystem', function () {
    // `..` cannot be resolved by rewriting the string: through a symlink it does not
    // mean the parent directory. Resolving it is canonicalize()'s job.
    expect(Project::normalize('/Users/emma/site/../other'))->toBe('/Users/emma/site/../other')
        ->and(Project::normalize('./site'))->toBe('./site');
});

it('leaves an empty path empty rather than calling it the root', function () {
    expect(Project::normalize(''))->toBe('')
        ->and(Project::canonicalize(''))->toBe('');
});

/*
|--------------------------------------------------------------------------
| Idempotence
|--------------------------------------------------------------------------
|
| This is the property the whole representation rests on. Every failure the
| Windows suite reported was an instance of it not holding: a path that had
| been through the launcher compared unequal to the path that went in.
|
*/

it('is already canonical once it has been canonicalized', function (string $input) {
    $once = Project::normalize($input);

    expect(Project::normalize($once))->toBe($once);
})->with([
    '/Users/emma/site/',
    'C:\\Users\\emma\\site\\',
    'c:/users/emma/site',
    '\\\\server\\share\\site',
    '/',
    'C:\\',
    '',
]);

it('hands back a real directory in the spelling it already had', function () {
    $path = TemporaryProject::portable();

    // The fixture path is created in canonical form, so a round trip through the
    // detector — which resolves it on disk — has to return it unchanged.
    expect(Project::canonicalize($path))->toBe($path)
        ->and((new ProjectDetector())->detect($path)->root)->toBe($path);
});

it('resolves the spellings of one directory onto one path', function () {
    $path = TemporaryProject::portable();

    // A trailing separator, a redundant dot segment and a detour through the parent
    // all name the same directory, and have to come back as the same string.
    expect(Project::canonicalize($path.'/'))->toBe($path)
        ->and(Project::canonicalize($path.'/.'))->toBe($path)
        ->and(Project::canonicalize($path.'/_pages/..'))->toBe($path);
});

it('falls back to the lexical rule for a path that is not on disk', function () {
    // A path the CLI is asked about need not exist yet — `hyde new` names one that does
    // not — so canonicalization must never depend on the directory being there.
    expect(Project::canonicalize('/no/such/directory/'))->toBe('/no/such/directory')
        ->and(Project::canonicalize('C:\\no\\such\\directory'))->toBe('C:/no/such/directory');
});
