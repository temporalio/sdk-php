<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec\ProtoCodec;

use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Payload;
use Temporal\Roadrunner\Internal\Error;
use Temporal\Roadrunner\Internal\Message;
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
     * @return Message
     */
    public function serialize(CommandInterface $command): Message
    {
        $msg = new Message();
        $msg->setId($command->getID());

        switch (true) {
            case $command instanceof RequestInterface:
                $options = $command->getOptions();
                if ($options === []) {
                    $options = new \stdClass();
                }

                $msg->setCommand($command->getName());
                $msg->setOptions(json_encode($options));
                $msg->setPayloads($this->encodePayloads($command->getPayloads()));

                return $msg;

            case $command instanceof ErrorResponseInterface:
                $error = new Error();
                $error->setMessage($command->getMessage());
                $error->setCode($command->getCode());
                $error->setData(json_encode($command->getData()));

                return $msg;

            case $command instanceof SuccessResponseInterface:
                $msg->setPayloads($this->encodePayloads($command->getPayloads()));

                return $msg;
            default:
                throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_COMMAND, \get_class($command)));
        }
    }

    /**
     * JSON require base64 encoding for all the payload data
     *
     * @param array $values
     * @return Payloads
     */
    private function encodePayloads(array $values): Payloads
    {
        $values = [];

        /** @var Payload $payload */
        foreach ($values as $value) {
            $encoded = $this->dataConverter->toPayload($value);

            $value = new \Temporal\Api\Common\V1\Payload();
            $value->setMetadata($encoded->getMetadata());
            $value->setData($encoded->getData());

            $values[] = $value;
        }

        $payloads = new Payloads();
        $payloads->setPayloads($values);
        return $payloads;
    }
}
