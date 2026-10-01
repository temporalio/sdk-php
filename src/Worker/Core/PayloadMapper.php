<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Google\Protobuf\Internal\RepeatedField;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Interceptor\Header;
use Temporal\Worker\Transport\Command\RequestInterface;

final class PayloadMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {}

    /**
     * @param \Traversable<Payload> $payloads
     */
    public function values(\Traversable $payloads): ValuesInterface
    {
        return EncodedValues::fromPayloadCollection($payloads, $this->converter);
    }

    /**
     * @param list<mixed> $values
     */
    public function encode(array $values): ValuesInterface
    {
        return EncodedValues::fromValues($values, $this->converter);
    }

    public function valuesFromPayload(?Payload $payload): ?ValuesInterface
    {
        return $payload === null ? null : $this->values(new \ArrayIterator([$payload]));
    }

    /**
     * @param \Traversable<string, Payload> $fields
     */
    public function header(\Traversable $fields): Header
    {
        return Header::fromPayloadCollection($fields, $this->converter);
    }

    /**
     * @return array<string, Payload>
     */
    public function headerFields(RequestInterface $command): array
    {
        $header = $command->getHeader();
        \assert($header instanceof Header);
        $header->setDataConverter($this->converter);

        return \iterator_to_array($header->toHeader()->getFields());
    }

    public function payloads(ValuesInterface $values): RepeatedField
    {
        $values->setDataConverter($this->converter);

        return $values->toPayloads()->getPayloads();
    }

    public function firstPayload(?ValuesInterface $values): ?Payload
    {
        if ($values === null) {
            return null;
        }

        $payloads = $this->payloads($values);

        return \count($payloads) > 0 ? $payloads[0] : null;
    }

    public function payload(mixed $value): Payload
    {
        return $this->converter->toPayload($value);
    }

    /**
     * @return array<string, Payload>
     */
    public function collection(array $values): array
    {
        return EncodedCollection::fromValues($values, $this->converter)->toPayloadArray();
    }

    public function decodeCollection(\ArrayAccess $payloads): array
    {
        return EncodedCollection::fromPayloadCollection($payloads, $this->converter)->getValues();
    }

    public function decode(Payload $payload): mixed
    {
        return $this->converter->fromPayload($payload, null);
    }

    public function failure(\Throwable $error): Failure
    {
        return Failures::fromThrowable($error, $this->converter);
    }

    public function exception(Failure $failure): \Throwable
    {
        return FailureConverter::mapFailureToException($failure, $this->converter);
    }
}
