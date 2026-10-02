<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Declaration\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Workflow\SimpleWorkflow;

#[CoversClass(WorkflowReader::class)]
final class WorkflowReaderTestCase extends TestCase
{
    public function testPrototypeIsReadOncePerClass(): void
    {
        $reader = new WorkflowReader(new AttributeReader());

        $first = $reader->fromClass(SimpleWorkflow::class);

        self::assertSame('SimpleWorkflow', $first->getID());
        self::assertSame($first, $reader->fromClass(SimpleWorkflow::class));
        self::assertSame($first, $reader->fromObject(new SimpleWorkflow()));
    }

    public function testInvalidClassIsNotCached(): void
    {
        $reader = new WorkflowReader(new AttributeReader());

        foreach ([1, 2] as $attempt) {
            try {
                $reader->fromClass(\stdClass::class);
                self::fail("Attempt $attempt did not throw");
            } catch (\LogicException) {
            }
        }
        self::assertTrue(true);
    }
}
