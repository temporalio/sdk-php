<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Unit\Declaration\Fixture\UnannotatedClass;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithMultipleMethods;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithoutHandler;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @group unit
 * @group declaration
 */
class WorkflowNegativeDeclarationTestCase extends DeclarationTestCase
{
    /**
     * @testdox Validate errors while loading workflow without WorkflowInterface attribute
     * @dataProvider workflowReaderDataProvider
     *
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    public function testWorkflowWithoutInterfaceAttribute(WorkflowReader $reader): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\vsprintf(
            'Workflow class %s or one of his parents (i.e. class, interface or trait) must contain #[%s] attribute',
            [
                UnannotatedClass::class,
                WorkflowInterface::class,
            ]
        ));

        $reader->fromClass(UnannotatedClass::class);
    }

    /**
     * @testdox Workflow handlers duplication
     * @dataProvider workflowReaderDataProvider
     *
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    public function testWorkflowMethodDuplication(WorkflowReader $reader): void
    {
        $this->expectException(\LogicException::class);

        $reader->fromClass(WorkflowWithMultipleMethods::class);
    }
}
