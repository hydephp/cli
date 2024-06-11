<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Str;
use Hyde\Foundation\PharSupport;
use Illuminate\Foundation\Console\VendorPublishCommand as BaseVendorPublishCommand;

use function Hyde\normalize_slashes;

/** @internal Provides Phar support */
class VendorPublishCommand extends BaseVendorPublishCommand
{
    /** @codeCoverageIgnore */
    protected function publishItem($from, $to): void
    {
        parent::publishItem($this->normalizePath($from), $to);
    }

    protected function normalizePath(string $from): string
    {
        return PharSupport::vendorPath(Str::after(normalize_slashes($from), 'vendor/hyde/framework/'));
    }
}
