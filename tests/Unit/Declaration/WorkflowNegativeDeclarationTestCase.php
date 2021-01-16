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
use Temporal\Workflow\WorkflowMethod;

/**
 * @group unit
 * @group declaration
 */
class WorkflowNegativeDeclarationTestCase extends DeclarationTestCase
{
    /**
     * @testdox Validate errors while loading invalid workflow
     * @dataProvider workflowReaderDataProvider
     *
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    public function testUnannotatedWorkflow(WorkflowReader $reader): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\vsprintf(
            'Can not find workflow handler, because class %s has no method marked with #[%s] attribute',
            [
                UnannotatedClass::class,
                WorkflowMethod::class,
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
