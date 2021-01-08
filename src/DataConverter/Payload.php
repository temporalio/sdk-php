<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use React\Promise\PromiseInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;

final class Payload implements \JsonSerializable
{
    #[Marshal(name: 'metadata')]
    private array $metadata;

    #[Marshal(name: 'data')]
    private string $data = '';

    /**
     * @param array $metadata
     * @param string $data
     * @return Payload
     */
    public static function create(array $metadata, string $data): Payload
    {
        $payload = new self();
        $payload->metadata = \array_map('\\base64_encode', $metadata);
        $payload->data = \base64_encode($data);

        return $payload;
    }

    /**
     * @param array $metadata
     * @param string|null $data
     * @return Payload
     */
    public static function createRaw(array $metadata, string $data = null): Payload
    {
        $payload = new self();
        $payload->metadata = $metadata;
        $payload->data = $data ?? '';

        return $payload;
    }


    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return \array_map('\\base64_decode', $this->metadata);
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return \base64_decode($this->data);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'Metadata' => $this->metadata,
            'Data' => $this->data,
        ];
    }

    /**
     * Unpack the server response into internal format based on return or argument type.
     *
     * @param DataConverterInterface $converter
     * @param PromiseInterface $promise
     * @param \ReflectionType|null $type
     * @return PromiseInterface
     */
    public static function fromPromise(
        DataConverterInterface $converter,
        PromiseInterface $promise,
        \ReflectionType $type = null
    ): PromiseInterface {
        return $promise->then(
            function ($value) use ($converter, $type) {
                if (!$value instanceof Payload || $value instanceof \Throwable) {
                    return $value;
                }

                return $converter->fromPayload($value, $type);
            }
        );
    }
}
