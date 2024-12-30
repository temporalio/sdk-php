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

    #[Workflow\SignalMethod]
    public function setAttributes(array $searchAttributes): void
    {
        $updates = [];
        foreach ($searchAttributes as $name => $value) {
            $updates[] = Workflow::getInfo()->typedSearchAttributes->getByName($name)->valueSet($value);
        }

        Workflow::upsertTypedSearchAttributes(...$updates);
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
