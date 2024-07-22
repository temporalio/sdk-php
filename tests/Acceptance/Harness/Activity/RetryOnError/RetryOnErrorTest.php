<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\RetryOnError;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;

class RetryOnErrorTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Workflow')] WorkflowStubInterface $stub): void
    {
        try {
            $stub->getResult();
            throw new \Exception('Expected WorkflowFailedException');
        } catch (WorkflowFailedException $e) {
            self::assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            /** @var ActivityFailure $failure */
            $failure = $e->getPrevious()->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertStringContainsStringIgnoringCase('activity attempt 5 failed', $failure->getOriginalMessage());
        }
    }
}
