# HydePHP v3 project template

`composer create-project hyde/hyde` resolves the published Hyde project, which is still the
v2 line: v3 is unreleased and deliberately untagged. Creating a project from it would install
a v2 dependency graph, which is not proof of anything this CLI claims.

This is the source `hyde new --composer` is pointed at while that is true, through the
`HYDE_PROJECT_SOURCE` environment variable. It is the shape of a real Hyde Composer project —
its own `hyde` entry point, its own `app/`, its own dependency graph — with the framework and
the realtime compiler resolved from the `develop@master` monorepo instead of Packagist.

Nothing here ships in the executable. It exists so the test suite can create a Composer
project, run it, and assert that what came out is running v3.
