<?php

declare(strict_types=1);

namespace App\Foundation;

use Hyde\Foundation\HydeKernel;

/** Hyde kernel used by the embedded application for Portable projects. */
final class PortableHydeKernel extends HydeKernel
{
    public function __construct(?string $basePath = null)
    {
        parent::__construct($basePath);
        $this->filesystem = new PortableFilesystem($this);
    }
}
