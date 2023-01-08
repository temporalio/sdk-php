<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\DeepDTO;

use ReflectionClass;
use ReflectionException;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;

/**
 * @requires PHP >= 8.1
 */
final class DeepDTOTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws ReflectionException
     */
    public function testMarshalAndUnmarshal(): void
    {
        if (PHP_VERSION_ID < 80104) {
            $this->markTestSkipped();
        }

        $manuallyCreatedParent = new ParentDTO(
            new ChildDTO('foo')
        );

        $parentDTOReflection = new ReflectionClass(ParentDTO::class);

        $marshalled = $this->marshal($manuallyCreatedParent);

        $unmarshalledParent = $this->unmarshal(
            $marshalled,
            $parentDTOReflection->newInstanceWithoutConstructor()
        );

        self::assertEquals($manuallyCreatedParent, $unmarshalledParent);
    }
}
