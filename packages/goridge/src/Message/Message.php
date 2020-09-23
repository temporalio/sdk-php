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
    public $body;

    /**
     * @param string $body
     */
    public function __construct(string $body)
    {
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->body;
    }
}
