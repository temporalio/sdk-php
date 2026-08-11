<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow\Process;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Workflow\Process\Scope;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\FeatureFlags;

final class ScopeCancellationFlagTestCase extends TestCase
{
    private bool $flagBackup;

    protected function setUp(): void
    {
        $this->flagBackup = FeatureFlags::$propagateCancellationToNewScopes;
    }

    protected function tearDown(): void
    {
        FeatureFlags::$propagateCancellationToNewScopes = $this->flagBackup;
    }

    #[Test]
    public function onCancelHandlerRegisteredAfterCancelIsMissedWhenFlagDisabled(): void
    {
        FeatureFlags::$propagateCancellationToNewScopes = false;

        $scope = new CancelProbeScope(
            $this->createMock(WorkflowContext::class),
            $this->createMock(ScopeContext::class),
        );
        $scope->cancel();

        $fired = false;
        $scope->onCancel(static function () use (&$fired): void {
            $fired = true;
        });

        self::assertFalse($fired, 'With the flag disabled the late onCancel handler must not fire');
    }

    #[Test]
    public function onCancelHandlerRegisteredAfterCancelFiresImmediatelyWhenFlagEnabled(): void
    {
        FeatureFlags::$propagateCancellationToNewScopes = true;

        $reason = new \RuntimeException('stop');
        $scope = new CancelProbeScope(
            $this->createMock(WorkflowContext::class),
            $this->createMock(ScopeContext::class),
        );
        $scope->cancel($reason);

        $received = null;
        $scope->onCancel(static function (?\Throwable $e = null) use (&$received): void {
            $received = $e;
        });

        self::assertSame($reason, $received, 'With the flag enabled the late onCancel handler must fire with the cancel reason');
    }
}

final class CancelProbeScope extends Scope
{
    public function __construct(WorkflowContext $context, ScopeContext $scopeContext)
    {
        $this->context = $context;
        $this->scopeContext = $scopeContext;
    }

    protected function makeCurrent(): void
    {
        // no-op: avoid the global Workflow context facade in isolation
    }
}
