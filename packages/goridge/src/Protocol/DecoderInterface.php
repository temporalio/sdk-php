<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

use Spiral\Goridge\Exception\TransportException;
use Spiral\Goridge\Message\ReceivedMessageInterface;

interface DecoderInterface
{
    /**
     * Returns the coroutine for reading from the source. Each "tick" of the
     * iterator passes the length to read from the source. The read value of
     * the chunk should be sent back.
     *
     * The result ({@see \Generator::getReturn()}) of the coroutine contains
     * the read and decoded message body and its flags as a result of the
     * decoding.
     *
     * For example, if we work with resource streams, then the reading
     * will look like this:
     *
     * <code>
     *  $stream = $decoder->decode();
     *
     *  while ($stream->valid()) {
     *      $stream->send(
     *          fread($resource, $stream->current())
     *      );
     *  }
     *
     *  return $stream->getReturn();
     * </code>
     *
     * @return \Generator|ReceivedMessageInterface
     * @throws TransportException in case of decoding error.
     *
     * @psalm-return \Generator<array-key, int, string, DecodedMessageInterface>
     */
    public function decode(): \Generator;
}
