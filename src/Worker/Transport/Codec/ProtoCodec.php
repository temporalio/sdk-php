<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Worker\Transport\Codec;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\ProtocolException;
use Temporal\Roadrunner\Internal\Frame;
use Temporal\Worker\Transport\Codec\ProtoCodec\Decoder;
use Temporal\Worker\Transport\Codec\ProtoCodec\Encoder;
use Temporal\Worker\Transport\Command\CommandInterface;

class ProtoCodec implements CodecInterface
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
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->parser = new Decoder($dataConverter);
        $this->serializer = new Encoder($dataConverter);
    }

    /**
     * {@inheritDoc}
     */
    public function encode(iterable $commands): string
    {
        try {
            $frame = new Frame();

            $messages = [];
            foreach ($commands as $command) {
                assert($command instanceof CommandInterface);
                $messages[] = $this->serializer->serialize($command);
            }

            $frame->setMessages($messages);

            return $frame->serializeToString();
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
            $frame = new Frame();
            $frame->mergeFromString($batch);

            foreach ($frame->getMessages() as $msg) {
                yield $this->parser->parse($msg);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
