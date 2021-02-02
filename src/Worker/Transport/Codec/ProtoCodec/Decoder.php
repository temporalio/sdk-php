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
use Temporal\Roadrunner\Internal\Message;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\FailureResponse;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\Request;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\SuccessResponse;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

/**
 * @codeCoverageIgnore tested via roadrunner-temporal repository.
 */
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

    /**
     * @param Message $msg
     * @return CommandInterface
     */
    public function decode(Message $msg): CommandInterface
    {
        switch (true) {
            case $msg->getCommand() !== '':
                return $this->parseRequest($msg);

            case $msg->hasFailure():
                return $this->parseFailureResponse($msg);

            default:
                return $this->parseResponse($msg);
        }
    }

    /**
     * @param Message $msg
     * @return RequestInterface
     */
    private function parseRequest(Message $msg): RequestInterface
    {
        $payloads = null;
        if ($msg->hasPayloads()) {
            $payloads = EncodedValues::fromPayloads($msg->getPayloads(), $this->converter);
        }

        return new Request(
            $msg->getCommand(),
            json_decode($msg->getOptions(), true, 256, JSON_THROW_ON_ERROR),
            $payloads,
            (int)$msg->getId()
        );
    }

    /**
     * @param Message $msg
     * @return FailureResponseInterface
     */
    private function parseFailureResponse(Message $msg): FailureResponseInterface
    {
        return new FailureResponse(
            FailureConverter::mapFailureToException($msg->getFailure(), $this->converter),
            $msg->getId()
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

        return new SuccessResponse($payloads, $msg->getId());
    }
}
