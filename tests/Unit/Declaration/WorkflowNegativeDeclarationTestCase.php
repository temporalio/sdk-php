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
use Temporal\Exception\InstantiationException;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Fixtures\PipelineProvider;
use Temporal\Tests\Unit\Declaration\Fixture\UnannotatedClass;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithMultipleMethods;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithoutHandler;
use Temporal\Workflow\WorkflowInterface;

/**
 * @group unit
 * @group declaration
 */
class WorkflowNegativeDeclarationTestCase extends AbstractDeclaration
{
    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Validate errors while loading workflow without WorkflowInterface attribute")]
    #[DataProvider('workflowReaderDataProvider')]
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
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Workflow handlers duplication")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowMethodDuplication(WorkflowReader $reader): void
    {
        $this->expectException(\LogicException::class);

        $reader->fromClass(WorkflowWithMultipleMethods::class);
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Workflow without handler instantiation")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithoutHandlerInstantiation(WorkflowReader $reader): void
    {
        $this->expectException(InstantiationException::class);
        $this->expectExceptionMessage(
            \sprintf('Unable to instantiate workflow "%s" without handler method', WorkflowWithoutHandler::class),
        );

        $protorype = $reader->fromClass(WorkflowWithoutHandler::class);

        (new WorkflowInstantiator())->instantiate($protorype);
    }
}
