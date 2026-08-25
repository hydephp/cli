<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application;
use App\Launcher\Launcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function putenv;
use function dirname;
use function set_error_handler;
use function restore_error_handler;
use function set_exception_handler;
use function restore_exception_handler;

/**
 * Boots the embedded application against a real project directory.
 *
 * This is the same path the executable takes for a Portable project, so a feature
 * test exercises the real console application rather than a stand-in for it.
 */
trait BootsApplication
{
    protected ?Application $app = null;

    protected BufferedOutput $console;

    /** @var callable|null */
    private $errorSentinel = null;

    /** @var callable|null */
    private $exceptionSentinel = null;

    protected function boot(string $workingDirectory): Application
    {
        Launcher::swap(null);

        putenv("HYDE_WORKING_DIR=$workingDirectory");

        $this->console = new BufferedOutput();

        $this->app = require dirname(__DIR__, 2).'/app/bootstrap.php';

        // Bootstrapping the kernel is what registers the facades and the service providers,
        // so it is done here rather than left until the first command runs.
        $this->bootKernel();

        return $this->app;
    }

    /** Bootstrap the console kernel, unwinding the handlers it installs. */
    protected function bootKernel(): void
    {
        set_error_handler($this->errorSentinel = fn (): bool => false);
        set_exception_handler($this->exceptionSentinel = function (): void {
            //
        });

        try {
            $this->app->make(Kernel::class)->bootstrap();
        } finally {
            $this->unwindErrorHandlers();
            $this->unwindExceptionHandlers();
        }
    }

    /**
     * Run a console command against the booted application and return its exit status.
     *
     * The framework installs an error and an exception handler while bootstrapping the
     * console kernel. They are unwound again as soon as the command returns, so the
     * test runner's own handlers are back in place before it inspects them.
     */
    protected function runCommand(string $command, array $parameters = []): int
    {
        // Mark the top of the handler stack so it can be unwound precisely afterwards.
        set_error_handler($this->errorSentinel = fn (): bool => false);
        set_exception_handler($this->exceptionSentinel = function (): void {
            //
        });

        try {
            return $this->app->make(Kernel::class)->handle(new ArrayInput(['command' => $command] + $parameters), $this->console);
        } finally {
            $this->unwindErrorHandlers();
            $this->unwindExceptionHandlers();
        }
    }

    /**
     * Resolve the console kernel's registered commands.
     *
     * Bootstrapping the kernel installs the framework's handlers, so this goes through the
     * same unwinding the command runner does.
     *
     * @return array<string, \Symfony\Component\Console\Command\Command>
     */
    protected function registeredCommands(): array
    {
        set_error_handler($this->errorSentinel = fn (): bool => false);
        set_exception_handler($this->exceptionSentinel = function (): void {
            //
        });

        try {
            return $this->app->make(Kernel::class)->all();
        } finally {
            $this->unwindErrorHandlers();
            $this->unwindExceptionHandlers();
        }
    }


    protected function consoleOutput(): string
    {
        return $this->console->fetch();
    }

    /**
     * Undo everything booting the application did to global state.
     *
     * The application binds itself as the container and the facade root; leaving that in
     * place would let one test's application serve the next test's facade calls.
     */
    public function shutdownApplication(): void
    {
        if ($this->app === null) {
            return;
        }

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        $this->app = null;
    }

    private function unwindErrorHandlers(): void
    {
        for ($i = 0; $i < 32; $i++) {
            $current = set_error_handler(null);
            restore_error_handler();

            if ($current === $this->errorSentinel) {
                break;
            }

            restore_error_handler();
        }

        restore_error_handler();
    }

    private function unwindExceptionHandlers(): void
    {
        for ($i = 0; $i < 32; $i++) {
            $current = set_exception_handler(null);
            restore_exception_handler();

            if ($current === $this->exceptionSentinel) {
                break;
            }

            restore_exception_handler();
        }

        restore_exception_handler();
    }
}
