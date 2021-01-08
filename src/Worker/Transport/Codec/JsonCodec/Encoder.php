<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec\JsonCodec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Payload;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ErrorResponseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

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
                $options = $command->getOptions();
                if ($options === []) {
                    $options = new \stdClass();
                }

                return [
                    'id' => $command->getID(),
                    'command' => $command->getName(),
                    'options' => $options,
                    'payloads' => ['payloads' => $this->encodePayloads($command->getPayloads())],
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
                    'payloads' => ['payloads' => $this->encodePayloads($command->getPayloads())],
                ];

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($command)));
        }
    }

    /**
     * JSON require base64 encoding for all the payload data
     *
     * @param array $values
     * @return array
     */
    private function encodePayloads(array $values): array
    {
        $result = [];
        /** @var Payload $payload */
        foreach ($values as $value) {
            $encoded = $this->dataConverter->toPayload($value);

            $result[] = [
                'metadata' => array_map('base64_encode', $encoded->getMetadata()),
                'data' => base64_encode($encoded->getData())
            ];
        }

        return $result;
    }
}
