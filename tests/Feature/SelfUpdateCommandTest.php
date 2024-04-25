<?php

/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
/** @noinspection PhpUnnecessaryLocalVariableInspection */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Commands\SelfUpdateCommand;
use Illuminate\Support\Facades\File;
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

    public function __construct(string $mockAppVersion = 'v1.0.0', string $mockLatestVersion = 'v1.0.0')
    {
        parent::__construct();

        $this->appVersion = $mockAppVersion;
        $this->latestVersion = $mockLatestVersion;

        $this->input = Mockery::mock(ArrayInput::class, ['getOption' => false]);
        $this->output = new MockBufferedOutput();

        file_put_contents(base_path().'/hyde.phar', '<?php echo "Hyde '.$mockAppVersion.'";');
    }

    public function teardown(TestCase $test): void
    {
        $test->assertEmpty($this->responseMocks, 'Not all pending mock responses were used!');

        $this->hasBeenTearedDown = true;
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
