# Working on the HydeCLI

Read [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) before changing anything in `app/Launcher`,
`bin/`, or `build/`. This file is the short version: the rules that must survive every change.

## Invariants

These are not preferences. A change that breaks one of them is a bug, whatever it fixes.

1. **Two project types, one detector.** Portable and Composer, modelled by `ProjectType` and
   resolved by `ProjectDetector`. Never add a file-existence check somewhere else to decide
   how a command should behave.
2. **No silent fallback from a broken Composer project to Portable.** A Hyde Composer project
   whose dependencies are missing must fail non-zero with a useful message. Building it with
   the embedded framework would compile the site against a different version of Hyde than the
   project declares. This is the single most important guarantee in the codebase.
3. **No external PHP in Portable mode.** `RuntimeManager` is the only thing that resolves a
   PHP binary, and it never looks at `PATH`. If you need to run PHP, ask it. That includes
   `hyde php` and `hyde composer`, which run the bundled runtime and nothing else.
4. **Composer is never invoked implicitly.** Nothing about building or serving a project
   runs it. It runs when the user asks for it and nowhere else: `hyde composer`, and
   `hyde new --composer`. Both use the Composer bundled in the executable, falling back to
   the host's only when none is bundled.
5. **No mixing of dependency graphs.** The embedded `vendor/` and a project's `vendor/` never
   share a process. Composer projects are dispatched into a separate process.
6. **Detection and dispatch run before the autoloader.** The `app/Launcher` classes are
   `require`d by explicit path from the `hyde` entry point. Keep them dependency-free: no
   Illuminate, no facades, no helper functions from the framework.
7. **No false-success subprocess handling.** Exit statuses are propagated, never swallowed.
   An unknown command is a failure, not a command listing.

## Layout

| Path | What lives there |
| --- | --- |
| `hyde` | The console entry point. Detection and dispatch happen here, first. |
| `app/Launcher/` | The project model, runtime management and dispatch. Plain PHP, no framework. |
| `app/Foundation/` | Overrides that let the framework boot out of a read-only executable. |
| `app/Commands/` | The commands the executable owns: `info`, `new`, `serve`, `self-update`, and the bundled programs `php` and `composer`. |
| `app/Support/` | Small helpers with no framework dependencies. |
| `bin/` | The build scripts. `build-native.sh` and `build-native.ps1` drive static-php-cli; `build-phar.php` assembles the executable. |
| `build/runtime.json` | The single build configuration: pinned PHP version, the extension set with a reason for each, and the pinned Composer release with its checksum. |
| `tests/System/` | Runtime acceptance in POSIX shell and PowerShell, for hosts with no PHP. |

## Testing

```bash
bin/sync-develop.sh                          # step zero: the v3 monorepo is a build input
composer install
vendor/bin/pest --testsuite=Unit,Feature     # no artifact needed

bin/build-native.sh                          # builds builds/hyde-<platform>
vendor/bin/pest                              # adds the Integration suite

# The Composer fixture needs its own dependency graph installed once:
composer install --working-dir=tests/Fixtures/composer-project
```

HydePHP v3 is unreleased and untagged. `hyde/framework` and `hyde/realtime-compiler` are
resolved from a sibling `develop@master` checkout through Composer path repositories.

`HydeKernel::VERSION` reads `3.0.0-dev`, but it read `2.0.3` on both lines for most of v3's
development, and a constant is a claim rather than evidence either way — so **no test may
rest on a version string alone to establish which framework is embedded**. Use behaviour
that differs; `tests/Feature/EmbeddedFrameworkIsV3Test.php` is where those live.
`bin/verify-v3-graph.php` fails the build if the graph is not v3.

Updating the `develop` checkout does not refresh `vendor/`: path packages are mirrored, and
Composer sees no version change. Run `composer reinstall hyde/framework hyde/realtime-compiler`.

Fixture projects must be created **outside** the repository. This repository is itself a
Hyde Composer project, so a fixture placed inside it with no portable marker of its own is
correctly attributed to the CLI's `composer.json` by the detector's upwards search. Use
`Tests\Support\TemporaryProject`.

The one fixture that lives in the tree, `tests/Fixtures/composer-project`, is deliberate: it
declares Hyde itself, so detection stops there. Its `vendor/` is gitignored.

## Adding an extension to the runtime

Add it to `build/runtime.json` **with the reason it is needed**, then rebuild. Do not enable
static-php-cli's default set: every extension in the executable should be traceable to a
framework requirement or to a test that proves it necessary.

## Things that are deliberately not implemented

- `hyde eject`, and Portable to Composer conversion.
- Hybrid autoloading, or loading Composer addons into a Portable project.

<!-- This file is a copy of AGENTS.md, kept so both conventions find the same guidance. -->
