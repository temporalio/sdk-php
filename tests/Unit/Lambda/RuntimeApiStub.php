<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use Symfony\Component\Process\Process;

final class RuntimeApiStub
{
    private const START_TIMEOUT_SECONDS = 10;

    private ?Process $process = null;
    private int $port = 0;
    private int $invocations = 1;
    private bool $sendHeaders = true;
    private int $acknowledgementStatus = 200;

    public function __construct(
        private readonly string $stateDir,
        private int $pollWindowMs,
    ) {}

    public function setInvocations(int $invocations): void
    {
        $this->invocations = $invocations;
        $this->writeSettings();
    }

    public function setPollWindowMs(int $pollWindowMs): void
    {
        $this->pollWindowMs = $pollWindowMs;
        $this->writeSettings();
    }

    public function omitHeaders(): void
    {
        $this->sendHeaders = false;
        $this->writeSettings();
    }

    public function failAcknowledgementsWith(int $status): void
    {
        $this->acknowledgementStatus = $status;
        $this->writeSettings();
    }

    public function host(): string
    {
        return "127.0.0.1:{$this->port}";
    }

    /**
     * @return list<string>
     */
    public function acknowledgements(): array
    {
        return \array_column($this->acknowledgementLog(), 'path');
    }

    public function lastBody(): string
    {
        $log = $this->acknowledgementLog();

        return $log === [] ? '' : (string) \end($log)['body'];
    }

    public function start(): void
    {
        $this->port = self::freePort();
        $this->writeSettings();
        \file_put_contents($this->router(), self::ROUTER);

        $this->process = new Process(
            [\PHP_BINARY, '-S', $this->host(), $this->router()],
            env: ['STUB_STATE' => $this->stateDir],
            timeout: null,
        );
        $this->process->start();

        $deadline = \microtime(true) + self::START_TIMEOUT_SECONDS;
        while (\microtime(true) < $deadline) {
            $connection = @\fsockopen('127.0.0.1', $this->port, $code, $message, 0.2);
            if ($connection !== false) {
                \fclose($connection);

                return;
            }

            \usleep(50_000);
        }

        throw new \RuntimeException('The Runtime API stub did not start');
    }

    public function stop(): void
    {
        $this->process?->stop(timeout: 5);
        $this->process = null;
    }

    private static function freePort(): int
    {
        $socket = \stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        if ($socket === false) {
            throw new \RuntimeException("Unable to reserve a port: {$message}");
        }

        $name = (string) \stream_socket_get_name($socket, false);
        \fclose($socket);

        return (int) \substr($name, \strrpos($name, ':') + 1);
    }

    /**
     * @return list<array{path: string, body: string}>
     */
    private function acknowledgementLog(): array
    {
        $log = @\file_get_contents($this->stateDir . '/acknowledgements.json');
        if ($log === false) {
            return [];
        }

        return \array_map(
            static fn(string $line): array => (array) \json_decode($line, true),
            \array_filter(\explode("\n", \trim($log))),
        );
    }

    private function writeSettings(): void
    {
        \file_put_contents($this->stateDir . '/settings.json', (string) \json_encode([
            'invocations' => $this->invocations,
            'pollWindowMs' => $this->pollWindowMs,
            'sendHeaders' => $this->sendHeaders,
            'acknowledgementStatus' => $this->acknowledgementStatus,
        ]));
    }

    private function router(): string
    {
        return $this->stateDir . '/runtime-api.php';
    }

    private const ROUTER = <<<'PHP'
        <?php

        declare(strict_types=1);

        $state = (string) \getenv('STUB_STATE');
        $settings = (array) \json_decode((string) \file_get_contents($state . '/settings.json'), true);
        $path = (string) \parse_url((string) $_SERVER['REQUEST_URI'], \PHP_URL_PATH);

        if (\str_ends_with($path, '/runtime/invocation/next')) {
            $served = (int) @\file_get_contents($state . '/served');
            \file_put_contents($state . '/served', (string) ($served + 1));

            if ($served >= (int) $settings['invocations']) {
                \http_response_code(500);

                return;
            }

            if ($settings['sendHeaders'] === true) {
                \header('Lambda-Runtime-Aws-Request-Id: request-' . $served);
                \header('Lambda-Runtime-Deadline-Ms: ' . (
                    (int) (\microtime(true) * 1000) + (int) $settings['pollWindowMs']
                ));
            }

            return;
        }

        \file_put_contents(
            $state . '/acknowledgements.json',
            \json_encode(['path' => $path, 'body' => (string) \file_get_contents('php://input')]) . "\n",
            \FILE_APPEND,
        );

        \http_response_code((int) $settings['acknowledgementStatus']);
        PHP;
}
