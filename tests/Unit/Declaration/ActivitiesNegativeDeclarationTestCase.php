<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Tests\Unit\Declaration\Fixture\ActivityNamesDuplication;
use Temporal\Tests\Unit\Declaration\Fixture\ActivityWithPrivateMethod;
use Temporal\Tests\Unit\Declaration\Fixture\ActivityWithProtectedMethod;
use Temporal\Tests\Unit\Declaration\Fixture\ActivityWithPublicStaticMethod;

/**
 * @group unit
 * @group declaration
 */
class ActivitiesNegativeDeclarationTestCase extends AbstractDeclaration
{
    /**
     * @param ActivityReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Checks for errors when reading activity class methods with the same name")]
    #[DataProvider('activityReaderDataProvider')]
    public function testNameConflict(ActivityReader $reader): void
    {
        $reflection = new \ReflectionMethod(ActivityNamesDuplication::class, 'a');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\vsprintf(
            'An Activity method %s::b() with the same name "a" has already been previously ' .
            'registered in %s:%d',
            [
                ActivityNamesDuplication::class,
                $reflection->getFileName(),
                $reflection->getStartLine()
            ]
        ));

        $activities = $reader->fromClass(ActivityNamesDuplication::class);
    }

    /**
     * @param ActivityReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Checks for errors when declaring a private method with an activity method attribute declaration")]
    #[DataProvider('activityReaderDataProvider')]
    public function testPrivateMethod(ActivityReader $reader): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\vsprintf(
            'An Activity method can only be a public non-static method, but %s::%s() does not meet these criteria',
            [
                ActivityWithPrivateMethod::class,
                'invalidActivityPrivateMethod'
            ]
        ));

        $reader->fromClass(ActivityWithPrivateMethod::class);
    }

    /**
     * @param ActivityReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Checks for errors when declaring a protected method with an activity method attribute declaration")]
    #[DataProvider('activityReaderDataProvider')]
    public function testProtectedMethod(ActivityReader $reader): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\vsprintf(
            'An Activity method can only be a public non-static method, but %s::%s() does not meet these criteria',
            [
                ActivityWithProtectedMethod::class,
                'invalidActivityProtectedMethod'
            ]
        ));

        $reader->fromClass(ActivityWithProtectedMethod::class);
    }

    /**
     * @param ActivityReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Checks for errors when declaring a public static method with an activity method attribute declaration")]
    #[DataProvider('activityReaderDataProvider')]
    public function testPublicStaticMethod(ActivityReader $reader): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\vsprintf(
            'An Activity method can only be a public non-static method, but %s::%s() does not meet these criteria',
            [
                ActivityWithPublicStaticMethod::class,
                'invalidActivityPublicStaticMethod'
            ]
        ));

        $reader->fromClass(ActivityWithPublicStaticMethod::class);
    }
}
