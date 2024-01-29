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
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\Header;
use RoadRunner\Temporal\DTO\V1\Message;
use Temporal\Worker\Transport\Command\FailureResponse;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\ResponseInterface;
use Temporal\Worker\Transport\Command\ServerRequest;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

class Decoder
{
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

    public function decode(Message $msg): ServerRequestInterface|ResponseInterface
    {
        return match (true) {
            $msg->getCommand() !== '' => $this->parseRequest($msg),
            $msg->hasFailure() => $this->parseFailureResponse($msg),
            default => $this->parseResponse($msg),
        };
    }

    /**
     * @param Message $msg
     * @return ServerRequestInterface
     */
    private function parseRequest(Message $msg): ServerRequestInterface
    {
        $payloads = null;
        if ($msg->hasPayloads()) {
            $payloads = EncodedValues::fromPayloads($msg->getPayloads(), $this->converter);
        }
        $header = $msg->hasHeader()
            ? Header::fromPayloadCollection($msg->getHeader()->getFields(), $this->converter)
            : null;

        return new ServerRequest(
            name: $msg->getCommand(),
            options: \json_decode($msg->getOptions(), true, 256, JSON_THROW_ON_ERROR),
            payloads: $payloads,
            id: $msg->getRunId() ?: null,
            header: $header,
            historyLength: (int)$msg->getHistoryLength(),
        );
    }

    /**
     * @param Message $msg
     * @return FailureResponseInterface
     */
    private function parseFailureResponse(Message $msg): FailureResponseInterface
    {
        return new FailureResponse(
            failure: FailureConverter::mapFailureToException($msg->getFailure(), $this->converter),
            id: $msg->getId(),
            historyLength: (int)$msg->getHistoryLength()
        );
    }

    /**
     * @param Message $msg
     * @return SuccessResponseInterface
     */
    private function parseResponse(Message $msg): SuccessResponseInterface
    {
        $payloads = null;
        if ($msg->hasPayloads()) {
            $payloads = EncodedValues::fromPayloads($msg->getPayloads(), $this->converter);
        }

        return new SuccessResponse(
            values: $payloads,
            id: $msg->getId(),
            historyLength: (int)$msg->getHistoryLength(),
        );
    }
}
