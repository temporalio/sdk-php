<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec\JsonCodec;

use JetBrains\PhpStorm\Pure;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\Header;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface as ServerResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class Decoder
{
    public function __construct(
        private readonly DataConverterInterface $dataConverter,
    ) {}

    /**
     * @throws \Exception
     */
    public function decode(array $command, TickInfo $info): ServerRequestInterface|ServerResponse
    {
        return match (true) {
            isset($command['command']) => $this->parseRequest($command, $info),
            isset($command['failure']) => $this->parseFailureResponse($command, $info),
            default => $this->parseResponse($command, $info),
        };
    }

    private function parseRequest(array $data, TickInfo $info): ServerRequestInterface
    {
        $payloads = new Payloads();
        if (isset($data['payloads'])) {
            $payloads->mergeFromString(\base64_decode($data['payloads']));
        }

        $headers = new \Temporal\Api\Common\V1\Header();
        if (isset($data['header'])) {
            $headers->mergeFromString(\base64_decode($data['header']));
        }

        return new ServerRequest(
            name: $data['command'],
            info: $info,
            options: $data['options'] ?? [],
            payloads: EncodedValues::fromPayloads($payloads, $this->dataConverter),
            id: $data['runId'] ?? null,
            header: Header::fromPayloadCollection($headers->getFields(), $this->dataConverter),
        );
    }

    private function parseFailureResponse(array $data, TickInfo $info): FailureResponseInterface
    {
        $this->assertCommandID($data);

        $failure = new Failure();
        $failure->mergeFromString(\base64_decode($data['failure']));

        return new FailureResponse(
            FailureConverter::mapFailureToException($failure, $this->dataConverter),
            $data['id'],
            $info,
        );
    }

    private function parseResponse(array $data, TickInfo $info): SuccessResponseInterface
    {
        $this->assertCommandID($data);

        $payloads = new Payloads();
        if (isset($data['payloads'])) {
            $payloads->mergeFromString(base64_decode($data['payloads']));
        }

        return new SuccessResponse(EncodedValues::fromPayloads($payloads, $this->dataConverter), $data['id'], $info);
    }

    private function assertCommandID(array $data): void
    {
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('An "id" command argument required');
        }

        if (!$this->isUInt32($data['id'])) {
            throw new \OutOfBoundsException('Command identifier must be a type of uint32');
        }
    }

    #[Pure]
    private function isUInt32(
        mixed $value,
    ): bool {
        return \is_int($value) && $value >= 0 && $value <= 2_147_483_647;
    }
}
