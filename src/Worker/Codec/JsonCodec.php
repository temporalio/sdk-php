<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Codec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ProtocolException;
use Temporal\Worker\Codec\JsonCodec\Parser;
use Temporal\Worker\Codec\JsonCodec\Serializer;
use Temporal\Worker\Command\CommandInterface;

final class JsonCodec implements CodecInterface
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
     * @param DataConverterInterface $dataConverter
     * @param int $depth
     */
    public function __construct(DataConverterInterface $dataConverter, int $depth = 64)
    {
        $this->depth = $depth;

        $this->parser = new Parser($dataConverter);
        $this->serializer = new Serializer($dataConverter);
    }

    /**
     * {@inheritDoc}
     */
    public function encode(iterable $commands): string
    {
        try {
            $result = [];

            foreach ($commands as $command) {
                assert($command instanceof CommandInterface);

                $result[] = $this->serializer->serialize($command);
            }

            return \json_encode($result, \JSON_THROW_ON_ERROR, $this->depth);
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $message): iterable
    {
        try {
            $commands = \json_decode($message, true, $this->depth, \JSON_THROW_ON_ERROR);

            foreach ($commands as $command) {
                assert(\is_array($command));

                yield $this->parser->parse($command);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
