<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Codec\JsonCodec;

use JetBrains\PhpStorm\Pure;
use Temporal\Worker\Command\CommandInterface;
use Temporal\Worker\Command\ErrorResponse;
use Temporal\Worker\Command\ErrorResponseInterface;
use Temporal\Worker\Command\Request;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Worker\Command\SuccessResponse;
use Temporal\Worker\Command\SuccessResponseInterface;

class Parser
{
    /**
     * @param array $command
     * @return CommandInterface
     */
    public function parse(array $command): CommandInterface
    {
        switch (true) {
            case isset($command['command']) || \array_key_exists('command', $command):
                return $this->parseRequest($command);

            case isset($command['error']) || \array_key_exists('error', $command):
                return $this->parseErrorResponse($command);

            default:
                return $this->parseSuccessResponse($command);
        }
    }

    /**
     * @param array $data
     * @return RequestInterface
     */
    private function parseRequest(array $data): RequestInterface
    {
        $this->assertCommandId($data);

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
    private function assertCommandId(array $data): void
    {
        if (! isset($data['id'])) {
            throw new \InvalidArgumentException('An "id" command argument required');
        }

        if (! $this->isUInt32($data['id'])) {
            throw new \OutOfBoundsException('Command identifier must be a type of uint32');
        }
    }

    /**
     * @param mixed $value
     * @return bool
     */
    #[Pure]
    private function isUInt32($value): bool
    {
        return \is_int($value) && $value >= 0 && $value <= 2_147_483_647;
    }

    /**
     * @param array $data
     * @return ErrorResponseInterface
     */
    private function parseErrorResponse(array $data): ErrorResponseInterface
    {
        $this->assertCommandId($data);

        if (! isset($data['error']) || ! \is_array($data['error'])) {
            throw new \InvalidArgumentException('An error response must contain an object "error" field');
        }

        $error = $data['error'];

        if (! isset($error['code']) || ! $this->isUInt32($error['code'])) {
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
    private function parseSuccessResponse(array $data): SuccessResponseInterface
    {
        $this->assertCommandId($data);

        return new SuccessResponse($data['result'] ?? null, $data['id']);
    }
}
