<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Workflow\ScopeContext;

#[CoversClass(ScopeContext::class)]
final class ScopeContextCloneFiberModeTestCase extends TestCase
{
    public function testDefaultFiberModeIsFalse(): void
    {
        $context = $this->makeScopeContext();

        self::assertFalse($context->isFiberMode());
    }

    public function testSetFiberModeFlipsFlag(): void
    {
        $context = $this->makeScopeContext();

        $context->setFiberMode(true);
        self::assertTrue($context->isFiberMode());

        $context->setFiberMode(false);
        self::assertFalse($context->isFiberMode());
    }

    public function testCloneDoesNotShareFiberModeWithParent(): void
    {
        $parent = $this->makeScopeContext();
        $parent->setFiberMode(true);

        $clone = clone $parent;
        self::assertTrue($clone->isFiberMode());

        $clone->setFiberMode(false);
        self::assertFalse($clone->isFiberMode());
        self::assertTrue(
            $parent->isFiberMode(),
            'Parent context fiberMode flag must not be affected by clone mutation',
        );
    }

    public function testParentMutationDoesNotPropagateToExistingClone(): void
    {
        $parent = $this->makeScopeContext();
        $parent->setFiberMode(true);

        $clone = clone $parent;
        $parent->setFiberMode(false);

        self::assertTrue(
            $clone->isFiberMode(),
            'Clone fiberMode flag must not be affected by parent mutation',
        );
    }

    private function makeScopeContext(): ScopeContext
    {
        return (new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor();
    }
}
