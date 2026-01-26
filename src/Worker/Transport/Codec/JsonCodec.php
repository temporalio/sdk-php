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
use Temporal\Worker\Transport\Command\Server\TickInfo;

final class JsonCodec implements CodecInterface
{
    private int $maxDepth;
    private Decoder $parser;
    private Encoder $serializer;
    private \DateTimeZone $hostTimeZone;

    public function __construct(DataConverterInterface $dataConverter, int $maxDepth = 64)
    {
        $this->maxDepth = $maxDepth;

        $this->parser = new Decoder($dataConverter);
        $this->serializer = new Encoder($dataConverter);
        $this->hostTimeZone = new \DateTimeZone(\date_default_timezone_get());
    }

    public function encode(iterable $commands): string
    {
        try {
            $result = [];

            foreach ($commands as $command) {
                \assert($command instanceof CommandInterface);
                $result[] = $this->serializer->encode($command);
            }

            return \json_encode($result, \JSON_THROW_ON_ERROR, $this->maxDepth);
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function decode(string $batch, array $headers = []): iterable
    {
        try {
            $commands = \json_decode($batch, true, $this->maxDepth, \JSON_THROW_ON_ERROR);

            foreach ($commands as $command) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $info = new TickInfo(
                    time: (new \DateTimeImmutable($headers['tickTime'] ?? 'now'))->setTimezone($this->hostTimeZone),
                    historyLength: (int) ($headers['history_length'] ?? 0),
                    historySize: (int) ($headers['history_size'] ?? 0),
                    continueAsNewSuggested: (bool) ($headers['continue_as_new_suggested'] ?? false),
                    isReplaying: (bool) ($headers['replay'] ?? false),
                );

                \assert(\is_array($command));
                yield $this->parser->decode($command, $info);
            }
        } catch (\Throwable $e) {
            throw new ProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
