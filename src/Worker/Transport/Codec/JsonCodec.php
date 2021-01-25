<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Codec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ProtocolException;
use Temporal\Worker\Transport\Codec\JsonCodec\Decoder;
use Temporal\Worker\Transport\Codec\JsonCodec\Encoder;
use Temporal\Worker\Transport\Command\CommandInterface;

final class JsonCodec implements CodecInterface
{
    private int $maxDepth;
    private Decoder $parser;
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
                $result[] = $this->serializer->encode($command);
            }

            return \json_encode($result, \JSON_THROW_ON_ERROR, $this->maxDepth);
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $batch): iterable
    {
        try {
            $commands = \json_decode($batch, true, $this->maxDepth, \JSON_THROW_ON_ERROR);

            foreach ($commands as $command) {
                assert(\is_array($command));
                yield $this->parser->decode($command);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
