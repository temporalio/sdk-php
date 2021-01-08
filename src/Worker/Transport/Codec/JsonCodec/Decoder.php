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
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Payload;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ErrorResponse;
use Temporal\Worker\Transport\Command\ErrorResponseInterface;
use Temporal\Worker\Transport\Command\Request;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class Decoder
{
    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * Decoder constructor.
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;
    }

    /**
     * @param array $command
     * @return CommandInterface
     */
    public function parse(array $command): CommandInterface
    {
        switch (true) {
            case isset($command['command']):
                return $this->parseRequest($command);

            case isset($command['error']) :
                return $this->parseErrorResponse($command);

            default:
                return $this->parseResponse($command);
        }
    }

    /**
     * @param array $data
     * @return RequestInterface
     */
    private function parseRequest(array $data): RequestInterface
    {
        $this->assertCommandID($data);

        if (!\is_string($data['command']) || $data['command'] === '') {
            throw new \InvalidArgumentException('Request command must be a non-empty string');
        }

        if (isset($data['options']) && !\is_array($data['options'])) {
            throw new \InvalidArgumentException('Request params must be an object');
        }

        $payloads = [];
        if (isset($data['payloads']['payloads'])) {
            $payloads = $this->decodePayloads($data['payloads']['payloads']);
        }

        return new Request($data['command'], $data['options'] ?? [], $payloads, $data['id']);
    }

    /**
     * @param array $data
     * @return ErrorResponseInterface
     */
    private function parseErrorResponse(array $data): ErrorResponseInterface
    {
        $this->assertCommandID($data);

        if (!isset($data['error']) || !\is_array($data['error'])) {
            throw new \InvalidArgumentException('An error response must contain an object "error" field');
        }

        $error = $data['error'];

        if (!isset($error['code']) || !$this->isUInt32($error['code'])) {
            throw new \InvalidArgumentException('Error code must contain a valid uint32 value');
        }

        if (!isset($error['message']) || !\is_string($error['message']) || $error['message'] === '') {
            throw new \InvalidArgumentException('Error message must contain a valid non-empty string value');
        }

        return new ErrorResponse(
            $error['message'],
            $error['code'],
            $error['data'] ?? null,
            $data['id']
        );
    }

    /**
     * @param array $data
     * @return SuccessResponseInterface
     */
    private function parseResponse(array $data): SuccessResponseInterface
    {
        $this->assertCommandID($data);

        $payloads = [];
        if (isset($data['payloads']['payloads'])) {
            $payloads = $this->decodePayloads($data['payloads']['payloads']);
        }

        return new SuccessResponse($payloads, $data['id']);
    }

    /**
     * Decodes payloads from the incoming request into internal representation.
     *
     * @param array $payloads
     * @return array<Payload>
     */
    private function decodePayloads(array $payloads): array
    {
        return array_map(
            static function ($value) {
                return Payload::create(
                    array_map('base64_decode', $value['metadata']),
                    $value['data'] === null ? null : base64_decode($value['data'])
                );
            },
            $payloads
        );
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
