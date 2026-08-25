<?php

declare(strict_types=1);

namespace App\Foundation;

use Hyde\Hyde;
use Hyde\Foundation\Kernel\Filesystem;
use Illuminate\Support\Collection;

use function file_exists;
use function in_array;

/** Exposes the bundled stylesheet as a normal Portable media asset. */
final class PortableFilesystem extends Filesystem
{
    public function findFiles(string $directory, string|array|false $matchExtensions = false, bool $recursive = false): Collection
    {
        $files = parent::findFiles($directory, $matchExtensions, $recursive);
        $mediaDirectory = Hyde::getMediaDirectory();
        $stylesheet = $mediaDirectory.'/app.css';

        if ($directory === $mediaDirectory
            && ! file_exists(Hyde::path($stylesheet))
            && ! $files->contains($stylesheet)
            && ($matchExtensions === false || in_array('css', (array) $matchExtensions, true))) {
            $files->push($stylesheet);
        }

        return $files->sort()->values();
    }
}
