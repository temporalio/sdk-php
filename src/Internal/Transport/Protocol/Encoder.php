<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Protocol;

use Temporal\Client\Internal\Support\Json;
use Temporal\Client\Internal\Transport\Protocol\Command\CommandInterface;
use Temporal\Client\Internal\Transport\Protocol\Command\ErrorResponseInterface;
use Temporal\Client\Internal\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Internal\Transport\Protocol\Command\SuccessResponseInterface;

final class Encoder
{
    /**
     * @param iterable|CommandInterface[] $commands
     * @return string
     * @throws \JsonException
     */
    public static function encode(iterable $commands): string
    {
        $result = [];

        foreach ($commands as $command) {
            if (! $command instanceof CommandInterface) {
                throw new \InvalidArgumentException('Command must be an instance of ' . CommandInterface::class);
            }

            $result[] = self::encodeCommand($command);
        }

        return Json::encode($result);
    }

    /**
     * @param CommandInterface $command
     * @return array
     */
    private static function encodeCommand(CommandInterface $command): array
    {
        switch (true) {
            case $command instanceof RequestInterface:
                return [
                    'id'      => $command->getId(),
                    'command' => $command->getName(),
                    'params'  => $command->getParams(),
                ];

            case $command instanceof ErrorResponseInterface:
                return [
                    'id'    => $command->getId(),
                    'error' => [
                        'code'    => $command->getCode(),
                        'message' => $command->getMessage(),
                        'data'    => $command->getData(),
                    ],
                ];

            case $command instanceof SuccessResponseInterface:
                return [
                    'id'     => $command->getId(),
                    'result' => $command->getResult(),
                ];

            default:
                throw new \InvalidArgumentException('Unsupported command type');
        }
    }
}
