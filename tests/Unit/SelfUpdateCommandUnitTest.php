<?php

use App\Commands\SelfUpdateCommand;
use Illuminate\Container\Container;

$versions = [
    ['1.2.3', ['major' => 1, 'minor' => 2, 'patch' => 3]],
    ['2.5.0', ['major' => 2, 'minor' => 5, 'patch' => 0]],
    ['0.0.1', ['major' => 0, 'minor' => 0, 'patch' => 1]],
];

afterEach(function () {
    Mockery::close();
    Container::setInstance();
});

it('parses the version correctly', function ($input, $expectedOutput) {
    expect((new InspectableSelfUpdateCommand())->parseVersion($input))->toBe($expectedOutput);
})->with($versions);

it('returns an array with integer values', function ($input, $expectedOutput) {
    $result = (new InspectableSelfUpdateCommand())->parseVersion($input);

    expect($result)->toEqual($expectedOutput)
        ->and($result['major'])->toBeInt()
        ->and($result['minor'])->toBeInt()
        ->and($result['patch'])->toBeInt();
})->with($versions);

it('correctly compares versions', function ($currentVersion, $latestVersion, $expectedResult) {
    $class = new InspectableSelfUpdateCommand();

    $result = $class->compareVersions($class->parseVersion($currentVersion), $class->parseVersion($latestVersion));

    expect($result)->toBe($class->constants($expectedResult));
})->with([
    ['1.2.3', '1.2.3', 'STATE_UP_TO_DATE'],
    ['1.2.3', '2.0.0', 'STATE_BEHIND'],
    ['2.0.0', '1.2.3', 'STATE_AHEAD'],
]);

it('validates release data correctly', function () {
    $data = ['tag_name' => 'v1.0.0', 'assets' => [
        ['name' => 'hyde', 'browser_download_url' => 'https://example.com'],
        ['name' => 'hyde.sig', 'browser_download_url' => 'https://example.com']
    ]];

    (new InspectableSelfUpdateCommand())->validateReleaseData($data);

    // No exception thrown means validation passed
    expect(true)->toBeTrue();
});

it('throws exception if release data is invalid', function ($data) {
    $this->expectException(RuntimeException::class);

    (new InspectableSelfUpdateCommand())->validateReleaseData($data);
})->with([
    [[]], // Empty data
    [['tag_name' => 'v1.0.0']], // Missing assets key
    [['assets' => []]], // Empty assets array
    [['assets' => [['name' => 'invalid_name']]]], // Invalid asset name
]);

it('returns the correct application path', function () {
    $class = new InspectableSelfUpdateCommand();
    $result = $class->findApplicationPath();

    // Assertions for the application path
    expect($result)->toBeString()
        ->and($result)->not->toBeEmpty()
        ->and(file_exists($result))->toBeTrue();
});

test('get debug environment', function () {
    $class = new InspectableSelfUpdateCommand();
    $result = $class->getDebugEnvironment();

    expect($result)->toBeString()
        ->and($result)->not->toBeEmpty()
        ->and($result)->toContain('Application version: v')
        ->and($result)->toContain('PHP version:         v')
        ->and($result)->toContain('Operating system:    ');
});

it('strips personal information from markdown', function () {
    $user = getenv('USER') ?: getenv('USERNAME') ?: 'user';
    mockContainerPath("/home/$user/project");

    $class = new InspectableSelfUpdateCommand();
    $markdown = "Error occurred in /home/$user/project".DIRECTORY_SEPARATOR."file.php\nStack trace:\n/home/$user/project".DIRECTORY_SEPARATOR.'file.php:10';

    $result = $class->stripPersonalInformation($markdown);

    expect($result)->toBeString()
        ->and($result)->not->toContain($user)
        ->and($result)->not->toContain(base_path().DIRECTORY_SEPARATOR)
        ->and($result)->toContain('<USERNAME>');
});

