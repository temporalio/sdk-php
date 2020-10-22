<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Protocol;

use Temporal\Client\Protocol\Command\CommandInterface;
use Temporal\Client\Protocol\Command\ErrorResponse;
use Temporal\Client\Protocol\Command\ErrorResponseInterface;
use Temporal\Client\Protocol\Command\Request;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\SuccessResponse;
use Temporal\Client\Protocol\Command\SuccessResponseInterface;
use Temporal\Client\Protocol\Json;

final class Decoder
{
    /**
     * @param string $json
     * @param \DateTimeZone $zone
     * @return array
     * @throws \JsonException
     * @throws \Exception
     */
    public static function decode(string $json, \DateTimeZone $zone): array
    {
        $data = Json::decode($json, \JSON_OBJECT_AS_ARRAY);

        return [
            'rid'      => self::parseRunId($data),
            'tickTime' => self::parseTickTime($data, $zone),
            'commands' => self::parseCommands($data),
        ];
    }

    /**
     * @param array $data
     * @return int|string|null
     */
    private static function parseRunId(array $data)
    {
        if (! isset($data['rid'])) {
            return null;
        }

        if (! \is_string($data['rid']) && ! \is_int($data['rid'])) {
            throw new \InvalidArgumentException('WorkflowDeclaration run id argument contain an invalid type');
        }

        return $data['rid'];
    }

    /**
     * @param array $data
     * @param \DateTimeZone $zone
     * @return \DateTimeInterface
     * @throws \Exception
     */
    private static function parseTickTime(array $data, \DateTimeZone $zone): \DateTimeInterface
    {
        if (! isset($data['tickTime'])) {
            return new \DateTimeImmutable('now', $zone);
        }

        try {
            return new \DateTimeImmutable($data['tickTime'], $zone);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Request tick time contain an invalid date time format', 0, $e);
        }
    }

    /**
     * @param array $data
     * @return array|CommandInterface[]
     * @throws \JsonException
     */
    private static function parseCommands(array $data): array
    {
        if (! isset($data['commands'])) {
            return [];
        }

        if (! \is_array($data['commands'])) {
            throw new \InvalidArgumentException('Commands list should be an array');
        }

        $result = [];

        foreach ($data['commands'] as $command) {
            switch (true) {
                case isset($command['command']):
                    $result[] = self::parseRequest($command);
                    break;

                case isset($command['error']):
                    $result[] = self::parseErrorResponse($command);
                    break;

                case isset($command['result']):
                    $result[] = self::parseSuccessResponse($command);
                    break;

                default:
                    throw new \InvalidArgumentException('Unrecognized command type: ' .
                        Json::encode($command, \JSON_PRETTY_PRINT)
                    );
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
