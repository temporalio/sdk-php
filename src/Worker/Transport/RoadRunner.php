<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Transport;

use JetBrains\PhpStorm\Pure;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\Worker;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Temporal\Client\Exception\ProtocolException;
use Temporal\Client\Exception\TransportException;

/**
 * @psalm-type JsonHeaders = string
 * @psalm-import-type Headers from ClientInterface
 */
final class RoadRunner implements ConnectionInterface
{
    /**
     * @var string
     */
    private const ERROR_HEADERS_FORMAT =
        'Incorrect format of received headers. An array<string, mixed> ' .
        'required, but %s (%s) given';

    /**
     * @var Worker
     */
    private Worker $worker;

    /**
     * @var RPC
     */
    private RPC $rpc;

    /**
     * @param RelayInterface $relay
     */
    #[Pure]
    private function __construct(RelayInterface $relay)
    {
        $this->rpc = new RPC($relay);
        $this->worker = new Worker($relay);

        $this->bootStdoutHandlers();
    }

    /**
     * @return $this
     */
    private function bootStdoutHandlers(): self
    {
        // symfony/var-dumper interceptor
        if (\class_exists(CliDumper::class)) {
            CliDumper::$defaultOutput = 'php://stderr';
        }

        // Intercept all output messages
        \ob_start(fn (string $chunk) => $this->write($chunk));

        // Intercept all exceptions
        \set_exception_handler(fn (\Throwable $e) => $this->writeException($e));

        // Intercept all errors
        \set_error_handler(function (int $code, string $message, string $file, int $line) {
            $this->writeException(
                new \ErrorException($message, $code, $code, $file, $line)
            );
        });

        return $this;
    }

    /**
     * @param \Throwable $e
     */
    private function writeException(\Throwable $e): void
    {
        $this->write((string)$e);
    }

    /**
     * @param string $message
     */
    private function write(string $message): void
    {
        \file_put_contents('php://stderr', $message);
    }

    /**
     * @return static
     */
    public static function pipes(): self
    {
        return new self(new StreamRelay(\STDIN, \STDOUT));
    }

    /**
     * @param int $port
     * @param string $host
     * @return static
     */
    public static function socket(int $port, string $host = '127.0.0.1'): self
    {
        return new self(new SocketRelay($host, $port));
    }

    /**
     * @param string $sock
     * @return static
     */
    public static function unix(string $sock): self
    {
        return new self(new SocketRelay($sock, null, SocketRelay::SOCK_UNIX));
    }

    /**
     * {@inheritDoc}
     */
    public function call(string $method, $payload)
    {
        try {
            return $this->interceptErrors(fn() => $this->rpc->call($method, $payload));
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param \Closure $expr
     * @return mixed
     */
    private function interceptErrors(\Closure $expr)
    {
        $handler = static function (int $code, string $message, string $file, int $line) {
            $error = new \ErrorException($message, $code, $code, $file, $line);

            throw new TransportException($message, $code, $error);
        };

        \set_error_handler($handler);

        try {
            return $expr();
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function await(): array
    {
        [$body, $json] = $this->interceptErrors(function () {
            $body = $this->worker->receive($json);

            return [$body, $json];
        });

        return [$body, $this->decodeHeaders($json)];
    }

    /**
     * @param JsonHeaders|null $headers
     * @return Headers
     * @throws ProtocolException
     */
    private function decodeHeaders(string $headers = null): array
    {
        if ($headers === null) {
            return [];
        }

        try {
            $result = \json_decode($headers, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }

        if (! \is_array($result)) {
            $message = \sprintf(self::ERROR_HEADERS_FORMAT, \get_debug_type($result), $headers);
            throw new ProtocolException($message);
        }

        return $result ?? [];
    }

    /**
     * @param Headers $headers
     * @return JsonHeaders|null
     */
    private function encodeHeaders(array $headers): ?string
    {
        if (\count($headers) === 0) {
            return null;
        }

        try {
            return \json_encode($headers, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $message, array $headers = []): void
    {
        $json = $this->encodeHeaders($headers);

        try {
            $this->worker->send($message, $json);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function error(\Throwable $error): void
    {
        try {
            $this->worker->error((string)$error);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
