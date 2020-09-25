<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Message;

use Temporal\Client\Protocol\Json;

abstract class Message implements MessageInterface, \Stringable, \JsonSerializable
{
    /**
     * @var int
     */
    private static int $lastSequenceId = 0;

    /**
     * @var string|int
     */
    protected $id;

    /**
     * Message constructor.
     *
     * @param string|int|null $id
     */
    public function __construct($id = null)
    {
        \assert(\is_string($id) || \is_int($id) || $id === null);

        $this->id = $id ?? $this->nextSequenceId();
    }

    /**
     * @return int
     */
    protected function nextSequenceId(): int
    {
        return ++self::$lastSequenceId;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     * @throws \JsonException
     */
    public function __toString(): string
    {
        return $this->toJson(\JSON_PRETTY_PRINT);
    }

    /**
     * {@inheritDoc}
     */
    public function toJson(int $options = 0): string
    {
        return Json::encode($this->jsonSerialize(), $options);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $this->id,
        ];
    }
}
