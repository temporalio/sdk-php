<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;

final class ActivityInvocationResult
{
    public function __construct(protected Payloads $payloads)
    {
    }

    public static function fromValue(mixed $value, ?DataConverterInterface $dataConverter = null): ActivityInvocationResult {
        $value = $value instanceof EncodedValues ? $value : EncodedValues::fromValues([$value], $dataConverter);

        return new self($value->toPayloads());
    }

    public function toValue(Type|string $type = null, ?DataConverterInterface $dataConverter = null)
    {
        return $this->toEncodedValues($dataConverter)->getValue(0, $type);
    }

    public function toEncodedValues(?DataConverterInterface $dataConverter = null): EncodedValues
    {
        return EncodedValues::fromPayloads($this->payloads, $dataConverter);
    }

    public function __serialize(): array
    {
        return [
            'payloads' => $this->payloads->serializeToJsonString(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->payloads = new Payloads();
        $this->payloads->mergeFromJsonString($data['payloads']);
    }
}
