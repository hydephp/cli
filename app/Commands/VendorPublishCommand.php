<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Str;
use Hyde\Foundation\PharSupport;
use Illuminate\Foundation\Console\VendorPublishCommand as BaseVendorPublishCommand;
use function Hyde\normalize_slashes;

/** @experimental */
class VendorPublishCommand extends BaseVendorPublishCommand
{
    /** @internal Provides Phar support */
    protected function publishItem($from, $to): void
    {
        $from = Str::after(normalize_slashes($from), 'vendor/hyde/framework/');
        $from = PharSupport::vendorPath($from);

        parent::publishItem($from, $to);
    }
}
