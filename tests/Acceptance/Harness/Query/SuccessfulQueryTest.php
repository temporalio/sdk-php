<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Query\SuccessfulQuery;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SuccessfulQueryTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Harness_Query_SuccessfulQuery')]WorkflowStubInterface $stub): void
    {
        self::assertSame(0, $stub->query('get_counter')?->getValue(0));

        $stub->signal('inc_counter');
        self::assertSame(1, $stub->query('get_counter')?->getValue(0));

        $stub->signal('inc_counter');
        $stub->signal('inc_counter');
        $stub->signal('inc_counter');
        self::assertSame(4, $stub->query('get_counter')?->getValue(0));

        $stub->signal('finish');
        $stub->getResult();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $counter = 0;
    private bool $beDone = false;

    #[WorkflowMethod('Harness_Query_SuccessfulQuery')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[QueryMethod('get_counter')]
    public function getCounter(): int
    {
        return $this->counter;
    }

    #[SignalMethod('inc_counter')]
    public function incCounter(): void
    {
        ++$this->counter;
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
