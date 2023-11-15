<?php

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use DateTimeImmutable;
use Google\Protobuf\Duration;
use Google\Protobuf\Internal\MapField;
use Google\Protobuf\Internal\Message;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Timestamp;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;

/**
 * @internall
 */
final class ProtoToArrayConverter
{
    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {
    }

    public function convert(mixed $message): mixed
    {
        if (!$message instanceof Message) {
            return $message;
        }

        $mapper = $this->getMapper($message);
        return $mapper === null ? $message : $mapper($message);
    }

    /**
     * @return null|\Closure(Message): mixed
     */
    private function getMapper(Message $message): ?\Closure
    {
        $mapper = match ($message::class) {
            Timestamp::class => static fn(Timestamp $input): DateTimeImmutable => DateTimeImmutable::createFromFormat(
                'U.u',
                \sprintf('%d.%d', $input->getSeconds(), $input->getNanos() / 1000),
            ),
            Duration::class => static function (Duration $input): \DateInterval {
                $now = new \DateTimeImmutable('@0');
                return $now->diff(
                    $now->modify(
                        \sprintf('+%d seconds +%d microseconds', $input->getSeconds(), $input->getNanos() / 1000)
                    )
                );
            },
            SearchAttributes::class => fn(SearchAttributes $input): EncodedCollection => EncodedCollection::fromPayloadCollection(
                $input->getIndexedFields(),
                $this->converter,
            ),
            Memo::class => fn(Memo $input): EncodedCollection => EncodedCollection::fromPayloadCollection(
                $input->getFields(),
                $this->converter,
            ),

            default => null,
        };

        // Return mapper and skip Google Protobuf messages without mapper
        if ($mapper !== null || \str_starts_with($message::class, 'Google\\Protobuf\\')) {
            return $mapper;
        }

        // Default mapper
        return \Closure::bind(function (Message $input): array {
            $result = [];
            $reflection = new \ReflectionClass($input::class);
            foreach ($reflection->getProperties() as $property) {
                $name = $property->getName();
                $value = $input->{$name};

                if ($value instanceof Message) {
                    $result[$name] = $this->convert($value);
                    continue;
                }

                if ($value instanceof RepeatedField || $value instanceof MapField) {
                    $result[$name] = [];
                    foreach ($value as $key => $item) {
                        $result[$name][$key] = $this->convert($item);
                    }
                    continue;
                }

                $result[$name] = $value;
            }

            return $result;
        }, $this, $message::class);
    }
}
