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
use Temporal\Worker\Codec\JsonCodec\Decoder;
use Temporal\Worker\Codec\JsonCodec\Encoder;
use Temporal\Worker\Command\CommandInterface;

final class JsonCodec implements CodecInterface
{
    /**
     * @var int
     */
    private int $maxDepth;

    /**
     * @var Decoder
     */
    private Decoder $parser;

    /**
     * @var Encoder
     */
    private Encoder $serializer;

    /**
     * @param DataConverterInterface $dataConverter
     * @param int $maxDepth
     */
    public function __construct(DataConverterInterface $dataConverter, int $maxDepth = 64)
    {
        $this->maxDepth = $maxDepth;

        $this->parser = new Decoder($dataConverter);
        $this->serializer = new Encoder($dataConverter);
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

            return \json_encode($result, \JSON_THROW_ON_ERROR, $this->maxDepth);
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
            $commands = \json_decode($message, true, $this->maxDepth, \JSON_THROW_ON_ERROR);

            foreach ($commands as $command) {
                assert(\is_array($command));
                yield $this->parser->parse($command);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
