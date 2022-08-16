<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\DataConverterException;
use Temporal\Tests\Unit\UnitTestCase;

/**
 * @group unit
 * @group data-converter
 */
class DataConverterTestCase extends UnitTestCase
{
    /**
     * @return array[]
     */
    public function typesDataProvider(): array
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

            Type::TYPE_ARRAY  => [Type::TYPE_ARRAY, [1, 2, 3]],
            Type::TYPE_OBJECT => [Type::TYPE_OBJECT, (object)['field' => 'value']],
            Type::TYPE_STRING => [Type::TYPE_STRING, 'string'],
            Type::TYPE_BOOL   => [Type::TYPE_BOOL, true],
            Type::TYPE_INT    => [Type::TYPE_INT, 42],
            Type::TYPE_FLOAT  => [Type::TYPE_FLOAT, 0.1],
            Type::TYPE_VOID   => [Type::TYPE_VOID, null],

            Type::TYPE_ARRAY . ' (associative)  => ' . Type::TYPE_ARRAY => [Type::TYPE_ARRAY, ['field' => 'value']],
        ];
    }

    /**
     * @return array
     */
    public function negativeTypesDataProvider(): array
    {
        return [
            Type::TYPE_OBJECT . ' => ' . Type::TYPE_STRING => [Type::TYPE_STRING, (object)['field' => 'value']],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_STRING  => [Type::TYPE_STRING, [1, 2, 3]],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_STRING  => [Type::TYPE_STRING, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_STRING    => [Type::TYPE_STRING, 42],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_STRING   => [Type::TYPE_STRING, true],
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
            Type::TYPE_BOOL . ' => ' . Type::TYPE_INT   => [Type::TYPE_INT, true],
            Type::TYPE_VOID . ' => ' . Type::TYPE_INT   => [Type::TYPE_INT, null],

            Type::TYPE_OBJECT . ' => ' . Type::TYPE_FLOAT => [Type::TYPE_FLOAT, (object)['field' => 'value']],
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_FLOAT  => [Type::TYPE_FLOAT, [1, 2, 3]],
            Type::TYPE_INT . ' => ' . Type::TYPE_FLOAT    => [Type::TYPE_FLOAT, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_FLOAT => [Type::TYPE_FLOAT, 'string'],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_FLOAT   => [Type::TYPE_FLOAT, true],
            Type::TYPE_VOID . ' => ' . Type::TYPE_FLOAT   => [Type::TYPE_FLOAT, null],

            Type::TYPE_ARRAY . ' => ' . Type::TYPE_OBJECT  => [Type::TYPE_OBJECT, [1, 2, 3]],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_OBJECT  => [Type::TYPE_OBJECT, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_OBJECT    => [Type::TYPE_OBJECT, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_OBJECT => [Type::TYPE_OBJECT, 'string'],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_OBJECT   => [Type::TYPE_OBJECT, true],
            Type::TYPE_VOID . ' => ' . Type::TYPE_OBJECT   => [Type::TYPE_OBJECT, null],

            Type::TYPE_FLOAT . ' => ' . Type::TYPE_ARRAY  => [Type::TYPE_ARRAY, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_ARRAY    => [Type::TYPE_ARRAY, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_ARRAY => [Type::TYPE_ARRAY, 'string'],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_ARRAY   => [Type::TYPE_ARRAY, true],
            Type::TYPE_VOID . ' => ' . Type::TYPE_ARRAY   => [Type::TYPE_ARRAY, null],
        ];
    }

    /**
     * @return array[]
     * @throws \Exception
     */
    public function nullableTypesDataProvider(): array
    {
        return [
            Type::TYPE_ARRAY . ' => ' . Type::TYPE_VOID  => [Type::TYPE_VOID, [1, 2, 3]],
            Type::TYPE_OBJECT . ' => ' . Type::TYPE_VOID => [Type::TYPE_VOID, (object)['field' => 'value']],
            Type::TYPE_FLOAT . ' => ' . Type::TYPE_VOID  => [Type::TYPE_VOID, .42],
            Type::TYPE_INT . ' => ' . Type::TYPE_VOID    => [Type::TYPE_VOID, 42],
            Type::TYPE_STRING . ' => ' . Type::TYPE_VOID => [Type::TYPE_VOID, 'string'],
            Type::TYPE_BOOL . ' => ' . Type::TYPE_VOID   => [Type::TYPE_VOID, true],
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
     * @dataProvider typesDataProvider
     *
     * @param string $type
     * @param mixed $value
     */
    public function testPositiveConvert(string $type, $value): void
    {
        $converter = $this->create();

        $payload = $converter->toPayload($value);

        $this->assertEquals($value, $converter->fromPayload($payload, $type));
    }

    /**
     * @dataProvider negativeTypesDataProvider
     *
     * @param string $type
     * @param mixed $value
     */
    public function testConvertErrors(string $type, $value): void
    {
        $this->expectException(DataConverterException::class);

        $converter = $this->create();
        $converter->fromPayload($converter->toPayload($value), $type);
    }

    /**
     * @dataProvider nullableTypesDataProvider
     *
     * @param string $type
     * @param mixed $value
     */
    public function testNullableTypeCoercion(string $type, $value): void
    {
        $converter = $this->create();

        $payload = $converter->toPayload($value);

        $this->assertNull($converter->fromPayload($payload, $type));
    }
}
