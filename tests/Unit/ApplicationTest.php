<?php

use App\Application;
use Hyde\Foundation\Application as HydeApplication;

test('custom application extends Hyde application', function () {
    expect(new Application())->toBeInstanceOf(HydeApplication::class);
});

it('uses custom cached packages path', function () {
    expect((new Application())->getCachedPackagesPath())->toBe(HYDE_TEMP_DIR.'/app/storage/framework/cache/packages.php');
});

it('uses custom namespace', function () {
    expect((new Application())->getNamespace())->toBe('App');
});