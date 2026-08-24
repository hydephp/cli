<?php

declare(strict_types=1);

namespace Tests\Support;

use function trim;
use function fopen;
use function fclose;
use function usleep;
use function implode;
use function is_array;
use function proc_open;
use function proc_close;
use function proc_get_status;
use function proc_terminate;
use function is_resource;
use function stream_get_contents;
use function stream_set_blocking;
use function stream_socket_server;
use function stream_socket_get_name;
use function escapeshellarg;
use function file_get_contents;
use function stream_context_create;

/**
 * Starts `hyde serve` from the built executable and talks to it over real HTTP.
 *
 * Nothing here is mocked: the request goes out over a socket to a server started by the
 * executable using the PHP runtime it extracted, on a machine where the environment
 * given to it contains no PHP at all.
 */
final class Server
{
    /** @var resource|null */
    private $process = null;

    private int $pid = 0;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $buffer = '';

    public function __construct(
        public readonly string $directory,
        public readonly int $port,
        public readonly string $host = '127.0.0.1',
    ) {
        //
    }

    public static function start(string $directory): self
    {
        $server = new self($directory, self::freePort());

        $server->run();

        return $server;
    }

    private function run(): void
    {
        $command = implode(' ', [
            escapeshellarg((string) Executable::path()),
            'serve',
            '--host=127.0.0.1',
            '--port='.$this->port,
            '--no-ansi',
        ]);

        $this->process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $this->pipes, $this->directory, Executable::environment());

        if (! is_resource($this->process)) {
            return;
        }

        foreach ([1, 2] as $descriptor) {
            stream_set_blocking($this->pipes[$descriptor], false);
        }

        $status = proc_get_status($this->process);

        $this->pid = is_array($status) ? (int) $status['pid'] : 0;
    }

    /** Poll the server until it answers, or give up. */
    public function waitUntilReady(float $seconds = 20.0): bool
    {
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            $this->drain();

            if ($this->accepts()) {
                return true;
            }

            usleep(200_000);
        }

        $this->drain();

        return false;
    }

    /**
     * Can a connection be opened to the server yet?
     *
     * The connection is attempted behind a silencing handler: a refused connection is the
     * expected answer while the server is still starting, not something to report.
     */
    private function accepts(): bool
    {
        set_error_handler(fn (): bool => true);

        try {
            $socket = stream_socket_client("tcp://$this->host:$this->port", $errno, $error, 1);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /** Collect whatever the server has written since the last time we looked. */
    private function drain(): void
    {
        foreach ([1, 2] as $descriptor) {
            if (isset($this->pipes[$descriptor]) && is_resource($this->pipes[$descriptor])) {
                $this->buffer .= (string) stream_get_contents($this->pipes[$descriptor]);
            }
        }
    }

    /** Make a real HTTP request against the running server. */
    public function get(string $path = '/'): string
    {
        $context = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);

        set_error_handler(fn (): bool => true);

        try {
            $response = (string) file_get_contents("http://$this->host:$this->port$path", false, $context);
        } finally {
            restore_error_handler();
        }

        // Give the server a moment to flush its request log before it is inspected.
        usleep(200_000);

        $this->drain();

        return $response;
    }

    public function output(): string
    {
        $this->drain();

        return trim($this->buffer);
    }

    /**
     * Stop the server and every process it started.
     *
     * Children are killed by the process ids we recorded, never by matching a command
     * line: another Hyde server may well be running on this machine.
     */
    public function stop(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        foreach ($this->children() as $child) {
            @posix_kill($child, SIGTERM);
        }

        @proc_terminate($this->process, SIGTERM);

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        @proc_close($this->process);

        $this->process = null;
        $this->pipes = [];
    }

    /** @return list<int> */
    private function children(): array
    {
        if ($this->pid === 0 || ! function_exists('posix_kill')) {
            return [];
        }

        $output = [];

        exec('pgrep -P '.$this->pid.' 2>/dev/null', $output);

        return array_map('intval', array_filter($output, 'is_numeric'));
    }

    /** Reserve an ephemeral port by letting the operating system pick one. */
    public static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        $name = stream_socket_get_name($socket, false);

        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }
}
