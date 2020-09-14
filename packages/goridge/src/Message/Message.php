<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Message;

abstract class Message implements MessageInterface
{
    /**
     * @var string
     */
    public string $body;

    /**
     * @var int
     */
    public int $size;

    /**
     * @param string $body
     * @param int|null $size
     */
    public function __construct(string $body, int $size = null)
    {
        $this->body = $body;
        $this->size = $size ?? \strlen($body);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->body;
    }
}
