<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\TypedSearchAttributes;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\TypedSearchAttributes;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
class TypedSearchAttributesTest extends TestCase
{
    #[Test]
    public function testStartWithTypedSearchAttributes(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_TypedSearchAttributes',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withTypedSearchAttributes(
                    TypedSearchAttributes::empty()
                        ->withValue(SearchAttributeKey::forFloat('testFloat'), 1.1)
                        ->withValue(SearchAttributeKey::forInteger('testInt'), -2)
                        ->withValue(SearchAttributeKey::forBool('testBool'), false)
                        ->withValue(SearchAttributeKey::forString('testString'), 'foo')
                        ->withValue(SearchAttributeKey::forKeyword('testKeyword'), 'bar')
                        ->withValue(SearchAttributeKey::forKeywordList('testKeywordList'), ['baz'])
                        ->withValue(
                            SearchAttributeKey::forDatetime('testDatetime'),
                            new \DateTimeImmutable('2019-01-01T00:00:00Z'),
                        )
                ),
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
            'testString' => 'foo',
            'testKeyword' => 'bar',
            'testKeywordList' => ['baz'],
            'testDatetime' => (new \DateTimeImmutable('2019-01-01T00:00:00Z'))
                ->format(\DateTimeInterface::RFC3339),
        ], (array)$result);
    }

    #[Test]
    public function testUpsertTypedSearchAttributes(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_TypedSearchAttributes',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withSearchAttributes([
                    'testBool' => false,
                    'testInt' => -2,
                    'testFloat' => 1.1,
                    'testString' => 'foo',
                    'testKeyword' => 'bar',
                    'testKeywordList' => ['baz'],
                    'testDatetime' => (new \DateTimeImmutable('2019-01-01T00:00:00Z'))
                        ->format(\DateTimeInterface::RFC3339),
                ])
        );

        $toSend = [
            'testBool' => true,
            'testInt' => 42,
            'testFloat' => 3.25,
            'testString' => 'foo bar baz',
            'testKeyword' => 'foo-bar-baz',
            'testKeywordList' => ['foo', 'bar', 'baz'],
            'testDatetime' => (new \DateTimeImmutable('2021-01-01T00:00:00Z'))->format(\DateTimeInterface::RFC3339),
        ];

        /** @see TestWorkflow::handle() */
        $client->start($stub);
        try {
            // Send an empty list of TSA
            $stub->signal('setAttributes', []);

            $stub->update('setAttributes', $toSend);

            // Get Search Attributes using Client API
            $clientSA = \array_intersect_key(
                $toSend,
                $stub->describe()->info->searchAttributes->getValues(),
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

        $this->assertSame($toSend, $clientSA);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_TypedSearchAttributes")]
    public function handle()
    {
        yield Workflow::await(
            fn(): bool => $this->exit,
        );

        return Workflow::getInfo()->searchAttributes;
    }

    #[Workflow\UpdateMethod]
    public function setAttributes(array $searchAttributes): void
    {
        $updates = [];
        /**  @var SearchAttributeKey $key */
        foreach (Workflow::getInfo()->typedSearchAttributes as $key => $value) {
            if (!\array_key_exists($key->getName(), $searchAttributes)) {
                continue;
            }

            $updates[] = $key->valueSet($searchAttributes[$key->getName()]);
        }

        Workflow::upsertTypedSearchAttributes(...$updates);
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
