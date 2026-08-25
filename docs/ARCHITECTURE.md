# HydeCLI Architecture

This document describes what the `hyde` executable is, the two kinds of project it knows
how to run, and how the native build is put together. It is the reference for the
invariants the code is written to preserve; if a change would break one of them,
the change is wrong.

## The project model

There are exactly **two** kinds of project. This is a first-class concept in the code,
modelled by [`App\Launcher\ProjectType`](../app/Launcher/ProjectType.php) and resolved by
[`App\Launcher\ProjectDetector`](../app/Launcher/ProjectDetector.php). It is never a
scattered set of file-existence checks.

### Portable project

```
_pages/
_posts/
_media/
hyde.yml     # optional
```

No `composer.json`, no `vendor/`, no external PHP. The framework, the runtime, and every
dependency come from the executable. The current working directory *is* the site root:
no project skeleton is generated, no temporary vendor tree is created, and Composer is
never invoked at any point in a build.

A Portable project cannot load Composer addons. There is no hybrid autoloading, and a
local `vendor/autoload.php` is never merged into the embedded dependency graph.

### Composer project

```
composer.json    # declares Hyde
composer.lock
vendor/
hyde
app/
config/
_pages/
```

The project owns its dependency graph: its own `vendor/autoload.php`, its own Hyde
version, its own addons. The executable supplies the PHP runtime and acts as a launcher
only. It starts a **separate process** running the project's own `hyde` entry point
against the project's own autoloader, and returns that process's exit status unchanged.

The two dependency graphs never share a process.

## Detection

`ProjectDetector::detect()` walks upwards from the invocation directory. For each
directory, in order:

1. A `composer.json` that **actually declares Hyde** makes it a Composer project root.
2. Otherwise, a portable marker (`_pages`, `_posts`, `_docs`, `_media`, `hyde.yml`,
   `hyde.yaml`) makes it a Portable project root, and stops the search.
3. Otherwise the search continues with the parent directory.

Portable is the fallback when nothing matches.

Rule 2 exists so that a portable site checked out *inside* another PHP project is not
attributed to that project's manifest. Rule 1 comes first so that a Composer project's
own root — which has both a manifest and content directories — resolves as Composer.

### What counts as declaring Hyde

Only the `require` and `require-dev` maps are inspected, and only for `hyde/framework`
and `hyde/hyde`. A mention of Hyde in a description, in `keywords`, in a script, or in
`extra` carries no weight: any project may reference Hyde in passing. Satellite packages
such as `hyde/realtime-compiler` are not sufficient evidence on their own.

Package names are compared case-insensitively, as Composer treats them.

### When things are broken

- **A Hyde Composer project with no `vendor/autoload.php`** fails with a message naming
  the missing file and the command that fixes it, and exits non-zero. It is *never*
  built with the embedded framework. Doing so would compile the site with a different
  version of Hyde than the project declares.
- **An unparseable `composer.json`** is a hard failure rather than a guess. Hyde cannot
  tell whether it is looking at a Hyde project, so it says so instead of choosing.
- **A `hyde` entry point that is not a PHP script** — most likely the executable itself,
  copied into a project — is refused. Treating it as the project's entry point would
  boot the embedded framework against a Composer project.

## Dispatch and the launcher

Detection and dispatch happen in [`App\Launcher\Launcher`](../app/Launcher/Launcher.php),
called from the [`hyde`](../hyde) entry point **before** the embedded autoloader is
registered. The launcher classes are `require`d by explicit path for exactly that
reason, and depend on nothing but the PHP standard library.

Some commands belong to the executable rather than to a project, and are answered even
inside a Composer project. They come in two kinds, and the difference is what answers them.

**Answered by the embedded application** — `Launcher::LAUNCHER_COMMANDS`:

| Command | Why |
| --- | --- |
| `new` | It creates a project that does not exist yet. |
| `info` | It reports on the environment, including which project it found. |
| `self-update` | It updates the executable itself. |

When one of these runs inside somebody else's Composer project, the embedded application is
pointed at a scratch directory rather than at the project root, so it can never read that
project's configuration or discover that project's packages.

**Answered by the launcher, booting nothing** — `Launcher::RUNTIME_COMMANDS`:

