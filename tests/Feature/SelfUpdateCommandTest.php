<?php

/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
/** @noinspection PhpUnnecessaryLocalVariableInspection */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Hyde\Foundation\HydeKernel;
use App\Commands\SelfUpdateCommand;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\BufferedConsoleOutput;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\ArrayInput;

// We want to run everything in a clean temporary directory
$path = __DIR__.'/../../vendor/.testing';

beforeEach(function () use ($path) {
    File::swap(new Filesystem());

    if (is_dir($path) && ! File::isEmptyDirectory($path)) {
        throw new RuntimeException('The directory already exists. Please remove it first.');
    } else {
        mkdir($path, 0777, true);
    }

    $mock = Mockery::mock(Container::class);
    $mock->shouldReceive('basePath')->andReturn($path);
    Container::setInstance($mock);
});

afterEach(function () use ($path) {
    // Clean up the temporary directory
    File::deleteDirectory($path);
});

test('handle when up to date', function () {
    $command = new MockSelfUpdateCommand();

    expect($command->handle())->toBe(0);

    $output = 'Checking for updates... You are already using the latest version (v1.0.0)';

    expect(trim($command->output->fetch()))->toBe($output);

    $this->assertTrue($command->madeApiRequest);

    $command->teardown($this);
});

test('handle when ahead of latest version', function () {
    $command = new MockSelfUpdateCommand('v1.0.1', 'v1.0.0');

    expect($command->handle())->toBe(0);

    $output = 'Checking for updates... You are using a development version (v1.0.1)';

    expect(trim($command->output->fetch()))->toBe($output);

    $this->assertTrue($command->madeApiRequest);

    $command->teardown($this);
});

test('handle when behind latest version', function () {
    $command = new MockSelfUpdateCommand('v1.0.0', 'v1.2.3');
    $command->mockApiResponse('https://github.com/hydephp/cli/releases/download/v1.2.3/hyde', '<?php echo "Hyde v1.2.3";');
//    $command->mockApiResponse('https://github.com/hydephp/cli/releases/download/v1.2.3/signature.bin', 'signature');
    app()->shouldReceive('make')->with('config', [])->andReturn(new \Illuminate\Config\Repository([
        'app.openssl_verify' => false,
    ]));

    expect($command->handle())->toBe(0);

    $output = 'Checking for updates... A new version is available (v1.0.0 -> v1.2.3)
Updating to the latest version...
Skipping signature verification as the OpenSSL extension is not available.
The application has been updated successfully.';

    expect(trim(HydeKernel::normalizeNewlines($command->output->fetch())))->toBe($output);

    $this->assertTrue($command->madeApiRequest);
    $this->assertTrue(File::exists(base_path().'/hyde.phar'));
    $this->assertSame('<?php echo "Hyde v1.2.3";', file_get_contents(base_path().'/hyde.phar'));

    $command->teardown($this);
});

test('handle when verbose', function () {
    $command = new MockSelfUpdateCommand();

    $command->makeVerbose();

    expect($command->handle())->toBe(0);

    $outputs = [
        'Checking for a new version...',
        'DEBUG: Application path: ',
        'DEBUG: Update strategy: Direct download',
        'DEBUG: Getting the latest release information from GitHub...',
        'DEBUG: Current version: v0.0.0',
        'DEBUG: Latest version: v0.0.0',
        'You are already using the latest version (v1.0.0)',
    ];

    $actual = trim($command->output->fetch());

    foreach ($outputs as $output) {
        expect($actual)->toContain($output);
    }

    $this->assertTrue($command->madeApiRequest);

    $command->teardown($this);
});

test('handle when checking for new updates', function () {
    $command = new MockSelfUpdateCommand('v1.0.0', 'v1.0.0', ['getOption' => true]);

    expect($command->handle())->toBe(0);

    $output = 'Checking for updates... You are already using the latest version (v1.0.0)';

    expect(trim($command->output->fetch()))->toBe($output);

    $this->assertTrue($command->madeApiRequest);

    $command->teardown($this);
});

test('handle catching exceptions', function () {
    $command = new MockSelfUpdateCommand('v1.0.0', 'v1.2.3');
    $command->shouldThrow(new RuntimeException('Something went wrong!'));

    expect($command->handle())->toBe(1);

    $output = 'Something went wrong while updating the application!';

    expect(trim($command->output->fetch()))->toContain($output);

    $this->assertTrue($command->madeApiRequest);

    $command->teardown($this);
});

