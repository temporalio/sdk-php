<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Protocol;

use Temporal\Client\Internal\Events\EventEmitterTrait;

final class Protocol implements ProtocolInterface
{
    use EventEmitterTrait;

    /**
     * @param string $headers
     * @return array
     * @throws \JsonException
     */
    public function decodeHeaders(string $headers): array
    {
        $result = Json::decode($headers, \JSON_OBJECT_AS_ARRAY);

        if ($result !== null && ! \is_array($result)) {
            throw new \LogicException('Error while decoding headers');
        }

        return $result ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function decodeCommands(string $message): iterable
    {
        $this->emit(self::ON_DECODING, [$message]);

        try {
            return $commands = Decoder::decode($message);
        } finally {
            $this->emit(self::ON_DECODED, [$commands ?? []]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function encode(iterable $commands): string
    {
        $this->emit(self::ON_ENCODING, [$commands]);

        try {
            return $result = Encoder::encode($commands);
        } finally {
            $this->emit(self::ON_ENCODED, [$result ?? '']);
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->removeAllListeners();
    }
}
