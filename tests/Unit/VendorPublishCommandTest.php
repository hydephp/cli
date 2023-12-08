<?php /** @noinspection PhpIllegalPsrClassPathInspection */

use App\Commands\VendorPublishCommand;
use Illuminate\Filesystem\Filesystem;

it('rewrites vendor paths to lead to phar path', function () {
    $command = new VendorPublishCommandTestClass(new Filesystem());
    $path = 'vendor/hyde/framework/src/Commands/VendorPublishCommand.php';

    expect(($command)->normalizePath($path))->not()->toBe($path);
});

class VendorPublishCommandTestClass extends VendorPublishCommand
{
    public function normalizePath(string $from): string
    {
        return parent::normalizePath($from);
    }
}
