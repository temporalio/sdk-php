<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Common\Priority;

final class PriorityTestCase extends TestCase
{
    public function testWithFairnessWeightValidValue(): void
    {
        $priority = Priority::new()->withFairnessWeight(1.0);

        $this->assertSame(1.0, $priority->fairnessWeight);
    }

    public function testWithFairnessWeightMinBoundary(): void
    {
        $priority = Priority::new()->withFairnessWeight(0.001);

        $this->assertSame(0.001, $priority->fairnessWeight);
    }

    public function testWithFairnessWeightMaxBoundary(): void
    {
        $priority = Priority::new()->withFairnessWeight(1000.0);

        $this->assertSame(1000.0, $priority->fairnessWeight);
    }

    #[DataProvider('invalidFairnessWeightProvider')]
    public function testWithFairnessWeightThrowsOnInvalidValue(float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FairnessWeight must be in the range [0.001, 1000].');

        Priority::new()->withFairnessWeight($value);
    }

    public static function invalidFairnessWeightProvider(): iterable
    {
        return [
            'zero' => [0.0],
            'below minimum' => [0.0009],
            'negative' => [-1.0],
            'above maximum' => [1000.1],
            'large value' => [9999.0],
        ];
    }

    public function testWithFairnessWeightIsImmutable(): void
    {
        $original = Priority::new();
        $modified = $original->withFairnessWeight(5.0);

        $this->assertNotSame($original, $modified);
        $this->assertSame(0.0, $original->fairnessWeight);
        $this->assertSame(5.0, $modified->fairnessWeight);
    }
}
