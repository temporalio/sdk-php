<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Protocol;

use JetBrains\PhpStorm\Pure;
use Temporal\Client\Transport\Protocol\Command\ErrorResponse;
use Temporal\Client\Transport\Protocol\Command\ErrorResponseInterface;
use Temporal\Client\Transport\Protocol\Command\Request;
use Temporal\Client\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Transport\Protocol\Command\SuccessResponse;
use Temporal\Client\Transport\Protocol\Command\SuccessResponseInterface;

final class Decoder
{
    /**
     * @param string $json
     * @return array
     * @throws \JsonException
     * @throws \Exception
     */
    public static function decode(string $json): array
    {
        $data = Json::decode($json, \JSON_OBJECT_AS_ARRAY);

        $result = [];

        foreach ($data as $command) {
            switch (true) {
                case isset($command['command']) || \array_key_exists('command', $command):
                    $result[] = self::parseRequest($command);
                    break;

                case isset($command['error']) || \array_key_exists('error', $command):
                    $result[] = self::parseErrorResponse($command);
                    break;

                default:
                    $result[] = self::parseSuccessResponse($command);
            }
        }

        return $result;
    }

    /**
     * @param array $data
     * @return RequestInterface
     */
    private static function parseRequest(array $data): RequestInterface
    {
        self::assertCommandId($data);

        if (! \is_string($data['command']) || $data['command'] === '') {
            throw new \InvalidArgumentException('Request command must be a non-empty string');
        }

        if (isset($data['params']) && ! \is_array($data['params'])) {
            throw new \InvalidArgumentException('Request params must be an object');
        }

        return new Request($data['command'], $data['params'] ?? [], $data['id']);
    }

    /**
     * @param array $data
     */
    private static function assertCommandId(array $data): void
    {
        if (! isset($data['id'])) {
            throw new \InvalidArgumentException('An "id" command argument required');
        }

        if (! self::isUInt32($data['id'])) {
            throw new \OutOfBoundsException('Command identifier must be a type of uint32');
        }
    }

    /**
     * @param mixed $value
     * @return bool
     */
    #[Pure]
    private static function isUInt32($value): bool
    {
        return \is_int($value) && $value >= 0 && $value <= 2_147_483_647;
    }

    /**
     * @param array $data
     * @return ErrorResponseInterface
     */
    private static function parseErrorResponse(array $data): ErrorResponseInterface
    {
        self::assertCommandId($data);

        if (! isset($data['error']) || ! \is_array($data['error'])) {
            throw new \InvalidArgumentException('An error response must contain an object "error" field');
        }

        $error = $data['error'];

        if (! isset($error['code']) || ! self::isUInt32($error['code'])) {
            throw new \InvalidArgumentException('Error code must contain a valid uint32 value');
        }

        if (! isset($error['message']) || ! \is_string($error['message']) || $error['message'] === '') {
            throw new \InvalidArgumentException('Error message must contain a valid non-empty string value');
        }

        return new ErrorResponse($error['message'], $error['code'], $error['data'] ?? null, $data['id']);
    }

    /**
     * @param array $data
     * @return SuccessResponseInterface
     */
    private static function parseSuccessResponse(array $data): SuccessResponseInterface
    {
        self::assertCommandId($data);

        return new SuccessResponse($data['result'] ?? null, $data['id']);
    }
}
