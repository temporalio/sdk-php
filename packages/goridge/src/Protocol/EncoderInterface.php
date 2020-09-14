<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

use Spiral\Goridge\Exception\EncodingException;
use Spiral\Goridge\Message\ProceedMessageInterface;
use Spiral\Goridge\Message\ReceivedMessageInterface;

interface EncoderInterface
{
    /**
     * Packs the message to protocol format and returns {@see ReceivedMessageInterface} data
     * transfer object instance.
     *
     * <code>
     *  $message = $encoder->encode('Example');
     * </code>
     *
     * @param string $body The protocol's message body.
     * @param int $flags The message's flags.
     * @return ProceedMessageInterface
     * @throws EncodingException in case of encoding error.
     */
    public function encode(string $body, int $flags): ProceedMessageInterface;
}
