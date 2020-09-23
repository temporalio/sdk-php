<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

use Spiral\Goridge\Message\ReceivedMessageInterface;
use Spiral\Goridge\Message\ProceedMessage;
use Spiral\Goridge\Message\ProceedMessageInterface;

abstract class Protocol implements ProtocolInterface
{
    /**
     * @param DecoderInterface $decoder
     * @param \Closure $reader
     * @return ReceivedMessageInterface
     */
    public static function decodeThrough(DecoderInterface $decoder, \Closure $reader): ReceivedMessageInterface
    {
        $stream = $decoder->decode();

        while ($stream->valid()) {
            $chunk = $reader($stream->current());

            $stream->send($chunk);
        }

        return $stream->getReturn();
    }

    /**
     * @param EncoderInterface $encoder
     * @param iterable $payload
     * @return ProceedMessageInterface
     *
     * @psalm-param iterable<string, Payload::TYPE_*|null> $payload
     * @psalm-return TEncodedMessage
     */
    public static function encodeBatch(EncoderInterface $encoder, iterable $payload): ProceedMessageInterface
    {
        [$buffer, $size] = ['', 0];

        foreach ($payload as $message => $flags) {
            $encoded = $encoder->encode((string)$message, (int)$flags);

            /** @psalm-suppress PossiblyInvalidOperand */
            $size += $encoded->size;
            $buffer .= $encoded->body;
        }

        return new ProceedMessage($buffer, $size);
    }
}
