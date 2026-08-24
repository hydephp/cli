<?php

use Illuminate\Process\Factory;
use App\Commands\SelfUpdateCommand;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Process;
use App\Commands\Internal\Support\GitHubReleaseData;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

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

test('createIssueTemplateLink method builds issue URL', function () {
    mockContainerPath('foo');
    $class = new InspectableSelfUpdateCommand();
    $exception = new RuntimeException('Error message');

    $result = $class->createIssueTemplateLink($exception);

    expect($result)->toBeString()
        ->toStartWith('https://github.com/hydephp/cli/issues/new?title=')
        ->toContain(urlencode('Error while self-updating the application'))
        ->toContain(urlencode($class->stripPersonalInformation($class->getIssueMarkdown($exception))));
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

test('handle exception method with known error', function () {
    mockContainerPath('/home/foo/project');
    $class = new InspectableSelfUpdateCommand();
    $message = 'The application path is not writable. Please rerun the command with elevated privileges.';
    $exception = new RuntimeException($message, 0);

    $class->setProperty('output', Mockery::mock(OutputInterface::class, [
        'isVerbose' => false,
        'newLine' => null,
        'writeln' => null,
        'getFormatter' => Mockery::mock(OutputFormatterInterface::class, [
            'hasStyle' => false,
            'setStyle' => null,
        ]),
    ]));

    $class->output->shouldReceive('error')->once()->with($message);

    $class->handleException($exception);
});

test('handle exception method with unknown error', function () {
    mockContainerPath('/home/foo/project');
    $class = new InspectableSelfUpdateCommand();
    $exception = new RuntimeException('Error message');

    $class->setProperty('output', Mockery::mock(OutputInterface::class, [
        'isVerbose' => false,
        'newLine' => null,
        'writeln' => null,
        'getFormatter' => Mockery::mock(OutputFormatterInterface::class, [
            'hasStyle' => false,
            'setStyle' => null,
        ]),
    ]));

    $class->output->shouldReceive('error')->once()->with('Something went wrong while updating the application!');

    $class->handleException($exception);
});

test('handle exception method with unknown error throws when verbose', function () {
    mockContainerPath('/home/foo/project');
    $class = new InspectableSelfUpdateCommand();
    $exception = new RuntimeException('Error message');

    $class->setProperty('output', Mockery::mock(OutputInterface::class, [
        'isVerbose' => true,
        'newLine' => null,
        'writeln' => null,
        'getFormatter' => Mockery::mock(OutputFormatterInterface::class, [
            'hasStyle' => false,
            'setStyle' => null,
        ]),
    ]));

    $class->output->shouldReceive('error')->once()->with('Something went wrong while updating the application!');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Error message');

    $class->handleException($exception);
});

test('public key hash identifier', function () {
    $publicKey = (new InspectableSelfUpdateCommand())->publicKey();
    $identifier = strtoupper(substr(hash('sha256', $publicKey."\n"), 0, 40));

    // Expect to match https://trustservices.hydephp.com/certificates/EE5FC423177F61B096D768E3B3D3CA94C5435426.pem
    // See also mirror https://github.com/hydephp/certificates/tree/master/EE5FC423177F61B096D768E3B3D3CA94C5435426
    expect($identifier)->toBe('EE5FC423177F61B096D768E3B3D3CA94C5435426');
});

test('signature verification accepts a correctly signed artifact', function () {
    [$verifier, $directory] = signingFixture();

    expect($verifier->verify($directory.'/artifact', $directory.'/artifact.sig.bin'))->toBeTrue();
});

test('signature verification rejects an invalid signature', function () {
    [$verifier, $directory] = signingFixture();

    file_put_contents($directory.'/artifact.sig.bin', 'Invalid signature');

    expect($verifier->verify($directory.'/artifact', $directory.'/artifact.sig.bin'))->toBeFalse();
});

test('signature verification rejects an artifact that was tampered with after signing', function () {
    [$verifier, $directory] = signingFixture();

    file_put_contents($directory.'/artifact', 'Tampered payload');

    expect($verifier->verify($directory.'/artifact', $directory.'/artifact.sig.bin'))->toBeFalse();
});

test('signature verification rejects a signature made by a different key', function () {
    [$verifier, $directory] = signingFixture();

    [, $other] = signingFixture();

    copy($other.'/artifact.sig.bin', $directory.'/artifact.sig.bin');

    expect($verifier->verify($directory.'/artifact', $directory.'/artifact.sig.bin'))->toBeFalse();
});

test('get latest release information', function () {
    $class = new InspectableSelfUpdateCommand();
    $class->setProperty('releaseResponse', file_get_contents(__DIR__.'/../Fixtures/general/github-release-api-response.json'));

    $result = (array) $class->getLatestReleaseInformationFromGitHub();

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['tag', 'assets'])
        ->and($result['tag'])->toBe('v3.0.0')
        ->and($result['assets'])->toHaveKeys([
            'hyde-linux-x86_64', 'hyde-linux-arm64', 'hyde-macos-x86_64', 'hyde-macos-arm64', 'hyde-windows-x86_64.exe',
        ])
        ->and($result['assets'])->each->toHaveKeys(['name', 'url']);
});

test('every platform has a signature published alongside its artifact', function () {
    $class = new InspectableSelfUpdateCommand();
    $class->setProperty('releaseResponse', file_get_contents(__DIR__.'/../Fixtures/general/github-release-api-response.json'));

    $release = $class->getLatestReleaseInformationFromGitHub();

    foreach (App\Launcher\Platform::assetMap() as $slug => $asset) {
        $platform = platformFor($slug);

        expect($release->getAsset($platform->releaseAsset())->name)->toBe($asset)
            ->and($release->getAsset($platform->opensslSignatureAsset())->name)->toBe($asset.'.sig.bin');
    }
});

/**
 * Build a real RSA key pair and sign a file with it.
 *
 * This exercises the verification logic itself rather than a signature made by the
 * release key, which cannot be reproduced here. The identifier of the shipped
 * public key is pinned by its own test above.
 *
 * @return array{0: App\Support\SignatureVerifier, 1: string}
 */
function signingFixture(): array
{
    $directory = Tests\Support\TemporaryProject::directory('signing');

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    openssl_pkey_export($key, $private);

    $public = openssl_pkey_get_details($key)['key'];

    file_put_contents($directory.'/artifact', 'The artifact that would be downloaded.');

    openssl_sign(file_get_contents($directory.'/artifact'), $signature, $private, OPENSSL_ALGO_SHA512);

    file_put_contents($directory.'/artifact.sig.bin', $signature);

    return [new App\Support\SignatureVerifier($public), $directory];
}

/** Build a Platform instance for a canonical slug, so the asset map can be asserted end to end. */
function platformFor(string $slug): App\Launcher\Platform
{
    return match ($slug) {
        'linux-x86_64' => new App\Launcher\Platform('Linux', 'x86_64'),
        'linux-arm64' => new App\Launcher\Platform('Linux', 'aarch64'),
        'macos-x86_64' => new App\Launcher\Platform('Darwin', 'x86_64'),
        'macos-arm64' => new App\Launcher\Platform('Darwin', 'arm64'),
        'windows-x86_64' => new App\Launcher\Platform('Windows', 'AMD64'),
    };
}

test('print newline if verbose when verbose', function () {
    $class = new InspectableSelfUpdateCommand();
    $class->setProperty('output', Mockery::mock(OutputInterface::class));
    $class->output->shouldReceive('isVerbose')->andReturnTrue();
    $class->output->shouldReceive('writeln')->once()->with('');

    $class->printNewlineIfVerbose();
});

test('print newline if verbose when not verbose', function () {
    $class = new InspectableSelfUpdateCommand();
    $class->setProperty('output', Mockery::mock(OutputInterface::class));
    $class->output->shouldReceive('isVerbose')->andReturnFalse();
    $class->output->shouldNotReceive('writeln');

    $class->printNewlineIfVerbose();
});

test('debug helper prints debug when verbose', function () {
    $class = new InspectableSelfUpdateCommand();
    $class->setProperty('output', Mockery::mock(OutputInterface::class));
    $class->output->shouldReceive('isVerbose')->andReturnTrue();
    $class->output->shouldReceive('writeln')->once()->with('<fg=gray>DEBUG:</> Debug message');

    $class->debug('Debug message');
});

test('debug helper does not print debug when not verbose', function () {
    $class = new InspectableSelfUpdateCommand();
    $class->setProperty('output', Mockery::mock(OutputInterface::class));
    $class->output->shouldReceive('isVerbose')->andReturnFalse();
    $class->output->shouldNotReceive('writeln');

    $class->debug('Debug message');
});

test('determineUpdateStrategy method', function () {
    $command = new InspectableSelfUpdateCommand();

    $command->setProperty('applicationPath', '/usr/local/bin/hyde');
    $this->assertSame('direct', $command->determineUpdateStrategy());

    $command->setProperty('applicationPath', '/home/user/.config/composer/vendor/bin/hyde');
    $this->assertSame('composer', $command->determineUpdateStrategy());
});

test('Composer process', function () {
    Process::swap(new Factory());
    Process::fake();

    $command = new InspectableSelfUpdateCommand();
    $command->setProperty('output', Mockery::mock(OutputInterface::class, [
        'isVerbose' => false,
        'writeln' => null,
    ]));

    [$exitCode, $output] = $command->runComposerProcess();

    expect($exitCode)->toBeInt()->toBe(0)
        ->and($output)->toBeArray()->toBeEmpty();

    Process::assertRan('composer global require hyde/cli');
})->skipOnWindows();

test('Windows Composer update process', function () {
    Process::swap(new Factory());
    Process::fake();

    $command = new InspectableSelfUpdateCommand();
    $command->setProperty('output', Mockery::mock(OutputInterface::class, [
        'isVerbose' => false,
        'writeln' => null,
    ]));

    $exitCode = $command->runComposerWindowsProcess();
    expect($exitCode)->toBeInt()->toBe(0);

    // We need to assemble the command here as it may be escaped differently on different systems
    Process::assertRan(sprintf('powershell -Command "%s"', sprintf("Start-Process -Verb RunAs powershell -ArgumentList '-Command %s'", escapeshellarg('composer global require hyde/cli'))));
});

test('failing Windows Composer update process', function () {
    $command = sprintf('powershell -Command "%s"', sprintf("Start-Process -Verb RunAs powershell -ArgumentList '-Command %s'", escapeshellarg('composer global require hyde/cli')));

    Process::swap(new Factory());
    Process::fake([
        $command => Process::result(
            output: 'Test output',
            errorOutput: 'Test error output',
            exitCode: 1,
        ),
    ]);

    $command = new InspectableSelfUpdateCommand();
    $command->setProperty('output', Mockery::mock(OutputInterface::class, [
        'isVerbose' => false,
        'writeln' => null,
    ]));

    $exitCode = $command->runComposerWindowsProcess();
    expect($exitCode)->toBeInt()->toBe(1);
});

/**
 * @noinspection PhpIllegalPsrClassPathInspection
 *
 * @method GitHubReleaseData getLatestReleaseInformationFromGitHub()
 * @method string makeGitHubApiResponse()
 * @method string getUserAgent()
 * @method array parseVersion(string $semver)
 * @method int compareVersions(array $currentVersion, array $latestVersion)
 * @method string findApplicationPath()
 * @method void printVersionStateInformation(int $state)
 * @method void updateApplication(string $strategy)
 * @method string determineUpdateStrategy()
 * @method void updateDirectly()
 * @method void downloadFile(string $url, string $destination)
 * @method bool verifySignature(string $phar, string $signature)
 * @method void replaceApplication(string $downloadedFile)
 * @method void moveFile(string $downloadedFile, string $applicationPath)
 * @method void updateViaComposer()
 * @method array runComposerProcess()
 * @method int runComposerWindowsProcess()
 * @method void debug(string $message)
 * @method void printNewlineIfVerbose()
 * @method void handleException(Throwable $exception)
 * @method string createIssueTemplateLink(Throwable $exception)
 * @method string buildUrl(string $url, array $params)
 * @method string getDebugEnvironment()
 * @method string getIssueMarkdown(Throwable $exception)
 * @method string stripPersonalInformation(string $markdown)
 * @method string publicKey()
 */
class InspectableSelfUpdateCommand extends SelfUpdateCommand
{
    public $output;

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
        if (! method_exists($this, $method)) {
            throw new BadMethodCallException("Method [$method] does not exist.");
        }

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