test('GitHub API connection call', function () {
    $command = new MockSelfUpdateCommand();

    Http::swap(new Factory());

    Http::fake([
        'github.com/*' => Http::response(['foo' => 'bar'], 200, ['Headers']),
    ]);

    $response = $command->makeRealGitHubApiResponse();

    expect($response)->toBeString()->toBe('{"foo":"bar"}');

    $recorded = Http::recorded();

    /** @var Request $request */
    $request = $recorded[0][0];
    expect($request->url())->toBe('https://api.github.com/repos/hydephp/cli/releases/latest');
    expect($request->header('Accept'))->toBe(['application/vnd.github.v3+json']);
    expect($request->header('User-Agent'))->toBe(['HydePHP CLI updater vv1.0.0 (github.com/hydephp/cli)']); // Todo: Fix double vv bug in test setup
});

/** Class that uses mocks instead of making real API and binary path calls */
class MockSelfUpdateCommand extends SelfUpdateCommand
{
    /** @var MockBufferedOutput */
    public $output;

    protected string $appVersion;
    protected string $latestVersion;

    public bool $madeApiRequest = false;

    /** @var array<string, string> */
    protected array $responseMocks = [];

    protected bool $hasBeenTearedDown = false;
    protected ?int $exitedWithCode = null;
    protected Throwable $throw;

    public function __construct(string $mockAppVersion = 'v1.0.0', string $mockLatestVersion = 'v1.0.0', array $input = ['getOption' => false])
    {
        parent::__construct();

        $this->appVersion = $mockAppVersion;
        $this->latestVersion = $mockLatestVersion;

        $this->input = Mockery::mock(ArrayInput::class, $input);
        $this->output = new MockBufferedOutput();

        file_put_contents(base_path().'/hyde.phar', '<?php echo "Hyde '.$mockAppVersion.'";');
    }

    public function teardown(TestCase $test): void
    {
        $test->assertEmpty($this->responseMocks, 'Not all pending mock responses were used!');

        $this->hasBeenTearedDown = true;
    }

    public function makeVerbose(): void
    {
        $this->output->setVerbosity(MockBufferedOutput::VERBOSITY_VERBOSE);
    }

    public function shouldThrow(Throwable $exception): void
    {
        $this->throw = $exception;
    }
    
    public function mockApiResponse(string $url, string $contents): void
    {
        $this->responseMocks[$url] = $contents;
    }

    public function makeRealGitHubApiResponse(): string
    {
        return parent::makeGitHubApiResponse();
    }

    protected function findApplicationPath(): string
    {
        return realpath(base_path().'/hyde.phar');
    }

    protected function makeGitHubApiResponse(): string
    {
        $this->madeApiRequest = true;

        $contents = file_get_contents(__DIR__.'/../Fixtures/general/github-release-api-response.json');
        $contents = str_replace('v0.7.61', $this->latestVersion, $contents);

        return $contents;
    }

    protected function getAppVersion(): string
    {
        return $this->appVersion;
    }

    protected function downloadFile(string $url, string $destination): void
    {
        file_put_contents($destination, $this->responseMocks[$url] ?? throw new RuntimeException('No mock response for '.$url));

        unset($this->responseMocks[$url]);
    }

    protected function updateApplication(string $strategy): void
    {
        if (isset($this->throw)) {
            throw $this->throw;
        }

        parent::updateApplication($strategy);
    }

    protected function exit(int $exitCode): void
    {
        $this->exitedWithCode = $exitCode;
    }
}

/** Buffered output that "interacts" with IO {@see \Illuminate\Console\Concerns\InteractsWithIO} */
class MockBufferedOutput extends BufferedConsoleOutput
{
    public function error($string, $verbosity = null): void
    {
        $this->line($string, 'error', $verbosity);
    }

    public function line($string, $style = null, $verbosity = null): void
    {
        $styled = $style ? "<$style>$string</$style>" : $string;

        $this->writeln($styled, $this->parseVerbosity($verbosity));
    }

    public function newLine(int $count = 1): void
    {
        $this->write(str_repeat(PHP_EOL, $count));
    }

    protected function parseVerbosity($level = null)
    {
        if (isset($this->verbosityMap[$level])) {
            $level = $this->verbosityMap[$level];
        } elseif (! is_int($level)) {
            $level = $this->getVerbosity();
        }

        return $level;
    }
}
