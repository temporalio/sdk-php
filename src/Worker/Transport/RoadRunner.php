<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\CodecInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Temporal\Exception\ProtocolException;
use Temporal\Exception\TransportException;
use Spiral\RoadRunner\WorkerInterface as RoadRunnerWorker;

/**
 * @psalm-type JsonHeaders = string
 *
 * @codeCoverageIgnore tested via roadrunner-temporal repository.
 */
final class RoadRunner implements HostConnectionInterface
{
    /**
     * @var string
     */
    private const ERROR_HEADERS_FORMAT =
        'Incorrect format of received headers. An array<string, mixed> ' .
        'required, but %s (%s) given';

    private RoadRunnerWorker $worker;
    private CodecInterface $codec;

    /**
     * @param RoadRunnerWorker $worker
     */
    public function __construct(RoadRunnerWorker $worker)
    {
        $this->worker = $worker;
        $this->codec = new JsonCodec();
    }

    /**
     * @param EnvironmentInterface|null $env
     * @return HostConnectionInterface
     */
    public static function create(
        EnvironmentInterface $env = null,
        RoadRunnerVersionChecker $versionChecker = null
    ): HostConnectionInterface {
        $versionChecker ??= new RoadRunnerVersionChecker();
        $versionChecker->check();

        $env ??= Environment::fromGlobals();

        return new self(new Worker(Relay::create($env->getRelayAddress())));
    }

    /**
     * {@inheritDoc}
     */
    public function waitBatch(): ?CommandBatch
    {
        /** @var Payload $payload */
        $payload = $this->worker->waitPayload();

        if ($payload === null) {
            return null;
        }

        return new CommandBatch(
            $payload->body,
            $this->decodeHeaders($payload->header)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $frame, array $headers = []): void
    {
        $json = $this->encodeHeaders($headers);

        try {
            $this->worker->respond(new Payload($frame, $json));
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
}