| Command | Why |
| --- | --- |
| `php` | It runs the PHP CLI bundled in the executable. |
| `composer` | It runs the Composer bundled in the executable, on that runtime. |

Neither falls back to a program found on the search path: they mean *the ones Hyde
supplies*. A source checkout embeds neither, so `hyde php` there runs the PHP process
already running the code and `hyde composer` fails, telling the developer to run their
own. `hyde new --composer` is the one place that does fall back to a host Composer,
because it needs some Composer to create a project at all.

Everything else in a Composer project is dispatched into that project.

### The bundled programs

The executable carries a complete PHP CLI and a Composer, because it needs both: one to
serve a site and to run a project's own entry point, the other to install a project's
dependencies. `hyde php` and `hyde composer` hand them to the user as well.

[`App\Launcher\RuntimeDispatcher`](../app/Launcher/RuntimeDispatcher.php) runs them, and it
is the *launcher* that calls it, for two reasons that are not stylistic:

- **Arguments have to arrive exactly as typed.** A console application claims `-v`,
  `--version` and `--help` for itself before any command runs, so `hyde php -v` answered by
  a console command could not print PHP's version. The launcher takes everything after the
  command name — after the command, not after the program, so `hyde -v php -r '...'` does
  not leak the CLI's own option into PHP.
- **`hyde composer install` has to work where nothing else does.** A Composer project with
  no `vendor/` is the state the launcher refuses to run anything in, and this is the
  command that repairs it. So it is answered *before* the project is detected at all.

`App\Commands\PhpCommand` and `App\Commands\ComposerCommand` are registered all the same,
so `hyde list` and `hyde help php` have something to describe. They are not the shipped
execution path, and the tests exercise the launcher rather than them.

The command list renders these, and the launcher-owned commands above, in a section of
their own, with membership read from `Launcher::ownedCommands()` — the list cannot come to
disagree with the routing it describes.

### Self-dispatch

The CLI's own source checkout is itself a Hyde Composer project whose `hyde` file is the
script being run. Dispatching there would relaunch the same script forever, so the
launcher recognises that case and boots the application it already is.

This applies **only** to a plain source checkout. A packaged executable is never treated
as a project's own entry point, because it can be copied to `./hyde` and that would
reopen the silent-fallback hole.

## The native executable

