<?php

declare(strict_types=1);

namespace App\Actions;

use Hyde\Hyde;
use Illuminate\Support\Facades\File;
use Hyde\Framework\Actions\PostBuildTasks\GenerateBuildManifest as BaseGenerateBuildManifest;

use function dirname;

/**
 * Generates the build manifest in a directory that a Portable project actually has.
 *
 * The framework writes the manifest into `app/storage/framework/cache`, which only
 * exists in a Composer project. Portable projects get `.hyde-cache` instead, and
 * this task makes sure that directory exists before the manifest is written.
 *
 * @see \App\Foundation\LoadConfiguration::PORTABLE_MANIFEST_PATH
 */
class GenerateBuildManifest extends BaseGenerateBuildManifest
{
    public function handle(): void
    {
        File::ensureDirectoryExists(dirname($this->getManifestPath()));

        parent::handle();
    }
}
