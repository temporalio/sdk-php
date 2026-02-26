<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\SearchAttributes;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\UpdateMethod;

#[CoversFunction('Temporal\Client\WorkflowOptions::withSearchAttributes')]
#[CoversFunction('Temporal\Workflow::upsertSearchAttributes')]
class SearchAttributesTest extends TestCase
{
    #[Test]
    public function testStartWithSearchAttributes(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_SearchAttributes',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withSearchAttributes([
                    'testFloat' => 1.1,
                    'testInt' => -2,
                    'testBool' => false,
                    'testText' => 'foo',
                    'testKeyword' => 'bar',
                    'testKeywordList' => ['baz'],
                    'testDatetime' => new \DateTimeImmutable('2019-01-01T00:00:00Z'),
                ]),
        );

        /** @see TestWorkflow::handle() */
        $client->start($stub);

        // Complete workflow
        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertEquals([
            'testBool' => false,
            'testInt' => -2,
            'testFloat' => 1.1,
            'testText' => 'foo',
            'testKeyword' => 'bar',
            'testKeywordList' => ['baz'],
            'testDatetime' => (new \DateTimeImmutable('2019-01-01T00:00:00Z'))
                ->format(\DateTimeInterface::RFC3339),
        ], (array)$result);
    }

    #[Test]
    public function testUpsertSearchAttributes(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_SearchAttributes',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withSearchAttributes([
                    'testFloat' => 1.1,
                    'testInt' => -2,
                    'testBool' => false,
                    'testText' => 'foo',
                    'testKeyword' => 'bar',
                    'testKeywordList' => ['baz'],
                    'testDatetime' => new \DateTimeImmutable('2019-01-01T00:00:00Z'),
                ]),
        );

        $toSend = [
            'testBool' => true,
            'testInt' => 42,
            'testFloat' => 1.0,
            'testText' => 'foo bar baz',
            'testKeyword' => 'foo-bar-baz',
            'testKeywordList' => ['foo', 'bar', 'baz'],
            'testDatetime' => '2021-01-01T00:00:00+00:00',
        ];

        /** @see TestWorkflow::handle() */
        $client->start($stub);
        try {
            // Send an empty list of TSA
            $stub->signal('setAttributes', []);

            $stub->update('setAttributes', $toSend);

            // Get Search Attributes using Client API
            $clientSA = \array_intersect_key(
                $stub->describe()->info->searchAttributes->getValues(),
                $toSend,
            );

            // Complete workflow
            /** @see TestWorkflow::exit */
            $stub->signal('exit');
        } catch (\Throwable $e) {
            $stub->terminate('test failed');
            throw $e;
        }

        // Get Search Attributes as a Workflow result
        $result = $stub->getResult();

        // Normalize datetime field
        $clientSA['testDatetime'] = (new \DateTimeImmutable($clientSA['testDatetime']))
            ->format(\DateTimeInterface::RFC3339);

        $this->assertEquals($toSend, $clientSA);
        $this->assertEquals($toSend, (array) $result);
    }

    #[Test]
    public function testUpsertSearchAttributesUnset(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_SearchAttributes',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withSearchAttributes([
                    'testFloat' => 1.1,
                    'testInt' => -2,
                    'testBool' => false,
                    'testText' => 'foo',
                    'testKeyword' => 'bar',
                    'testKeywordList' => ['baz'],
                    'testDatetime' => new \DateTimeImmutable('2019-01-01T00:00:00Z'),
                ]),
        );

        $toSend = [
            'testInt' => 42,
            'testBool' => null,
            'testText' => 'bar',
            'testKeyword' => null,
            'testKeywordList' => ['red'],
            'testDatetime' => null,
        ];

        /** @see TestWorkflow::handle() */
        $client->start($stub);
        try {
            $stub->update('setAttributes', $toSend);

            // Get Search Attributes using Client API
            $clientSA = \array_intersect_key(
                $stub->describe()->info->searchAttributes->getValues(),
                $toSend,
            );

            // Complete workflow
            /** @see TestWorkflow::exit */
            $stub->signal('exit');
        } catch (\Throwable $e) {
            $stub->terminate('test failed');
            throw $e;
        }

        // Get Search Attributes as a Workflow result
        $result = \array_intersect_key((array) $stub->getResult(), $toSend);

        $this->assertEquals(\array_filter($toSend), $clientSA);
        $this->assertEquals(\array_filter($toSend), $result);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_SearchAttributes")]
    public function handle()
    {
        yield Workflow::await(
            fn(): bool => $this->exit,
        );

        return Workflow::getInfo()->searchAttributes;
    }

    #[UpdateMethod]
    public function setAttributes(array $searchAttributes): void
    {
        Workflow::upsertSearchAttributes($searchAttributes);
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