The `hyde` executable is a [static-php-cli](https://static-php.dev) **micro SAPI** binary
with the application archive appended to it:

```
hyde  =  micro.sfx  ++  hyde.phar
                        ├── hyde                  (the entry point)
                        ├── app/                  (the launcher and the application)
                        ├── config/
                        ├── vendor/               (the embedded dependency graph)
                        └── runtime/
                            ├── php.gz            (a full static PHP CLI, gzipped)
                            ├── composer.phar.gz  (Composer, patched and gzipped)
                            └── runtime.json      (versions, platform, checksums, patches, offset)
```

`bin/build-native.sh` (POSIX) and `bin/build-native.ps1` (Windows) drive static-php-cli;
`bin/build-phar.php` assembles the archive and concatenates it onto the micro binary.

### Why a second PHP is embedded

The micro SAPI can run the embedded application, but it is not a general CLI SAPI: it has
no built-in web server, so it cannot serve `php -S`, and it cannot run another project's
entry point in a clean process. Both of those are core requirements, so a complete static
PHP CLI is shipped inside the archive as a runtime resource.

[`App\Launcher\RuntimeManager`](../app/Launcher/RuntimeManager.php) extracts it on demand
to a versioned per-user cache:

```
~/.cache/hyde/runtime/<php-version>/<platform>/php
```

`XDG_CACHE_HOME` is honoured, Windows uses `%LOCALAPPDATA%`, and `HYDE_CACHE_DIR`
overrides the root outright. Extraction verifies the SHA-256 recorded at build time
against the *decompressed* binary, reuses a valid extraction, repairs a corrupted or
truncated one, and installs through an atomic rename. On Windows a locked target is moved
aside rather than overwritten in place.

**The RuntimeManager never resolves `php` from `PATH`.** The only interpreter it will use
besides the embedded one is the CLI process that is already running the code, which only
happens in a source checkout, where no embedded runtime exists.

### Why Composer is embedded too

The CLI could always *run* a Composer project on a machine with no PHP. Installing that
project's dependencies still needed a Composer that machine did not have, which left the
promise one step short. So the build embeds one.

`build/runtime.json` pins the version to bundle and the SHA-256 it must hash to, taken
from the checksum getcomposer.org publishes alongside that release. Both build scripts
download that version and verify it against the pinned checksum before it goes anywhere
near the archive — on every build, cached download included, so the pin rather than the
download is what decides. It is then extracted like the runtime, verified against the
checksum of the decompressed file, and cached under its own version rather than under
the platform:

```
~/.cache/hyde/composer/<composer-version>/composer.phar
```

Composer is a PHAR, so it is never executed on its own: the bundled runtime is named as the
program and Composer as its first argument. That is also what decides which PHP an install
runs against — the one this executable ships, never whatever a shebang would find.

Two extensions are in the runtime for this, and the acceptance suites assert both are
present in the artifact rather than trusting the build configuration:

- `ext-zip`. Without it Composer falls back to an `unzip` binary and then to `git`, and a
  machine that installed Hyde to avoid installing PHP cannot be assumed to have either:
  `composer install` fails outright there.
- `ext-session`. Composer resolves platform requirements against the runtime it is
  running on, and `illuminate/session` — reached from `hyde/framework` through
  `hydephp/torchlight-commonmark`, `torchlight/torchlight-laravel` and `illuminate/http`
  — requires it. Without it every project `hyde new --composer` creates is unresolvable.

**The executable distributes Composer, so the pin is a supply-chain decision, not a
convenience.** Keep it current with upstream's security releases; read the release notes
and confirm a project can still be created before bumping it.

### The patch carried against Composer

The bundled archive is not always the published one byte for byte.
[`bin/lib/composer-patches.php`](../bin/lib/composer-patches.php) carries the minimum
change needed where a Composer bug stops the CLI working on one of its platforms; the
build applies it *after* verifying the download against the pinned checksum, and records
the result:

```
published composer.phar  ──verified against the pin──▶  patched  ──▶  hashed into runtime.json
      (provenance)                                                    (what runs, verified
                                                                       on every extraction)
```

So the manifest carries both checksums — upstream's and ours — and `hyde info -v` names
every patch applied. Nothing about what is shipped is left to be discovered.

One patch exists today. Composer parses curl's SSL backend with `[^/]+`, which matches
newlines, so a curl that reports `SSL Version => Schannel` — with no `/version` after it,
which is every static-php-cli Windows build — makes the capture run past the end of the
line to the next `/` further down the block. Composer then builds a platform package
called `lib-curl-schannel
ZLib Version => 1.3.2
libSSH Version => libssh2`, rejects it
as an invalid name, and aborts **every** dependency resolution. `hyde composer install`
and `hyde new --composer` were both dead on Windows; the Windows acceptance run is what
caught it.

The fix is one character class, so the library capture stops at a line boundary and the
Schannel line simply does not match — the right answer, since it advertises no version to
register. `ext-curl` and `lib-curl` are registered before that code runs and are
untouched. The version capture is left exactly as upstream wrote it: `.` cannot cross a
newline, so it was never part of the bug, and it absorbs the carriage return that lets
`$` match on a CRLF host.

Reported as [composer/composer#12615](https://github.com/composer/composer/issues/12615),
which was closed by a commit that fixed the *later* report on that issue — macOS
SecureTransport with LibreSSL, where a slash is present and only the naming was wrong —
and left the original Schannel case untouched.

**A patch is a liability, and is meant to be deleted.** The build fails if one no longer
matches exactly once, so a Composer bump cannot silently ship unpatched; the day a release
contains the fix, the entry goes and `tests/Unit/ComposerPatchTest.php` goes with it.
That test needs no Composer, no network and no artifact: it runs the pattern against the
extension info the platforms actually report, including the SecureTransport case upstream
did fix, which the patch must not take back away.

`hyde composer self-update` is refused. The bundled Composer is versioned with the
executable and verified on every run, so an update to the extracted copy would be
repaired away by the next command that needed it — silently, since repairing a copy that
fails verification is exactly what the extraction does.

### Finding the archive inside the executable

A plain PHP CLI cannot open the combined binary as a PHAR — the PHAR extension rejects the
native prefix. So `hyde serve` reads the archive from a copy extracted next to the PHP
runtime, and the CLI needs to know where the archive begins.

The build records `payload_offset` (the size of `micro.sfx`) in `runtime.json`. That
offset is a **hint, not a fact**: code signing, stripping, an embedded ini, or a Windows
resource section could all change the length of the prefix after the fact. The archive's
stub therefore opens with a fixed byte sequence, and the runtime verifies the offset
points at it. If it does not, the executable is scanned for the marker and the recovered
offset is used instead.

### macOS code signing

The combined executable inherits the ad-hoc signature the linker gave `micro.sfx`, which
no longer covers the file once the archive is appended. `codesign -v` therefore reports
"main executable failed strict validation" and `spctl` rejects the binary. Re-signing it
does not help: any signature over a Mach-O with trailing data fails the same check.

Two practical consequences:

- **The build writes the executable to a temporary file and renames it into place.** macOS
  caches its verdict for a binary against the inode, so overwriting an executable in place
  leaves the kernel killing every subsequent run of it with `SIGKILL`. This bites during
  development, not for users, who always get a fresh file.
- **A macOS artifact downloaded through a browser carries the quarantine attribute** and
  Gatekeeper will refuse it until it is removed with
  `xattr -d com.apple.quarantine hyde`. Downloading with `curl` does not set the
  attribute, which is why the documented install command uses it.

**This is an unresolved packaging limitation, not a settled architectural one.** What was
established here is narrow: an ad-hoc `codesign` over the finished binary does not make
`spctl` accept it, because the archive is trailing data on the Mach-O. That does not
establish that no signable packaging exists. Options that were not investigated include
appending the archive inside a Mach-O segment rather than past the end of the file,
signing with a Developer ID and submitting to the notary service to see what it actually
rejects, and shipping the artifact inside a signed `.pkg` or notarised `.dmg` while
keeping the executable itself a single file. Each is worth its own investigation before
the trade against the single-file guarantee is treated as settled.

### Where the framework comes from

HydePHP v3 is unreleased. It lives on the `master` branch of the
[`hydephp/develop`](https://github.com/hydephp/develop) monorepo, and Packagist has no 3.x
of any Hyde package. `HydeKernel::VERSION` reads `3.0.0-dev`, having been unbumped at
`2.0.3` for most of v3's development — during which it distinguished the two lines not at
all.

**A version constant states what code claims to be, not what it is.** It is checked, but
only alongside checks that cannot be satisfied by editing a string: where the package came
from, and whether the code carries v3's removals and additions.

The CLI therefore resolves `hyde/framework` and `hyde/realtime-compiler` from a local
checkout of the monorepo, through Composer path repositories — the same mechanism the
monorepo itself uses for its own packages:

```json
"repositories": [
    { "type": "path", "url": "../develop/packages/*", "options": { "symlink": false } }
]
```

`symlink: false` matters: the vendor directory is copied whole into the archive, so the
packages have to be real files rather than links into a checkout the executable will not
have.

This makes a sibling `develop@master` checkout a **build input**. `bin/sync-develop.sh`
clones or validates it, and refuses a checkout on `2.x`, which is the released v2 line.

Because a resolution that quietly reaches Packagist would produce an executable that
looks entirely correct — right version string, right command names for the most part —
the result is verified rather than assumed. [`bin/verify-v3-graph.php`](../bin/verify-v3-graph.php)
asserts that both packages came from a `path` dist at `dev-master`, that the framework
reports a 3.x version, that it carries v3's removals (`RebuildPageCommand`, `CodeblockFilepathProcessor`, the sitemap and
RSS post-build tasks) and its additions (Blade Blocks, the code block and terminal view
models). It runs from `bin/build-phar.php` as the executable is assembled, so a v2
fallback fails the build instead of shipping.

When v3 is tagged, the path repositories and the pinned `dev-master` constraints are
replaced by an ordinary `^3.0`, and `verify-v3-graph.php` and `sync-develop.sh` go away
with them.

#### `hyde new --composer` before the release

The command asks Composer for `hyde/hyde` at the executable's **own major**, derived from
`Application::APP_VERSION` rather than written out:

```
HydeCLI 3.x  ->  composer create-project hyde/hyde <name> ^3.0
HydeCLI 4.x  ->  composer create-project hyde/hyde <name> ^4.0
```

An unconstrained `create-project` would resolve to whatever is currently the latest
stable Hyde, which means a HydeCLI 3.x binary creating a Hyde 4 project on the day Hyde 4
is released — a project it cannot run, against a framework whose commands and
configuration it knows nothing about. Pinning the major keeps the executable and the
projects it creates in the same generation by construction.

Until v3 is tagged there is no published release in `^3.0` to create from, so the command
is pointed at a local v3 source through the `HYDE_PROJECT_SOURCE` environment variable,
which replaces the constraint and adds a path repository to the `create-project` call.
The variable is a development mechanism, is never set for a released executable, and is
the only thing that can move the command off Packagist.
`tests/Fixtures/project-template` is the source the suite uses; its manifest is generated,
and the tests that need it generate it or fail loudly rather than skipping.

### Runtime version and extensions

`build/runtime.json` is the single build configuration. It pins **PHP 8.4**, lists every
extension with the reason it is present, and pins the Composer release to bundle along
with the checksum it must hash to. Nothing is compiled that is not needed: static-php-cli's
default extension set is not used.

PHP 8.4 rather than 8.5 because 8.5 emits deprecation notices from dependencies in the
current release line (`PDO::MYSQL_ATTR_SSL_CA` in `laravel-zero/foundation`), and the
suite has not been demonstrated clean on it.

Two of the extensions cannot exist everywhere. `pcntl` and `posix` have never been part
of any PHP on Windows, and static-php-cli refuses to start a build that asks for one,
so `unsupported-extensions.windows` records them and both build scripts filter the
set against it. The Windows executable is the same build minus those two:
`symfony/process` and `symfony/console` take their Windows code paths, which
is what every PHP on Windows has always done.

### Supported compatibility range

| | Supported |
| --- | --- |
| Bundled PHP runtime | 8.4.x |
| PHP required on the user's machine | none |
| Bundled Composer | the release pinned in `build/runtime.json` |
| Composer required on the user's machine | none |
| Framework, Portable projects | the version embedded in the executable |
| Framework, Composer projects | whatever the project declares |
| Running the CLI from source | PHP 8.2 – 8.4 with the extensions in `build/runtime.json` |

The CLI version and the framework version a Composer project reports are expected to
differ. `hyde info` labels which is which, and where each came from.

### Release artifacts

```
hyde-linux-x86_64
hyde-linux-arm64
hyde-macos-x86_64
hyde-macos-arm64
hyde-windows-x86_64.exe
```

Each is published with a detached GPG signature (`<asset>.sig`) and an OpenSSL signature
(`<asset>.sig.bin`). `hyde self-update` resolves the asset for the running platform
through [`App\Launcher\Platform`](../app/Launcher/Platform.php) and verifies the OpenSSL
signature, since OpenSSL is bundled with the executable while GPG may not be installed.

## Testing

| Suite | What it proves | Needs |
| --- | --- | --- |
| `tests/Unit` | Detection, platform mapping, runtime extraction, dispatch guards | PHP |
| `tests/Feature` | The embedded application, booted against real project directories | PHP |
| `tests/Integration` | The built executable, run with PHP and Composer scrubbed from the environment | PHP + a built artifact |
| `tests/System/acceptance.sh` | The same acceptance criteria, in POSIX shell, for a container where PHP was never installed | `sh` and `curl` only |
| `tests/System/acceptance.ps1` | The Windows equivalent | PowerShell only |

CI is split along the same line. `tests.yml` and `build-native.yml` are Build CI: they may
install PHP, Composer and a compiler toolchain. `runtime.yml` is Runtime CI: it downloads
the artifacts and proves them on machines that have none of that, with the authoritative
Linux run happening inside a minimal container on a native runner.

`build-native.yml` builds **one** platform per call. A matrix job cannot be depended on leg
by leg, so a single build job would make every acceptance job wait for the slowest
platform and skip all of them when any one platform failed to build. `runtime.yml`
therefore calls it once per platform and pairs each build with its own acceptance
job, so the five chains stand or fall independently and each keeps its own row in
the checks list. The release pipelines want every artifact or none, and call
`build-native-all.yml`, which holds the only copy of the platform-to-runner map.

Fixture projects are always created **outside** the repository, because the repository is
itself a Hyde Composer project and detection would otherwise attribute a fixture with no
markers of its own to the CLI's `composer.json`.
