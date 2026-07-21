<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;

class InvocationResult
{
    public function __construct(protected Payloads $payloads) {}

    public static function fromValue(mixed $value, ?DataConverterInterface $dataConverter = null): static
    {
        $value = $value instanceof EncodedValues ? $value : EncodedValues::fromValues([$value], $dataConverter);

        /** @psalm-suppress UnsafeInstantiation */
        return new static($value->toPayloads());
    }

    public function toValue(Type|string|null $type = null, ?DataConverterInterface $dataConverter = null): mixed
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
