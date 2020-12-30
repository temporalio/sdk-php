<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Codec\JsonCodec;

use Temporal\Worker\Command\CommandInterface;
use Temporal\Worker\Command\ErrorResponseInterface;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Worker\Command\SuccessResponseInterface;

class Serializer
{
    /**
     * @var string
     */
    private const ERROR_INVALID_COMMAND = 'Unserializable command type %s';

    /**
     * @param CommandInterface $command
     * @return array
     */
    public function serialize(CommandInterface $command): array
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
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($command)));
        }
    }
}
