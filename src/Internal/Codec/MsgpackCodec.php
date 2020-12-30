<?php

namespace Temporal\Client\Internal\Codec;

use Temporal\Client\Exception\ProtocolException;
use Temporal\Client\Internal\Codec\JsonCodec\Parser;
use Temporal\Client\Internal\Codec\JsonCodec\Serializer;
use Temporal\Client\Worker\Command\CommandInterface;

final class MsgpackCodec extends Codec
{
    /**
     * @var Parser
     */
    private Parser $parser;

    /**
     * @var Serializer
     */
    private Serializer $serializer;

    /**
     * @var \Spiral\Goridge\RPC\Codec\MsgpackCodec
     */
    private \Spiral\Goridge\RPC\CodecInterface $codec;

    /**
     * JsonCodec constructor.
     */
    public function __construct()
    {
        $this->parser = new Parser();
        $this->serializer = new Serializer();
        $this->codec = new \Spiral\Goridge\RPC\Codec\MsgpackCodec();
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

            return $this->codec->encode($result);
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
            // todo: msg
            $commands = $this->codec->decode($message);

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
