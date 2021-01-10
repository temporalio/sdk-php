<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec\ProtoCodec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Payload;
use Temporal\Roadrunner\Internal\Message;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ErrorResponse;
use Temporal\Worker\Transport\Command\ErrorResponseInterface;
use Temporal\Worker\Transport\Command\Request;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class Decoder
{
    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * Decoder constructor.
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;
    }

    /**
     * @param Message $msg
     * @return CommandInterface
     */
    public function parse(Message $msg): CommandInterface
    {
        switch (true) {
            case $msg->getCommand() !== "":
                return $this->parseRequest($msg);

            case $msg->getError() !== null :
                return $this->parseErrorResponse($msg);

            default:
                return $this->parseResponse($msg);
        }
    }

    /**
     * @param array $msg
     * @return RequestInterface
     */
    private function parseRequest(Message $msg): RequestInterface
    {
        $payloads = [];
        if ($msg->getPayloads() !== null && $msg->getPayloads()->getPayloads()->count() !== 0) {
            $payloads = $this->decodePayloads($msg->getPayloads()->getPayloads());
        }

        return new Request(
            $msg->getCommand(),
            json_decode($msg->getOptions(), true),
            $payloads,
            $msg->getId()
        );
    }

    /**
     * @param array $msg
     * @return ErrorResponseInterface
     */
    private function parseErrorResponse(Message $msg): ErrorResponseInterface
    {
        // todo: access payloads

        return new ErrorResponse(
            $msg->getError()->getMessage(),
            $msg->getError()->getCode(),
            json_decode($msg->getError()->getData()),
            $msg->getId()
        );
    }

    /**
     * @param array $msg
     * @return SuccessResponseInterface
     */
    private function parseResponse(Message $msg): SuccessResponseInterface
    {
        $payloads = [];
        if ($msg->getPayloads() !== null && $msg->getPayloads()->getPayloads()->count() !== 0) {
            $payloads = $this->decodePayloads($msg->getPayloads()->getPayloads());
        }

        return new SuccessResponse($payloads, $msg->getId());
    }

    /**
     * Decodes payloads from the incoming request into internal representation.
     *
     * @param array $payloads
     * @return array<Payload>
     */
    private function decodePayloads(iterable $payloads): array
    {
        $result = [];

        /** @var \Temporal\Api\Common\V1\Payload $payload */
        foreach ($payloads as $payload) {
            $metadata = [];
            foreach ($payload->getMetadata() as $key => $value) {
                $metadata[$key] = $value;
            }

            $result[] = Payload::create($metadata, $payload->getData());
        }

        return $result;
    }
}
