<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\DataConverterException;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group data-converter
 */
class DataConverterTestCase extends AbstractUnit
{
    /**
     * @return array[]
     */
    public static function typesDataProvider(): array
    {
        return [
            // Any
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_ANY  => [Type::TYPE_ANY, 0.1],
            Type::TYPE_INT . ' => ' . Type::TYPE_ANY    => [Type::TYPE_ANY, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_ANY => [Type::TYPE_ANY, 'string'],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_ANY   => [Type::TYPE_ANY, true],
            Type::TYPE_VOID . ' => ' . Type::TYPE_ANY   => [Type::TYPE_ANY, null],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_ANY  => [Type::TYPE_ANY, []],
            Type::TYPE_OBJECT . ' => ' . Type::TYPE_ANY => [Type::TYPE_ANY, (object)[]],
            Type::TYPE_NULL . ' => ' . Type::TYPE_ANY   => [Type::TYPE_ANY, null],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_ANY   => [Type::TYPE_ANY, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_ANY  => [Type::TYPE_ANY, false],

            Type::TYPE_ARRAY  => [Type::TYPE_ARRAY, [1, 2, 3]],
            Type::TYPE_OBJECT => [Type::TYPE_OBJECT, (object)['field' => 'value']],
            Type::TYPE_STRING => [Type::TYPE_STRING, 'string'],
            Type::TYPE_BOOL   => [Type::TYPE_BOOL, true],
            Type::TYPE_INT    => [Type::TYPE_INT, 42],
            Type::TYPE_FLOAT  => [Type::TYPE_FLOAT, 0.1],
            Type::TYPE_VOID   => [Type::TYPE_VOID, null],
            Type::TYPE_NULL   => [Type::TYPE_NULL, null],
            Type::TYPE_TRUE   => [Type::TYPE_TRUE, true],
            Type::TYPE_FALSE  => [Type::TYPE_FALSE, false],

            Type::TYPE_ARRAY . ' (associative)  => ' . Type::TYPE_ARRAY => [Type::TYPE_ARRAY, ['field' => 'value']],
        ];
    }

    /**
     * @return array
     */
    public static function negativeTypesDataProvider(): array
    {
        return [
            Type::TYPE_OBJECT . ' => ' . Type::TYPE_STRING => [Type::TYPE_STRING, (object)['field' => 'value']],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_STRING  => [Type::TYPE_STRING, [1, 2, 3]],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_STRING  => [Type::TYPE_STRING, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_STRING    => [Type::TYPE_STRING, 42],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_STRING   => [Type::TYPE_STRING, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_STRING  => [Type::TYPE_STRING, false],
            Type::TYPE_VOID . ' => ' . Type::TYPE_STRING   => [Type::TYPE_STRING, null],

            Type::TYPE_OBJECT . ' => ' . Type::TYPE_BOOL => [Type::TYPE_BOOL, (object)['field' => 'value']],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_BOOL  => [Type::TYPE_BOOL, [1, 2, 3]],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_BOOL  => [Type::TYPE_BOOL, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_BOOL    => [Type::TYPE_BOOL, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_BOOL => [Type::TYPE_BOOL, 'string'],
            Type::TYPE_VOID . ' => ' . Type::TYPE_BOOL   => [Type::TYPE_BOOL, null],

            Type::TYPE_OBJECT . ' => ' . Type::TYPE_INT => [Type::TYPE_INT, (object)['field' => 'value']],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_INT  => [Type::TYPE_INT, [1, 2, 3]],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_INT  => [Type::TYPE_INT, .42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_INT => [Type::TYPE_INT, 'string'],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_INT   => [Type::TYPE_INT, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_INT  => [Type::TYPE_INT, false],
            Type::TYPE_VOID . ' => ' . Type::TYPE_INT   => [Type::TYPE_INT, null],

            Type::TYPE_OBJECT . ' => ' . Type::TYPE_FLOAT => [Type::TYPE_FLOAT, (object)['field' => 'value']],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_FLOAT  => [Type::TYPE_FLOAT, [1, 2, 3]],
            Type::TYPE_INT . ' => ' . Type::TYPE_FLOAT    => [Type::TYPE_FLOAT, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_FLOAT => [Type::TYPE_FLOAT, 'string'],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_FLOAT   => [Type::TYPE_FLOAT, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_FLOAT  => [Type::TYPE_FLOAT, false],
            Type::TYPE_VOID . ' => ' . Type::TYPE_FLOAT   => [Type::TYPE_FLOAT, null],

            Type::TYPE_ARRAY . ' => ' . Type::TYPE_OBJECT  => [Type::TYPE_OBJECT, [1, 2, 3]],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_OBJECT  => [Type::TYPE_OBJECT, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_OBJECT    => [Type::TYPE_OBJECT, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_OBJECT => [Type::TYPE_OBJECT, 'string'],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_OBJECT   => [Type::TYPE_OBJECT, true],
            Type::TYPE_VOID . ' => ' . Type::TYPE_OBJECT   => [Type::TYPE_OBJECT, null],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_OBJECT   => [Type::TYPE_OBJECT, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_OBJECT  => [Type::TYPE_OBJECT, false],

            Type::TYPE_FLOAT . ' => ' . Type::TYPE_ARRAY  => [Type::TYPE_ARRAY, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_ARRAY    => [Type::TYPE_ARRAY, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_ARRAY => [Type::TYPE_ARRAY, 'string'],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_ARRAY   => [Type::TYPE_ARRAY, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_ARRAY  => [Type::TYPE_ARRAY, false],
            Type::TYPE_VOID . ' => ' . Type::TYPE_ARRAY   => [Type::TYPE_ARRAY, null],
        ];
    }

    /**
     * @return array[]
     * @throws \Exception
     */
    public static function nullableTypesDataProvider(): array
    {
        return [
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_VOID  => [Type::TYPE_VOID, [1, 2, 3]],
            Type::TYPE_OBJECT . ' => ' . Type::TYPE_VOID => [Type::TYPE_VOID, (object)['field' => 'value']],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_VOID  => [Type::TYPE_VOID, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_VOID    => [Type::TYPE_VOID, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_VOID => [Type::TYPE_VOID, 'string'],
            Type::TYPE_TRUE . ' => ' . Type::TYPE_VOID   => [Type::TYPE_VOID, true],
            Type::TYPE_FALSE . ' => ' . Type::TYPE_VOID   => [Type::TYPE_VOID, false],
        ];
    }

    /**
     * @return DataConverterInterface
     */
    protected function create(): DataConverterInterface
    {
        return DataConverter::createDefault();
    }

    /**
     * @param string $type
     * @param mixed $value
     */
    #[DataProvider('typesDataProvider')]
    public function testPositiveConvert(string $type, $value): void
    {
        $converter = $this->create();

        $payload = $converter->toPayload($value);

        $this->assertEquals($value, $converter->fromPayload($payload, $type));
    }

    /**
     * @param string $type
     * @param mixed $value
     */
    #[DataProvider('negativeTypesDataProvider')]
    public function testConvertErrors(string $type, $value): void
    {
        $this->expectException(DataConverterException::class);

        $converter = $this->create();
        $converter->fromPayload($converter->toPayload($value), $type);
    }

    /**
     * @param string $type
     * @param mixed $value
     */
    #[DataProvider('nullableTypesDataProvider')]
    public function testNullableTypeCoercion(string $type, $value): void
    {
        $converter = $this->create();

        $payload = $converter->toPayload($value);

        $this->assertNull($converter->fromPayload($payload, $type));
    }
}
