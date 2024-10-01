<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Spiral\Attributes\AttributeReader;
use Temporal\Common\CronSchedule;
use Temporal\Internal\Declaration\Instantiator\WorkflowInstantiator;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Unit\Declaration\Fixture\Inheritance\ExtendingWorkflow;
use Temporal\Tests\Unit\Declaration\Fixture\SimpleWorkflow;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithCron;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithCronAndRetry;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithCustomName;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithInterface;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithoutHandler;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithQueries;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithRetry;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithSignals;

/**
 * @group unit
 * @group declaration
 */
class WorkflowDeclarationTestCase extends AbstractDeclaration
{
    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow without handler")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithoutHandler(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithoutHandler::class);

        $this->assertNull($prototype->getHandler());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow without cron attribute (cron prototype value should be null)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithoutCron(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(SimpleWorkflow::class);

        $this->assertNull($prototype->getCronSchedule());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow with cron attribute (cron prototype value should not be null)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithCron(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithCron::class);

        $this->assertNotNull($prototype->getCronSchedule());
        $this->assertEquals(new CronSchedule('@daily'), $prototype->getCronSchedule());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow without method retry attribute (method retry prototype value should be null)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithoutRetry(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(SimpleWorkflow::class);

        $this->assertNull($prototype->getMethodRetry());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow with method retry attribute (method retry prototype value should not be null)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithRetry(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithRetry::class);

        $this->assertNotNull($prototype->getMethodRetry());
        $this->assertEquals(CarbonInterval::microsecond(42)->f,
            $prototype->getMethodRetry()->initialInterval->f
        );
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow with method retry and cron attributes (prototypes value should not be null)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithCronAndRetry(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithCronAndRetry::class);

        $this->assertNotNull($prototype->getCronSchedule());
        $this->assertNotNull($prototype->getMethodRetry());

        $this->assertEquals(new CronSchedule('@monthly'), $prototype->getCronSchedule());
        $this->assertEqualIntervals(CarbonInterval::microsecond(42), $prototype->getMethodRetry()->initialInterval);
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow without query methods (query methods count equals 0)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithoutQueries(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(SimpleWorkflow::class);

        $this->assertCount(0, $prototype->getQueryHandlers());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow with query methods (query methods count not equals 0)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithQueries(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithQueries::class);

        $queries = \array_keys($prototype->getQueryHandlers());
        $this->assertSame(['a', 'b'], $queries);
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow without signal methods (signal methods count equals 0)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithoutSignals(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(SimpleWorkflow::class);

        $this->assertCount(0, $prototype->getSignalHandlers());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Reading workflow with signal methods (signal methods count not equals 0)")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowWithSignals(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithSignals::class);

        $signals = \array_keys($prototype->getSignalHandlers());

        $this->assertSame(['a', 'b'], $signals);
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Workflow should be named same as method name")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowHandlerDefaultNaming(WorkflowReader $reader): void
    {
        $withoutName = $reader->fromClass(SimpleWorkflow::class);

        $this->assertSame('SimpleWorkflow', $withoutName->getID());
    }

    /**
     * @param WorkflowReader $reader
     * @throws \ReflectionException
     */
    #[TestDox("Workflow should be named same as the name specified in the workflow method attribute")]
    #[DataProvider('workflowReaderDataProvider')]
    public function testWorkflowHandlerWithName(WorkflowReader $reader): void
    {
        $prototype = $reader->fromClass(WorkflowWithCustomName::class);

        $this->assertSame('ExampleWorkflowName', $prototype->getID());
    }

    public function testHierarchicalWorkflow(): void
    {
        $instantiator = new WorkflowInstantiator(new \Temporal\Interceptor\SimplePipelineProvider());

        $instance = $instantiator->instantiate(
            new WorkflowPrototype(
                'extending',
                new \ReflectionMethod(ExtendingWorkflow::class, 'handler'),
                new \ReflectionClass(ExtendingWorkflow::class),
            ),
        );

        $this->assertInstanceOf(ExtendingWorkflow::class, $instance->getContext());
    }

    public function testWorkflowWithInterface(): void
    {
        $reader = new WorkflowReader(new AttributeReader());

        $result = $reader->fromClass(\Temporal\Tests\Workflow\AggregatedWorkflowImpl::class);

        $this->assertSame('AggregatedWorkflow', $result->getID());
    }
}
