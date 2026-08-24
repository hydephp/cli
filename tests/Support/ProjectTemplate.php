<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

use function dirname;
use function is_dir;
use function is_file;
use function realpath;
use function str_replace;
use function file_get_contents;
use function file_put_contents;

/**
 * Resolves the HydePHP v3 project template that `hyde new --composer` is created from.
 *
 * The template's manifest is generated rather than committed, because it names an
 * absolute path to the develop@master checkout: `composer create-project` copies the
 * template to an arbitrary directory before installing it, so a relative repository
 * path cannot survive the copy.
 *
 * Generating it is a build step, but a missing manifest must never quietly turn the
 * tests that use it into skips. Proving that a Composer project can be created and
 * dispatched into against the v3 graph is one of the guarantees this suite exists for,
 * and a green run that silently left it out is worse than a red one.
 */
final class ProjectTemplate
{
    /**
     * The template directory, with its manifest rendered.
     *
     * @throws \RuntimeException When the manifest is missing and cannot be generated.
     */
    public static function path(): string
    {
        $template = dirname(__DIR__).'/Fixtures/project-template';

        if (! is_file($template.'/composer.json')) {
            self::render($template);
        }

        return $template;
    }

    /** @throws \RuntimeException */
    private static function render(string $template): void
    {
        $packages = dirname(__DIR__, 3).'/develop/packages';

        if (! is_dir($packages)) {
            throw new RuntimeException(<<<'TEXT'

            The HydePHP v3 development fixture is missing.

            Run `bin/sync-develop.sh` before executing the integration suite. It checks out
            the develop@master monorepo the CLI is built against and renders the project
            template's manifest against it.

            This is not skipped, because the tests that need it are what prove a Composer
            project can be created and dispatched into against the v3 dependency graph.

            TEXT);
        }

        file_put_contents($template.'/composer.json', str_replace(
            '@DEVELOP_PACKAGES@',
            realpath($packages).'/*',
            (string) file_get_contents($template.'/composer.json.dist')
        ));
    }
}
