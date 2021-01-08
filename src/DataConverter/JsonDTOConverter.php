<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineReader;
use Doctrine\Common\Annotations\Reader;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Exception\DataConverterException;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;

/**
 * Json converter with the ability to serialize/unserialize DTO objects using JSON.
 */
class JsonDTOConverter implements PayloadConverterInterface
{
    /**
     * @var string
     */
    private const RESERVED_ANNOTATIONS = [
        'readonly',
    ];

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface|null $marshaller
     */
    public function __construct(MarshallerInterface $marshaller = null)
    {
        $this->marshaller = $marshaller ?? self::createDefaultMarshaller();
    }

    /**
     * @return string
     */
    public function getEncodingType(): string
    {
        return EncodingKeys::METADATA_ENCODING_JSON;
    }

    /**
     * @param mixed $value
     * @return Payload|null
     * @throws DataConverterException
     */
    public function toPayload($value): ?Payload
    {
        if (\is_object($value)) {
            $value = $this->marshaller->marshal($value);
        }

        try {
            return Payload::create(
                [EncodingKeys::METADATA_ENCODING_KEY => EncodingKeys::METADATA_ENCODING_JSON],
                \json_encode($value, \JSON_THROW_ON_ERROR)
            );
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param Payload $payload
     * @param \ReflectionType|null $type
     * @return mixed|void
     * @throws DataConverterException
     */
    public function fromPayload(Payload $payload, ?\ReflectionType $type)
    {
        try {
            $data = \json_decode($payload->getData(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }

        if (\is_array($data) && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            try {
                $obj = new \ReflectionClass($type->getName());
            } catch (\ReflectionException $e) {
                throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
            }

            return $this->marshaller->unmarshal($data, $obj->newInstanceWithoutConstructor());
        }

        return $data;
    }

    /**
     * @return MarshallerInterface
     */
    private static function createDefaultMarshaller(): MarshallerInterface
    {
        return new Marshaller(new AttributeMapperFactory(self::createDefaultReader()));
    }

    /**
     * @return ReaderInterface
     */
    private static function createDefaultReader(): ReaderInterface
    {
        if (\interface_exists(Reader::class)) {
            foreach (self::RESERVED_ANNOTATIONS as $annotation) {
                DoctrineReader::addGlobalIgnoredName($annotation);
            }

            return new SelectiveReader([
                new AnnotationReader(),
                new AttributeReader(),
            ]);
        }

        return new AttributeReader();
    }
}
