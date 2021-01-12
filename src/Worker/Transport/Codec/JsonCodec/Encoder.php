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
use Temporal\Exception\Failure\FailureConverter;
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
    private DataConverterInterface $converter;

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->converter = $dataConverter;
    }

    /**
     * @param CommandInterface $cmd
     * @return array
     */
    public function serialize(CommandInterface $cmd): array
    {
        switch (true) {
            case $cmd instanceof RequestInterface:
                $options = $cmd->getOptions();
                if ($options === []) {
                    $options = new \stdClass();
                }

                $data = [
                    'id' => $cmd->getID(),
                    'command' => $cmd->getName(),
                    'options' => $options,
                    'payloads' => ['payloads' => $this->encodePayloads($cmd->getPayloads())],
                ];

                if ($cmd->getFailure() !== null) {
                    $failure = FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter);
                    $data['failure'] = base64_encode($failure->serializeToString());
                }

                return $data;

            case $cmd instanceof ErrorResponseInterface:
                $failure = FailureConverter::mapExceptionToFailure($cmd->getFailure(), $this->converter);

                return [
                    'id' => $cmd->getID(),
                    'failure' => base64_encode($failure->serializeToString()),
                ];

            case $cmd instanceof SuccessResponseInterface:
                return [
                    'id' => $cmd->getID(),
                    'payloads' => ['payloads' => $this->encodePayloads($cmd->getPayloads())],
                ];

            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($cmd)));
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
            $encoded = $this->converter->toPayload($value);

            $result[] = [
                'metadata' => array_map('base64_encode', $encoded->getMetadata()),
                'data' => base64_encode($encoded->getData())
            ];
        }

        return $result;
    }
}
