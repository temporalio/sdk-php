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
use Temporal\Client\Protocol\Command\ErrorResponseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\SuccessResponseInterface;
use Temporal\Client\Protocol\Json;

final class Encoder
{
    /**
     * @param \DateTimeInterface $tick
     * @param iterable|CommandInterface[] $commands
     * @param string|int|null $runId
     * @return string
     * @throws \JsonException
     */
    public static function encode(\DateTimeInterface $tick, iterable $commands, $runId = null): string
    {
        return Json::encode([
            'rid'      => $runId,
            'tickTime' => $tick->format(\DateTime::RFC3339),
            'commands' => self::encodeCommands($commands),
        ]);
    }

    /**
     * @param iterable|CommandInterface[] $commands
     * @return array
     */
    private static function encodeCommands(iterable $commands): array
    {
        $result = [];

        foreach ($commands as $command) {
            if (! $command instanceof CommandInterface) {
                throw new \InvalidArgumentException('Command must be an instance of ' . CommandInterface::class);
            }

            $result[] = self::encodeCommand($command);
        }

        return $result;
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
