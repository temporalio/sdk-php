<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge;

use Spiral\Goridge\Protocol\EncoderInterface;

class Responder implements ResponderInterface
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @var EncoderInterface
     */
    private EncoderInterface $encoder;

    /**
     * @param $stream
     * @param EncoderInterface $encoder
     */
    public function __construct($stream, EncoderInterface $encoder)
    {
        $this->stream = $stream;
        $this->encoder = $encoder;
    }

    /**
     * @param string $body
     * @param int $flags
     */
    public function send(string $body, int $flags): void
    {
        $message = $this->encoder->encode($body, $flags);

        \error_clear_last();

        @\fwrite($this->stream, $message->body, $message->size);

        if ($error = \error_get_last()) {
            throw new \RuntimeException($error['message']);
        }
    }
}