it('strips personal and path information from markdown', function () {
    mockContainerPath('/home/foo/project');

    $class = new InspectableSelfUpdateCommand();
    $markdown = 'Error occurred in /home/foo/project'.DIRECTORY_SEPARATOR."file.php\nStack trace:\n/home/foo/project".DIRECTORY_SEPARATOR.'file.php:10';

    $result = $class->stripPersonalInformation($markdown);

    expect($result)->toBeString()
        ->and($result)->not->toContain('/home/foo/project')
        ->and($result)->not->toContain(base_path())
        ->and($result)->toContain('<project>');
});

it('does not modify markdown without personal information', function () {
    mockContainerPath('/home/foo/project');

    $class = new InspectableSelfUpdateCommand();
    $markdown = 'No personal information present.';

    $result = $class->stripPersonalInformation($markdown);

    // Assertions
    expect($result)->toBe($markdown);
});

test('get issue markdown method', function () {
    $class = new InspectableSelfUpdateCommand();
    $exception = new RuntimeException('Error message');

    $result = $class->getIssueMarkdown($exception);

    expect($result)->toBeString()
        ->and($result)->toContain('Description')
        ->and($result)->toContain('Error message')
        ->and($result)->toContain('Stack trace')
        ->and($result)->toContain('Environment')
        ->and($result)->toContain('Context');
});

test('public key hash identifier', function () {
    $publicKey = (new InspectableSelfUpdateCommand())->publicKey();
    $identifier = strtoupper(substr(hash('sha256', $publicKey."\n"), 0, 40));

    // Expect to match https://trustservices.hydephp.com/certificates/EE5FC423177F61B096D768E3B3D3CA94C5435426.pem
    // See also mirror https://github.com/hydephp/certificates/tree/master/EE5FC423177F61B096D768E3B3D3CA94C5435426
    expect($identifier)->toBe('EE5FC423177F61B096D768E3B3D3CA94C5435426');
});

test('signature verification', function () {
    $class = new InspectableSelfUpdateCommand();

    $phar = 'builds/hyde';
    $signature = 'builds/signature.bin';

    // Sanity check to ensure the files exist
    assert(file_exists($phar) && file_exists($signature), 'Phar and signature files must exist');

    expect($class->verifySignature($phar, $signature))->toBeTrue();
});

test('signature verification fails if signature is invalid', function () {
    $class = new InspectableSelfUpdateCommand();

    $phar = 'builds/hyde';
    $signature = 'builds/false-signature.bin';

    // Sanity check to ensure the file exists
    assert(file_exists($phar), 'Phar file must exist');

    file_put_contents($signature, 'Invalid signature');

    expect($class->verifySignature($phar, $signature))->toBeFalse();

    // Clean up
    unlink($signature);
});

test('get latest release information method', function () {
    $class = new InspectableSelfUpdateCommand();

    $result = $class->getLatestReleaseInformationFromGitHub();

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['tag_name', 'assets'])
        ->and($result['tag_name'])->toBeString()
        ->and($result['assets'])->toBeArray()
        ->and($result['assets'])->toHaveKeys(['name', 'browser_download_url']);
});

/** @noinspection PhpIllegalPsrClassPathInspection */
class InspectableSelfUpdateCommand extends SelfUpdateCommand
{
    public function __construct()
    {
        parent::__construct();

        $this->releaseResponse = file_get_contents(__DIR__.'/../Fixtures/general/github-release-api-response.json');
    }

    public function property(string $property): mixed
    {
        return $this->$property;
    }

    public function __call($method, $parameters)
    {
        return $this->$method(...$parameters);
    }

    public function constants(string $constant): mixed
    {
        return constant("self::$constant");
    }

    public function setProperty(string $property, mixed $value): void
    {
        $this->$property = $value;
    }
}

function mockContainerPath(string $path): void
{
    $mock = Mockery::mock(Container::class);
    $mock->shouldReceive('basePath')->andReturn($path);
    Container::setInstance($mock);
}
