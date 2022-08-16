<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Doctrine\Common\Annotations\Reader;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\Exception\DataConverterException;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;

/**
 * Json converter with the ability to serialize/unserialize DTO objects using JSON.
 */
class JsonConverter extends Converter
{
    /**
     * @var int
     */
    public const JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION;

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
            $value = $value instanceof \stdClass
                ? $value
                : $this->marshaller->marshal($value)
            ;
        }

        try {
            return $this->create(\json_encode($value, self::JSON_FLAGS));
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param Payload $payload
     * @param Type $type
     * @return mixed|void
     * @throws DataConverterException
     */
    public function fromPayload(Payload $payload, Type $type)
    {
        try {
            $data = \json_decode(
                $payload->getData(),
                $type->getName() === Type::TYPE_ARRAY,
                512,
                self::JSON_FLAGS
            );
        } catch (\Throwable $e) {
            throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
        }

        switch ($type->getName()) {
            case Type::TYPE_ANY:
                return $data;

            case Type::TYPE_STRING:
                if (!\is_string($data)) {
                    throw $this->errorInvalidType($type, $data);
                }

                return $data;

            case Type::TYPE_FLOAT:
                if (!\is_float($data)) {
                    throw $this->errorInvalidType($type, $data);
                }

                return $data;

            case Type::TYPE_INT:
                if (!\is_int($data)) {
                    throw $this->errorInvalidType($type, $data);
                }

                return $data;

            case Type::TYPE_BOOL:
                if (!\is_bool($data)) {
                    throw $this->errorInvalidType($type, $data);
                }

                return $data;

            case Type::TYPE_ARRAY:
                if (!\is_array($data)) {
                    throw $this->errorInvalidType($type, $data);
                }

                return $data;

            case Type::TYPE_OBJECT:
                if (!\is_object($data)) {
                    throw $this->errorInvalidType($type, $data);
                }

                return $data;
        }

        if ((\is_object($data) || \is_array($data)) && $type->isClass()) {
            try {
                $reflection = new \ReflectionClass($type->getName());
                if (PHP_VERSION_ID >= 80104 && $reflection->isEnum()) {
                    return $reflection->getConstant($data->name);
                }

                $instance = $reflection->newInstanceWithoutConstructor();
            } catch (\ReflectionException $e) {
                throw new DataConverterException($e->getMessage(), $e->getCode(), $e);
            }

            return $this->marshaller->unmarshal($this->toHashMap($data), $instance);
        }

        throw $this->errorInvalidTypeName($type);
    }

    /**
     * @param object|array $context
     * @return array
     */
    private function toHashMap($context): array
    {
        if (\is_object($context)) {
            $context = (array)$context;
        }

        foreach ($context as $key => $value) {
            if (\is_object($value) || \is_array($value)) {
                $context[$key] = $this->toHashMap($value);
            }
        }

        return $context;
    }

    /**
     * @param Type $type
     * @return DataConverterException
     */
    private function errorInvalidTypeName(Type $type): DataConverterException
    {
        $message = \vsprintf('Type named "%s" is not a valid type name', [
            $type->getName(),
        ]);

        return new DataConverterException($message);
    }

    /**
     * @param Type $type
     * @param mixed $data
     * @return DataConverterException
     */
    private function errorInvalidType(Type $type, $data): DataConverterException
    {
        $message = \vsprintf('The passed value of type "%s" can not be converted to required type "%s"', [
            \get_debug_type($data),
            $type->getName(),
        ]);

        return new DataConverterException($message);
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
            return new SelectiveReader([new AnnotationReader(), new AttributeReader()]);
        }

        return new AttributeReader();
    }
}
