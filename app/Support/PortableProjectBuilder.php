<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Illuminate\Support\Str;

use function rtrim;
use function mkdir;
use function is_dir;
use function basename;
use function file_put_contents;

/**
 * Writes the file tree of a new Portable project.
 *
 * A Portable project is content and configuration only. Nothing here shells out,
 * nothing here needs PHP or Composer to be installed, and — by construction —
 * neither a `composer.json` nor a `vendor` directory is ever created.
 */
final class PortableProjectBuilder
{
    /** Directories every portable project starts with. */
    private const DIRECTORIES = ['_pages', '_posts', '_media', '_static'];

    public function __construct(
        private readonly string $path,
        private readonly ?string $name = null,
    ) {
        //
    }

    public function create(): void
    {
        // Nothing here goes through the framework: a new project must be creatable by an
        // executable that has not booted an application, in a directory that is not yet
        // a project, on a machine with no PHP and no Composer.
        foreach (self::DIRECTORIES as $directory) {
            $this->makeDirectory($this->path.'/'.$directory);
        }

        // Keep the empty directories in version control, which is what a user expects
        // after committing a freshly created site.
        $this->write('_posts/.gitkeep', '');
        $this->write('_media/.gitkeep', '');

        // The output directory is emptied completely before every build, so a file that
        // has to be present in the compiled site — a `CNAME`, a `.nojekyll`, a search
        // engine verification file — belongs in `_static`, which is copied to the site
        // root with its relative paths preserved.
        $this->write('_static/.gitkeep', '');

        $this->write('_pages/index.md', $this->homepage());
        $this->write('hyde.yml', $this->configuration());
        $this->write('.gitignore', $this->gitignore());
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create the directory $path.");
        }
    }

    private function write(string $relative, string $contents): void
    {
        $this->makeDirectory($this->path);

        if (@file_put_contents($this->path.'/'.$relative, $contents) === false) {
            throw new RuntimeException("Unable to write {$this->path}/$relative.");
        }
    }

    public function title(): string
    {
        return Str::headline($this->name ? basename(rtrim($this->name, '/\\')) : basename($this->path));
    }

    private function homepage(): string
    {
        $title = $this->title();

        return <<<MARKDOWN
        ---
        title: "$title"
        ---

        # Welcome to $title

        This site was created with the HydePHP CLI. It is a portable project:
        all the content lives in Markdown files, and everything else comes from
        the `hyde` executable, so there is nothing to install.

        Run `hyde serve` to preview it, and `hyde build` to compile it to `_site`.

        MARKDOWN;
    }

    private function configuration(): string
    {
        $title = $this->title();

        return <<<YAML
        # The Hyde configuration for this site.
        # See https://hydephp.com/docs for everything you can set here.

        name: "$title"

        YAML;
    }

    private function gitignore(): string
    {
        return <<<'TEXT'
        /_site
        /.hyde-cache

        TEXT;
    }
}
