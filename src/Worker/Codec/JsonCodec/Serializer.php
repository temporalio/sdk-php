<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Codec\JsonCodec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Command\CommandInterface;
use Temporal\Worker\Command\ErrorResponseInterface;
use Temporal\Worker\Command\PayloadAwareRequest;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Worker\Command\SuccessResponseInterface;

class Serializer
{
    /**
     * @var string
     */
    private const ERROR_INVALID_COMMAND = 'Unserializable command type %s';
    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;
    }

    /**
     * @param CommandInterface $command
     * @return array
     */
    public function serialize(CommandInterface $command): array
    {
        switch (true) {
            case $command instanceof PayloadAwareRequest:
                return [
                    'id' => $command->getId(),
                    'command' => $command->getName(),
                    'params' => $command->getMappedParams($this->dataConverter),
                ];

            case $command instanceof RequestInterface:
                return [
                    'id' => $command->getId(),
                    'command' => $command->getName(),
                    'params' => $command->getParams(),
                ];

            case $command instanceof ErrorResponseInterface:
                return [
                    'id' => $command->getId(),
                    'error' => [
                        'code' => $command->getCode(),
                        'message' => $command->getMessage(),
                        'data' => $command->getData(),
                    ],
                ];

            case $command instanceof SuccessResponseInterface:
                // convert all payloads into transport format upon leave from worker
                return [
                    'id' => $command->getId(),
                    'result' => $this->dataConverter->toPayloads($command->getResult()),
                ];

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($command)));
        }
    }
}
