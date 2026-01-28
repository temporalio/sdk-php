<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PhpUnitPatchTest extends TestCase
{
    public function testInstanceOf(): void
    {
        try {
            $this->assertInstanceOf(\DateTimeImmutable::class, new \DateTime());

            $this->fail('Expected exception not thrown.');
        } catch (\Throwable $e) {
            $this->assertEquals(
                'Failed asserting that an instance of class DateTime is an instance of class DateTimeImmutable.',
                $e->getMessage(),
            );
        }
    }
}
