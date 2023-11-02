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
use Temporal\Worker\Transport\Command\FailureResponse;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\ServerRequest;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class Decoder
{
    private DataConverterInterface $converter;

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->converter = $dataConverter;
    }

    /**
     * @throws \Exception
     */
    public function decode(array $command): ServerRequestInterface|ResponseInterface
    {
        return match (true) {
            isset($command['command']) => $this->parseRequest($command),
            isset($command['failure']) => $this->parseFailureResponse($command),
            default => $this->parseResponse($command),
        };
    }

    /**
     * @param array $data
     * @return RequestInterface
     * @throws \Exception
     */
    private function parseRequest(array $data): ServerRequestInterface
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
            options: $data['options'] ?? [],
            payloads: EncodedValues::fromPayloads($payloads, $this->converter),
            id: $data['runId'] ?? null,
            header: Header::fromPayloadCollection($headers->getFields(), $this->converter),
        );
    }

    /**
     * @param array $data
     * @return FailureResponseInterface
     * @throws \Exception
     */
    private function parseFailureResponse(array $data): FailureResponseInterface
    {
        $this->assertCommandID($data);

        $failure = new Failure();
        $failure->mergeFromString(\base64_decode($data['failure']));

        return new FailureResponse(
            FailureConverter::mapFailureToException($failure, $this->converter),
            $data['id']
        );
    }

    /**
     * @param array $data
     * @return SuccessResponseInterface
     * @throws \Exception
     */
    private function parseResponse(array $data): SuccessResponseInterface
    {
        $this->assertCommandID($data);

        $payloads = new Payloads();
        if (isset($data['payloads'])) {
            $payloads->mergeFromString(base64_decode($data['payloads']));
        }

        return new SuccessResponse(EncodedValues::fromPayloads($payloads, $this->converter), $data['id']);
    }

    /**
     * @param array $data
     */
    private function assertCommandID(array $data): void
    {
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('An "id" command argument required');
        }

        if (!$this->isUInt32($data['id'])) {
            throw new \OutOfBoundsException('Command identifier must be a type of uint32');
        }
    }

    /**
     * @param mixed $value
     * @return bool
     */
    #[Pure]
    private function isUInt32(
        $value
    ): bool {
        return \is_int($value) && $value >= 0 && $value <= 2_147_483_647;
    }
}
