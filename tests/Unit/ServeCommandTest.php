<?php /** @noinspection PhpIllegalPsrClassPathInspection */

use App\Commands\ServeCommand;
use Hyde\Foundation\HydeKernel;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const HYDE_WORKING_DIR = '/path/to/working/dir';
const HYDE_TEMP_DIR = '/path/to/temp/dir';

test('getExecutablePath method proxies server executable', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));
    File::shouldReceive('exists')->twice()->andReturnFalse();
    File::shouldReceive('ensureDirectoryExists')->once()->with('/path/to/temp/dir/bin');
    File::shouldReceive('put')->once()->withArgs(function ($path, $contents) {
        expect($path)->toBe('/path/to/temp/dir/bin/server.php')
            ->and($contents)->toContain("putenv('HYDE_AUTOLOAD_PATH=phar://hyde.phar/vendor/autoload.php')");

        return true;
    });

    $command = Mockery::mock(TestableServeCommand::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();

    expect($command->getExecutablePath())->toBe('/path/to/temp/dir/bin/server.php');
});

test('getExecutablePath method uses existing default executable when available', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));
    File::shouldReceive('exists')->once()->andReturnTrue();

    $command = Mockery::mock(TestableServeCommand::class)->makePartial();

    $command->shouldAllowMockingProtectedMethods();

    $command->shouldNotReceive('proxyPharServer');

    expect($command->getExecutablePath())->toBe('/path/to/working/dir/vendor/hyde/realtime-compiler/bin/server.php');
});

test('getExecutablePath method uses cached executable proxy when available', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));
    File::shouldReceive('exists')->once()->andReturnFalse();
    File::shouldReceive('exists')->once()->andReturnTrue();

    $command = Mockery::mock(TestableServeCommand::class)->makePartial();

    $command->shouldAllowMockingProtectedMethods();

    expect($command->getExecutablePath())->toBe('/path/to/temp/dir/bin/server.php');
});

it('merges in environment variables', function () {
    expect((new TestableServeCommand())->getEnvironmentVariables())->toBe([
        'HYDE_SERVER_REQUEST_OUTPUT' => false,
        'HYDE_PHAR_PATH' => realpath(__DIR__.'/../../builds/hyde') ?: 'false',
        'HYDE_BOOTSTRAP_PATH' => realpath(__DIR__.'/../../app/bootstrap.php'),
        'HYDE_WORKING_DIR' => '/path/to/working/dir',
        'HYDE_TEMP_DIR' => '/path/to/temp/dir',
    ]);
});

class TestableServeCommand extends ServeCommand
{
    public function __construct()
    {
        parent::__construct();

        $this->input = tap(Mockery::mock(ArrayInput::class), function ($mock) {
            $mock->shouldReceive('getOption')->with('no-ansi')->andReturn('false');
            $mock->shouldReceive('getOption')->with('host')->andReturn('localhost');
            $mock->shouldReceive('getOption')->with('port')->andReturn(8080);
            $mock->shouldReceive('getOption')->with('save-preview')->andReturnNull();
            $mock->shouldReceive('getOption')->with('dashboard')->andReturnNull();
            $mock->shouldReceive('getOption')->with('pretty-urls')->andReturnNull();
            $mock->shouldReceive('getOption')->with('play-cdn')->andReturnNull();
        });
        $this->output = new BufferedOutput();
    }

    public function getEnvironmentVariables(): array
    {
        return parent::getEnvironmentVariables();
    }
}
