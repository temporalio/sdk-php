<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec\ProtoCodec;

use RoadRunner\Temporal\DTO\V1\Message;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\Header;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\Server\FailureResponse;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface as ServerResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class Decoder
{
    public function __construct(
        private readonly DataConverterInterface $dataConverter
    ) {}

    public function decode(Message $msg, TickInfo $info): ServerRequestInterface|ServerResponse
    {
        return match (true) {
            $msg->getCommand() !== '' => $this->parseRequest($msg, $info),
            $msg->hasFailure() => $this->parseFailureResponse($msg, $info),
            default => $this->parseResponse($msg, $info),
        };
    }

    private function parseRequest(Message $msg, TickInfo $info): ServerRequestInterface
    {
        $payloads = null;
        if ($msg->hasPayloads()) {
            $payloads = EncodedValues::fromPayloads($msg->getPayloads(), $this->dataConverter);
        }
        $header = $msg->hasHeader()
            ? Header::fromPayloadCollection($msg->getHeader()->getFields(), $this->dataConverter)
            : null;

        return new ServerRequest(
            name: $msg->getCommand(),
            info: $info,
            options: \json_decode($msg->getOptions(), true, 256, JSON_THROW_ON_ERROR),
            payloads: $payloads,
            id: $msg->getRunId() ?: null,
            header: $header,
        );
    }

    private function parseFailureResponse(Message $msg, TickInfo $info): FailureResponseInterface&ServerResponse
    {
        return new FailureResponse(
            failure: FailureConverter::mapFailureToException($msg->getFailure(), $this->dataConverter),
            id: $msg->getId(),
            info: $info,
        );
    }

    private function parseResponse(Message $msg, TickInfo $info): SuccessResponseInterface&ServerResponse
    {
        $payloads = null;
        if ($msg->hasPayloads()) {
            $payloads = EncodedValues::fromPayloads($msg->getPayloads(), $this->dataConverter);
        }

        return new SuccessResponse(
            values: $payloads,
            id: $msg->getId(),
            info: $info,
        );
    }
}
