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

class Encoder
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
            case $command instanceof RequestInterface:
                return [
                    'id' => $command->getID(),
                    'command' => $command->getName(),
                    'params' => $this->encodeParams(
                        $command->getParams(),
                        $command->getPayloadParams()
                    ),
                ];

            case $command instanceof ErrorResponseInterface:
                return [
                    'id' => $command->getID(),
                    'error' => [
                        'code' => $command->getCode(),
                        'message' => $command->getMessage(),
                        'data' => $command->getData(),
                    ],
                ];

            case $command instanceof SuccessResponseInterface:
                return [
                    'id' => $command->getID(),
                    'result' => $this->toPayloads($command->getResult()),
                ];

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($command)));
        }
    }

    /**
     * @param array $params
     * @param array $payloadParams
     * @return array
     */
    private function encodeParams(array $params, array $payloadParams): array
    {
        $result = $params;
        foreach ($payloadParams as $name) {
            if (isset($params[$name])) {
                $result[$name] = $this->toPayloads($params[$name]);
            }
        }

        return $result;
    }

    /**
     * @param array $values
     * @return array
     */
    private function toPayloads(array $values): array
    {
        return $this->dataConverter->toPayloads($values);
    }
}
