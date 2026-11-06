<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Worker;

use PHPUnit\Framework\TestCase;
use Temporal\Api\History\V1\History;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Worker\Core\CoreReplayer;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Worker\Core\ReplayFailedException;
use Temporal\Worker\Core\WorkflowActivations;
use Temporal\Tests\Core\Replayers;

final class CoreReplayerTestCase extends TestCase
{
    private const STARTED_EVENT_ID = 1;
    private const FULL_HISTORY = 0;
    private const FIRST_TASK_EVENT_ID = 3;
    private const SHORT_IDLE_TIMEOUT_SECONDS = 1.0;

    public function testEmptyHistoryIsRejected(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('The history has no events'));
        CoreWorkerFactory::create()->replay(new History(), 'replay');
    }

    public function testHistoryOfAnUnknownTaskQueueIsRejected(): void
    {
        $this->expectException(\OutOfRangeException::class);
        CoreWorkerFactory::create()->replay(Replayers::history(self::FULL_HISTORY), 'replay');
    }

    public function testReplayWithoutActivationTimesOut(): void
    {
        $replayer = new CoreReplayer(Bridge::shared(), self::SHORT_IDLE_TIMEOUT_SECONDS);

        try {
            $replayer->replay(Replayers::history(self::STARTED_EVENT_ID), 'replay', Replayers::config(), self::activations(static fn(): array => []));
            self::fail('The replay must time out');
        } catch (ReplayFailedException $e) {
            self::assertSame('The replay got no activation for 1 seconds', $e->getMessage());
            self::assertFalse($e->nonDeterministic);
        }
    }

    public function testRejectedCompletionFailsTheReplayAndForeignEventsAreSkipped(): void
    {
        $bridge = Bridge::shared();
        $other = $bridge->newReplayer(Replayers::config(), Replayers::history(self::STARTED_EVENT_ID)->serializeToString(), 'other');
        $replayTag = (int) (new \ReflectionClass(CoreReplayer::class))->getStaticPropertyValue('tag') + 1;
        $evicted = false;
        $activations = self::activations(static function (array $messages) use ($bridge, $other, $replayTag, &$evicted): array {
            if ($messages[0]->getName() === 'StartWorkflow') {
                $bridge->completeActivityTask($other, $replayTag, 'x');
                $bridge->completeWorkflowActivation($other, $replayTag + 1, 'x');
            }
            if ($messages[0]->getName() === 'DestroyWorkflow') {
                $evicted = true;
                $bridge->completeWorkflowActivation($other, $replayTag, 'x');
            }
            return [];
        });

        try {
            (new CoreReplayer($bridge))->replay(Replayers::history(self::FIRST_TASK_EVENT_ID), 'replay', Replayers::config(), $activations);
            self::fail('The rejected completion must fail the replay');
        } catch (\RuntimeException $e) {
            self::assertStringStartsWith('sdk-core completion failed: Decode failure', $e->getMessage());
        } finally {
            $bridge->shutdownWorkers([$replayTag + 1 => $other]);
        }
        self::assertTrue($evicted);
    }



    private static function activations(\Closure $dispatch): WorkflowActivations
    {
        return new WorkflowActivations(DataConverter::createDefault(), $dispatch, 'default', 'default', [], false);
    }
}
