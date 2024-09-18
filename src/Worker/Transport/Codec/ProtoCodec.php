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
use RoadRunner\Temporal\DTO\V1\Frame;
use RoadRunner\Temporal\DTO\V1\Message;
use Temporal\Worker\Transport\Codec\ProtoCodec\Decoder;
use Temporal\Worker\Transport\Codec\ProtoCodec\Encoder;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\Server\TickInfo;

/**
 * @codeCoverageIgnore tested via roadrunner-temporal repository.
 */
final class ProtoCodec implements CodecInterface
{
    /**
     * @var Decoder
     */
    private Decoder $parser;

    /**
     * @var Encoder
     */
    private Encoder $encoder;

    /**
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->parser = new Decoder($dataConverter);
        $this->encoder = new Encoder($dataConverter);
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
                \assert($command instanceof CommandInterface);
                $messages[] = $this->encoder->encode($command);
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
    public function decode(string $batch, array $headers = []): iterable
    {
        static $tz = new \DateTimeZone('UTC');

        try {
            $frame = new Frame();
            $frame->mergeFromString($batch);

            /** @var Message $msg */
            foreach ($frame->getMessages() as $msg) {
                $info = new TickInfo(
                    time: new \DateTimeImmutable($headers['tickTime'] ?? $msg->getTickTime(), $tz),
                    historyLength: (int) ($headers['history_length'] ?? $msg->getHistoryLength()),
                    historySize: (int) ($headers['history_size'] ?? $msg->getHistorySize()),
                    continueAsNewSuggested: (bool) ($headers['continue_as_new_suggested'] ?? $msg->getContinueAsNewSuggested()),
                    isReplaying: (bool) ($headers['replay'] ?? $msg->getReplay()),
                );

                yield $this->parser->decode($msg, $info, $headers);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
