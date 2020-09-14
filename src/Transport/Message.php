<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

abstract class Message implements MessageInterface
{
    /**
     * @var mixed
     */
    protected $payload;

    /**
     * @param mixed $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->payload;
    }

    /**
     * @return string
     * @throws \JsonException
     */
    public function __toString(): string
    {
        return (string)\json_encode($this, \JSON_THROW_ON_ERROR);
    }
}
