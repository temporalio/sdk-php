<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\CodecInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Temporal\Exception\ProtocolException;
use Temporal\Exception\TransportException;

/**
 * @psalm-type JsonHeaders = string
 */
final class RoadRunner implements RelayConnectionInterface
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
     * @var CodecInterface
     */
    private CodecInterface $codec;

    /**
     * @param RelayInterface $relay
     */
    public function __construct(RelayInterface $relay)
    {
        $this->worker = new Worker($relay);
        $this->codec = new JsonCodec();
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
        \ob_start(fn(string $chunk) => $this->write($chunk));

        // Intercept all exceptions
        \set_exception_handler(fn(\Throwable $e) => $this->writeException($e));

        // Intercept all errors
        \set_error_handler(
            function (int $code, string $message, string $file, int $line) {
                $this->writeException(
                    new \ErrorException($message, $code, $code, $file, $line)
                );
            }
        );

        return $this;
    }

    /**
     * @param string $message
     */
    private function write(string $message): void
    {
        \file_put_contents('php://stderr', $message);
    }

    /**
     * @param \Throwable $e
     */
    private function writeException(\Throwable $e): void
    {
        $this->write((string)$e);
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
    public function await(): ?Frame
    {
        /** @var Payload $payload */
        $payload = $this->interceptErrors(
            function () {
                return $this->worker->waitPayload();
            }
        );

        if ($payload === null) {
            return null;
        }

        return new Frame(
            $payload->body,
            $this->decodeHeaders($payload->header)
        );
    }

    /**
     * @param JsonHeaders|null $headers
     * @return array<string, string>
     * @throws ProtocolException
     */
    private function decodeHeaders(string $headers = null): array
    {
        if ($headers === null) {
            return [];
        }

        try {
            $result = $this->codec->decode($headers);
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }

        if (!\is_array($result)) {
            $message = \sprintf(self::ERROR_HEADERS_FORMAT, \get_debug_type($result), $headers);
            throw new ProtocolException($message);
        }

        return $result ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $frame, array $headers = []): void
    {
        $json = $this->encodeHeaders($headers);

        try {
            $this->worker->send($frame, $json);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, string> $headers
     * @return JsonHeaders|null
     */
    private function encodeHeaders(array $headers): ?string
    {
        if (\count($headers) === 0) {
            return null;
        }

        try {
            return $this->codec->encode($headers, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
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
