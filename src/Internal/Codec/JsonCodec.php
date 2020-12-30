<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Codec;

use Temporal\Exception\ProtocolException;
use Temporal\Internal\Codec\JsonCodec\Parser;
use Temporal\Internal\Codec\JsonCodec\Serializer;
use Temporal\Worker\Command\CommandInterface;

final class JsonCodec extends Codec
{
    /**
     * @var int
     */
    private int $depth;

    /**
     * @var Parser
     */
    private Parser $parser;

    /**
     * @var Serializer
     */
    private Serializer $serializer;

    /**
     * JsonCodec constructor.
     */
    public function __construct(int $depth = 64)
    {
        $this->depth = $depth;

        $this->parser = new Parser();
        $this->serializer = new Serializer();
    }

    /**
     * {@inheritDoc}
     */
    public function encode(iterable $commands): string
    {
        $this->emit(self::ON_ENCODING);

        try {
            $result = [];

            foreach ($commands as $command) {
                assert($command instanceof CommandInterface);

                $result[] = $this->serializer->serialize($command);
            }

            return \json_encode($result, \JSON_THROW_ON_ERROR, $this->depth);
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        } finally {
            $this->emit(self::ON_ENCODED);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $message): iterable
    {
        $this->emit(self::ON_DECODING);

        try {
            $commands = \json_decode($message, true, $this->depth, \JSON_THROW_ON_ERROR);

            foreach ($commands as $command) {
                assert(\is_array($command));

                yield $this->parser->parse($command);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        } finally {
            $this->emit(self::ON_DECODED);
        }
    }
}
