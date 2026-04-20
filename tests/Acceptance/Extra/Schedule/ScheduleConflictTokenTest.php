<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Schedule\ScheduleConflictToken;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Update\ScheduleUpdate;
use Temporal\Client\Schedule\Update\ScheduleUpdateInput;
use Temporal\Client\ScheduleClientInterface;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Tests\Acceptance\App\TestCase;

/**
 * These tests require the CHASM (V2) scheduler to be enabled on the server. The legacy V1
 * signal-based scheduler silently drops updates on conflict token mismatch and therefore
 * cannot enforce optimistic concurrency end-to-end; CHASM handles UpdateSchedule via direct
 * RPC handlers and returns `serviceerror.NewFailedPrecondition("mismatched conflict token")`
 * on mismatch (see temporalio/temporal#8474).
 *
 * The acceptance test runner enables CHASM via two dynamic-config flags (see
 * {@see \Temporal\Tests\Acceptance\App\Runtime\TemporalStarter}):
 *
 *   history.enableChasm=true                     (base CHASM tree implementation)
 *   history.enableCHASMSchedulerCreation=true    (route new schedules to CHASM V2)
 */
class ScheduleConflictTokenTest extends TestCase
{
    #[Test]
    public function updateWithMatchingConflictTokenSucceeds(
        ScheduleClientInterface $client,
    ): void {
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(StartWorkflowAction::new('TestWorkflow'))
                ->withSpec(ScheduleSpec::new()->withStartTime('+1 hour')),
            ScheduleOptions::new()->withMemo(['key' => 'initial']),
        );

        try {
            $description = $handle->describe();
            $token = $description->conflictToken;
            self::assertNotSame('', $token);

            // Update using the fresh conflict token
            $handle->update(
                $description->schedule->withAction(
                    $description->schedule->action->withMemo(['key' => 'updated']),
                ),
                $token,
            );

            $actual = $handle->describe()->schedule->action;
            self::assertInstanceOf(StartWorkflowAction::class, $actual);
            self::assertSame('updated', $actual->memo->getValue('key'));
        } finally {
            $handle->delete();
        }
    }

    #[Test]
    public function updateWithStaleConflictTokenIsRejected(
        ScheduleClientInterface $client,
    ): void {
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(StartWorkflowAction::new('TestWorkflow'))
                ->withSpec(ScheduleSpec::new()->withStartTime('+1 hour')),
            ScheduleOptions::new(),
        );

        try {
            // Capture the initial token — will become stale after the next update.
            $staleToken = $handle->describe()->conflictToken;
            self::assertNotSame('', $staleToken);

            // First update (no token): applies and advances the server-side token.
            $handle->update(
                $handle->describe()->schedule->withAction(
                    StartWorkflowAction::new('TestWorkflow')->withMemo(['step' => 'first']),
                ),
            );
            \sleep(1);

            // Second update with the stale token: CHASM scheduler rejects with FAILED_PRECONDITION.
            $exception = null;
            try {
                $handle->update(
                    $handle->describe()->schedule->withAction(
                        StartWorkflowAction::new('TestWorkflow')->withMemo(['step' => 'second']),
                    ),
                    $staleToken,
                );
            } catch (ServiceClientException $e) {
                $exception = $e;
            }

            self::assertNotNull($exception, 'Expected stale conflict token to be rejected');
            self::assertSame(StatusCode::FAILED_PRECONDITION, $exception->getCode());
            self::assertStringContainsString('conflict token', $exception->getMessage());

            // Sanity check: the schedule still reflects the first update, not the second
            $current = $handle->describe()->schedule->action;
            self::assertInstanceOf(StartWorkflowAction::class, $current);
            self::assertSame('first', $current->memo->getValue('step'));
        } finally {
            $handle->delete();
        }
    }

    #[Test]
    public function updateWithEmptyTokenAlwaysApplies(
        ScheduleClientInterface $client,
    ): void {
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(StartWorkflowAction::new('TestWorkflow'))
                ->withSpec(ScheduleSpec::new()->withStartTime('+1 hour')),
            ScheduleOptions::new(),
        );

        try {
            // Two back-to-back updates without a token. Both must apply — passing no token
            // bypasses the optimistic check entirely.
            $handle->update(
                $handle->describe()->schedule->withAction(
                    StartWorkflowAction::new('TestWorkflow')->withMemo(['n' => '1']),
                ),
            );
            \sleep(1);

            $handle->update(
                $handle->describe()->schedule->withAction(
                    StartWorkflowAction::new('TestWorkflow')->withMemo(['n' => '2']),
                ),
            );
            \sleep(1);

            $current = $handle->describe()->schedule->action;
            self::assertInstanceOf(StartWorkflowAction::class, $current);
            self::assertSame('2', $current->memo->getValue('n'));
        } finally {
            $handle->delete();
        }
    }

    #[Test]
    public function conflictTokenChangesAfterEachUpdate(
        ScheduleClientInterface $client,
    ): void {
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(StartWorkflowAction::new('TestWorkflow'))
                ->withSpec(ScheduleSpec::new()->withStartTime('+1 hour')),
            ScheduleOptions::new(),
        );

        try {
            $tokenBefore = $handle->describe()->conflictToken;

            $handle->update(function (ScheduleUpdateInput $input): ScheduleUpdate {
                return ScheduleUpdate::new(
                    $input->description->schedule->withAction(
                        StartWorkflowAction::new('TestWorkflow')->withMemo(['v' => '1']),
                    ),
                );
            });

            $tokenAfter = $handle->describe()->conflictToken;

            self::assertNotSame('', $tokenBefore);
            self::assertNotSame('', $tokenAfter);
            self::assertNotSame(
                $tokenBefore,
                $tokenAfter,
                'Conflict token must change after a successful update',
            );
        } finally {
            $handle->delete();
        }
    }

    #[Test]
    public function closureUpdateUsesFreshTokenFromEachDescribe(
        ScheduleClientInterface $client,
    ): void {
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(StartWorkflowAction::new('TestWorkflow'))
                ->withSpec(ScheduleSpec::new()->withStartTime('+1 hour')),
            ScheduleOptions::new(),
        );

        // Second handle to the same schedule acts as a concurrent writer.
        $concurrent = $client->getHandle($handle->getID());

        try {
            // Advance the server token out-of-band. The closure-based update below must
            // still apply successfully: updateWithClosure() fetches a fresh description
            // (and therefore a fresh token) on every invocation, so the token it sends
            // always matches the current server state.
            $concurrent->update(
                $concurrent->describe()->schedule->withAction(
                    StartWorkflowAction::new('TestWorkflow')->withMemo(['by' => 'concurrent']),
                ),
            );
            \sleep(1);

            $closureCalls = 0;
            $handle->update(function (ScheduleUpdateInput $input) use (&$closureCalls): ScheduleUpdate {
                ++$closureCalls;
                return ScheduleUpdate::new(
                    $input->description->schedule->withAction(
                        StartWorkflowAction::new('TestWorkflow')->withMemo(['by' => 'closure']),
                    ),
                );
            });
            \sleep(1);

            self::assertSame(
                1,
                $closureCalls,
                'Closure should run once when there is no concurrent update happening during the call',
            );

            $final = $handle->describe()->schedule->action;
            self::assertInstanceOf(StartWorkflowAction::class, $final);
            self::assertSame(
                'closure',
                $final->memo->getValue('by'),
                'Closure update should overwrite the earlier concurrent update',
            );
        } finally {
            $handle->delete();
        }
    }
}
