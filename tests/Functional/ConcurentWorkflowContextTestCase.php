<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Common\Uuid;
use Temporal\Tests\Fixtures\Splitter;
use Temporal\Tests\Fixtures\WorkerMock;

/**
 * @group workflow
 * @group functional
 */
class ConcurentWorkflowContextTestCase extends AbstractFunctional
{
    public function setUp(): void
    {
        parent::setUp();

        // emulate connection to parent server
        $_SERVER['RR_RPC'] = 'tcp://127.0.0.1:6001';
    }

    public function testContextInConcurrentWorkflows(): void
    {
        $worker = WorkerMock::createMock();

        // Generate log
        $workflows = 10;
        $log = $generators = [];

        $addWorkflow = function () use (&$generators) {
            $c = \count($generators) % 3;
            $c === 1 and $generators[] = $this->iterateVoidActivityStubWorkflow();
            $generators[] = $this->iterateOtherWorkflow($c === 2);
            $generators[] = $this->iterateOtherWorkflow($c === 0);
        };

        for ($i = 0; $i < $workflows; $i++) {
            $addWorkflow();
        }

        $stop = false;
        while ($generators !== []) {
            foreach ($generators as $i => $generator) {
                if ($generator->valid()) {
                    $log[] = $generator->current();
                    $generator->next();
                } else {
                    $i === 0 and $stop = true;
                    unset($generators[$i]);
                }
            }

            $stop or $addWorkflow();
        }

        $worker->run($this, Splitter::createFromString(\implode("\n", $log))->getQueue());
    }

    private function iterateVoidActivityStubWorkflow(): iterable
    {
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();
        $id1 = self::getId();
        $emptyPayloadStr= '';
        yield <<<EVENT
            [0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"VoidActivityStubWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"8646d54f9f6b22f407d6d22254eea9f5"}},"payloads":"$emptyPayloadStr"}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.3983204Z"}
            EVENT;
        yield <<<EVENT
            [0m	[{"id":$id1,"command":"ExecuteActivity","options":{"name":"SimpleActivity.empty","options":{"TaskQueueName":null,"ScheduleToCloseTimeout":0,"ScheduleToStartTimeout":0,"StartToCloseTimeout":5000000000,"HeartbeatTimeout":0,"WaitForCancellation":false,"ActivityID":"","RetryPolicy":null}},"payloads":"$emptyPayloadStr","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            EVENT;
        yield <<<EVENT
            [0m	[{"id":$id1,"payloads":"$emptyPayloadStr"}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.4849445Z"}
            EVENT;
        $id2 = self::getId();
        yield <<<EVENT
            [0m	[{"id":$id2,"command":"CompleteWorkflow","options":{},"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs","header":""}]	{"receive": true}
            EVENT;
        yield <<<EVENT
            [0m	[{"id":$id2,"payloads":"CiUKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SCyJjb21wbGV0ZWQi"},{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.5143426Z","replay":true}
            EVENT;
        yield <<<EVENT
            [0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            EVENT;
    }

    private function iterateOtherWorkflow(bool $withQuery = true): iterable
    {
        $uuid1 = Uuid::v4();
        $runId = Uuid::v4();
        $id1 = self::getId();
        // Run workflow
        yield <<<EVENT
            [0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$runId"},"WorkflowType":{"Name":"TestContextLeakWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"8646d54f9f6b22f407d6d22254eea9f5"}},"payloads":""}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.3983204Z"}
            EVENT;
        // Start timer
        yield <<<EVENT
            [0m	[{"id":$id1,"command":"NewTimer","options":{"ms":5000},"payloads":"","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            EVENT;

        if ($withQuery) {
            // Query
            yield <<<EVENT
                [0m	[{"command":"InvokeQuery","options":{"runId":"$runId","name":"wakeup"},"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.3987564Z"}
                EVENT;
            yield <<<EVENT
                [0m	[{"payloads":"ChwKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SAltd"}]	{"receive": true}
                EVENT;
        }

        // Timer fired
        yield <<<EVENT
            [0m	[{"id":$id1}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.4983204Z"}
            EVENT;

        // Complete WF
        $id2 = self::getId();
        yield <<<EVENT
            [0m	[{"id":$id2,"command":"CompleteWorkflow","options":{},"payloads":"Ch4KFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SBHRydWU=","header":""}]	{"receive": true}
            EVENT;
        yield <<<EVENT
            [0m	[{"id":$id2,"payloads":"CiUKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SCyJjb21wbGV0ZWQi"},{"command":"DestroyWorkflow","options":{"runId":"$runId"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.5983204Z","replay":true}
            EVENT;
        yield <<<EVENT
            [0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            EVENT;
    }

    private static function getId(): int
    {
        static $id = 9000;
        return ++$id;
    }
}
