<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Str;
use Hyde\Foundation\PharSupport;
use function Hyde\normalize_slashes;

/** @experimental */
class VendorPublishCommand extends \Illuminate\Foundation\Console\VendorPublishCommand
{
    /** @internal Provides Phar support */
    protected function publishItem($from, $to)
    {
        $from = Str::after(normalize_slashes($from), 'vendor/hyde/framework/');
        $from = PharSupport::vendorPath($from);

        parent::publishItem($from, $to);
    }
}
