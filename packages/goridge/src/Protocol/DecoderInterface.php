<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

use React\Promise\PromiseInterface;
use Spiral\Goridge\Exception\DecodingException;

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
     * </code>
     *
     * @param resource $stream
     * @return PromiseInterface
     * @throws DecodingException in case of decoding error.
     */
    public function decode($stream): PromiseInterface;
}
