<?php /** @noinspection PhpIllegalPsrClassPathInspection */

use Hyde\Foundation\HydeKernel;
use App\Commands\PharServeCommand;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const HYDE_WORKING_DIR= '/path/to/working/dir';
const HYDE_TEMP_DIR= '/path/to/temp/dir';

test('getExecutablePath method returns live server path when not running in Phar', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));

    $command = Mockery::mock(TestablePharServeCommand::class)->makePartial();

    $command->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('isPharRunning')->once()->andReturnFalse();

    $path = realpath($command->getExecutablePath());

    expect($path)->not()->toBeFalse()
        ->and($path)->toBe(realpath(__DIR__ . '/../../bin/test-server.php'));
});

test('getExecutablePath method extracts server executable when running in Phar', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));
    File::shouldReceive('exists')->twice()->andReturnFalse();

    $command = Mockery::mock(TestablePharServeCommand::class)->makePartial();

    $command->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('isPharRunning')->once()->andReturnTrue();
    $command->shouldReceive('extractServerFromPhar')->once();

    expect($command->getExecutablePath())->toBe('/path/to/temp/dir/bin/server.php');
});

test('getExecutablePath method uses existing default executable when available', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));
    File::shouldReceive('exists')->once()->andReturnTrue();

    $command = Mockery::mock(TestablePharServeCommand::class)->makePartial();

    $command->shouldAllowMockingProtectedMethods();

    $command->shouldReceive('isPharRunning')->once()->andReturnTrue();
    $command->shouldNotReceive('extractServerFromPhar');

    expect($command->getExecutablePath())->toBe('/path/to/working/dir/vendor/hyde/realtime-compiler/bin/server.php');
});

test('getExecutablePath method uses cached extracted executable when available', function () {
    HydeKernel::setInstance(new HydeKernel(HYDE_WORKING_DIR));
    File::shouldReceive('exists')->once()->andReturnFalse();
    File::shouldReceive('exists')->once()->andReturnTrue();

    $command = Mockery::mock(TestablePharServeCommand::class)->makePartial();

    $command->shouldAllowMockingProtectedMethods();

    $command->shouldReceive('isPharRunning')->once()->andReturnTrue();
    $command->shouldNotReceive('extractServerFromPhar');

    expect($command->getExecutablePath())->toBe('/path/to/temp/dir/bin/server.php');
});

it('merges in environment variables', function () {
    expect((new TestablePharServeCommand())->getEnvironmentVariables())->toBe([
        'HYDE_SERVER_REQUEST_OUTPUT' => false,
        'HYDE_PHAR_PATH' => 'false',
        'HYDE_BOOTSTRAP_PATH' => realpath(__DIR__ . '/../../app/bootstrap.php'),
        'HYDE_WORKING_DIR' => '/path/to/working/dir',
        'HYDE_TEMP_DIR' => '/path/to/temp/dir',
    ]);
});

class TestablePharServeCommand extends PharServeCommand
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
