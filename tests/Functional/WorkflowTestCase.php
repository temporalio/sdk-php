<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Common\Uuid;
use Temporal\Tests\Fixtures\Splitter;
use Temporal\Tests\Fixtures\WorkerMock;

/**
 * @group workflow
 * @group functional
 */
class WorkflowTestCase extends AbstractFunctional
{
    public function testSplitter(): void
    {
        $splitter = Splitter::create('Test_ExecuteSimpleWorkflow_1.log');

        $this->assertNotEmpty($splitter->getQueue());
    }

    public function testSimpleWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteSimpleWorkflow_1.log')->getQueue());
    }

    public function testTimer(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_Timer.log')->getQueue());
    }

    public function testGetQuery(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_GetQuery.log')->getQueue());
    }

    public function testCancelledWithCompensationWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_CancelledWithCompensationWorkflow.log')->getQueue());
    }

    public function testCancelledNestedWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_CancelledNestedWorkflow.log')->getQueue());
    }

    public function testCancelledMidflightWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_CancelledMidflightWorkflow.log')->getQueue());
    }

    public function testSendSignalBeforeCompletingWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_SendSignalBeforeCompletingWorkflow.log')->getQueue());
    }

    public function testActivityStubWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ActivityStubWorkflow.log')->getQueue());
    }

    public function testBinaryPayload(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_BinaryPayload.log')->getQueue());
    }

    public function testContinueAsNew(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ContinueAsNew.log')->getQueue());
    }

    public function testEmptyWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_EmptyWorkflow.log')->getQueue());
    }

    public function testSideEffectWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_SideEffect.log')->getQueue());
    }

    public function testExecuteWorkflowWithParallelScopes(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteWorkflowWithParallelScopes.log')->getQueue());
    }

    public function testActivity(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_Activity.log')->getQueue());
    }

    public function testExecuteProtoWorkflow(): void
    {
        if (\extension_loaded('protobuf')) {
            $this->markTestSkipped('The test conflicts with "protobuf" extension.');
        }

        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteProtoWorkflow.log')->getQueue());
    }

    public function testExecuteSimpleDTOWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteSimpleDTOWorkflow.log')->getQueue());
    }

    public function testExecuteSimpleWorkflowWithSequenceInBatch(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteSimpleWorkflowWithSequenceInBatch.log')->getQueue());
    }

    public function testPromiseChaining(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_PromiseChaining.log')->getQueue());
    }

    public function testMultipleWorkflowsInSingleWorker(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_MultipleWorkflowsInSingleWorker.log')->getQueue());
    }

    public function testSignalChildViaStubWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_SignalChildViaStubWorkflow.log')->getQueue());
    }

    public function testExecuteChildStubWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteChildStubWorkflow.log')->getQueue());
    }

    public function testExecuteChildStubWorkflow_02(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteChildStubWorkflow_02.log')->getQueue());
    }

    public function testExecuteChildWorkflow(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteChildWorkflow.log')->getQueue());
    }

    /**
     * Namespace must be inherited by default
     */
    public function testExecuteChildWorkflowNamespaced(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteChildWorkflowNamespaced.log')->getQueue());
    }

    /**
     * Namespace must be inherited by default
     */
    public function testExecuteChildWorkflowTaskQueueInheritance(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_ExecuteChildWorkflowTaskQueue.log')->getQueue());
    }

    public function testRuntimeSignal(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_RuntimeSignal.log')->getQueue());
    }

    public function testSignalStepsAndRuntimeQuery(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_SignalSteps.log')->getQueue());
    }

    public function testBatchedSignal_WithPauses(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_BatchedSignal.log')->getQueue());
    }

    public function testBatchedSignal_Combined(): void
    {
        $worker = WorkerMock::createMock();

        $worker->run($this, Splitter::create('Test_BatchedSignal_01.log')->getQueue());
    }

    /**
     * Destroy workflow with a started awaitWithTimeout promise inside.
     * @see \Temporal\Tests\Workflow\AwaitWithTimeoutWorkflow
     */
    public function testAwaitWithTimeout(): void
    {
        $worker = WorkerMock::createMock();

        $id = 9001;
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();
        $log = <<<LOG
            2021/01/12 15:21:52	[97mDEBUG[0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"AwaitWithTimeoutWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"4301710877bf4b107429ee12de0922be"}},"payloads":"CicKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SDSJIZWxsbyBXb3JsZCI="}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:52.2672785Z"}
            2021/01/12 15:21:52	[97mDEBUG[0m	[{"id":$id,"command":"NewTimer","options":{"ms":999000},"payloads":"","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            2021/01/12 15:21:53	[97mDEBUG[0m	[{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:53.3838443Z","replay":true}
            2021/01/12 15:21:53	[97mDEBUG[0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            LOG;

        $worker->run($this, Splitter::createFromString($log)->getQueue());
    }

    /**
     * Destroy 100 workflows with a started awaitWithTimeout promise inside.
     * The promise will be annihilated on the workflow destroy.
     * There mustn't be any leaks.
     * @see \Temporal\Tests\Workflow\AwaitWithTimeoutWorkflow
     */
    public function testAwaitWithTimeout_Leaks(): void
    {
        $worker = WorkerMock::createMock();

        // Run the workflow $i times
        for ($id = 9001, $i = 0; $i < 100; ++$i, ++$id) {
            $uuid1 = Uuid::v4();
            $uuid2 = Uuid::v4();
            $log = <<<LOG
                [0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"AwaitWithTimeoutWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"4301710877bf4b107429ee12de0922be"}},"payloads":"CicKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SDSJIZWxsbyBXb3JsZCI="}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:52.2672785Z"}
                # Run a timers
                [0m	[{"id":$id,"command":"NewTimer","options":{"ms":999000},"payloads":"","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                # Destroy workflow
                [0m	[{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:53.3838443Z","replay":true}
                [0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                LOG;

            $worker->run($this, Splitter::createFromString($log)->getQueue());
            $i === 3 and $before = \memory_get_usage();
        }
        $after = \memory_get_usage();

        $this->assertSame(0, $after - $before);
    }

    /**
     * Destroy 100 workflows with started few awaitWithTimeout promises inside.
     * The promises will be annihilated on the workflow destroy.
     * There mustn't be any leaks.
     * @see \Temporal\Tests\Workflow\AwaitWithTimeoutWorkflow
     */
    public function testAwaitWithFewParallelTimeouts_Leaks(): void
    {
        $worker = WorkerMock::createMock();

        // Run the workflow $i times
        for ($id = 9000, $i = 0; $i < 100; ++$i) {
            $uuid1 = Uuid::v4();
            $uuid2 = Uuid::v4();
            $id1 = ++$id;
            $id2 = ++$id;
            $id3 = ++$id;
            $id4 = ++$id;
            $log = <<<LOG
                [0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"AwaitWithTimeoutWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"4301710877bf4b107429ee12de0922be"}},"payloads":"CicKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SDSJIZWxsbyBXb3JsZCI="}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:52.2672785Z"}
                [0m	[{"id":$id1,"command":"NewTimer","options":{"ms":999000},"payloads":"","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                [0m	[{"id":$id1}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:53.3204026Z"}
                # Run three async timers
                [0m	[{"id":{$id2},"command":"NewTimer","options":{"ms":500000},"payloads":"","header":""},{"id":$id3,"command":"NewTimer","options":{"ms":120000},"payloads":"","header":""},{"id":$id4,"command":"NewTimer","options":{"ms":20000},"payloads":"","header":""}]	{"receive": true}
                # Destroy workflow
                [0m	[{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:53.3838443Z","replay":true}
                [0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                LOG;

            $worker->run($this, Splitter::createFromString($log)->getQueue());
            $i === 3 and $before = \memory_get_usage();
        }
        $after = \memory_get_usage();

        $this->assertSame(0, $after - $before);
    }

    /**
     * Destroy 100 workflows with single promise inside Workflow::await.
     * That case mustn't leak.
     * @see \Temporal\Tests\Workflow\AwaitWithSingleTimeoutWorkflow
     */
    public function testAwaitWithOneTimer_Leaks(): void
    {
        $worker = WorkerMock::createMock();

        // Run the workflow $i times
        for ($id = 9000, $i = 0; $i < 100; ++$i) {
            $uuid1 = Uuid::v4();
            $uuid2 = Uuid::v4();
            $id1 = ++$id;
            $log = <<<LOG
                [0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"AwaitWithSingleTimeoutWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"4301710877bf4b107429ee12de0922be"}},"payloads":"CicKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SDSJIZWxsbyBXb3JsZCI="}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:52.2672785Z"}
                # Run a timer
                [0m	[{"id":$id1,"command":"NewTimer","options":{"ms":5000000},"payloads":"","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                # Destroy workflow
                [0m	[{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:53.3838443Z","replay":true}
                [0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                LOG;

            $worker->run($this, Splitter::createFromString($log)->getQueue());
            $i === 3 and $before = \memory_get_usage();
        }
        $after = \memory_get_usage();

        $this->assertSame(0, $after - $before);
    }

    public function testDetachedScopeRequests_Leaks(): void
    {
        $worker = WorkerMock::createMock();

        // Run the workflow $i times
        for ($id = 9000, $i = 0; $i < 20; ++$i) {
            $uuid1 = Uuid::v4();
            $uuid2 = Uuid::v4();
            $id1 = ++$id;
            $id2 = ++$id;
            $log = <<<LOG
                [0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"DetachedScopeWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"4301710877bf4b107429ee12de0922be"}},"payloads":"CicKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SDSJIZWxsbyBXb3JsZCI="}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:52.2672785Z"}
                # Run a timer
                [0m	[{"id":$id1,"command":"NewTimer","options":{"ms":5000000},"payloads":"","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"},{"id":$id2,"command":"CompleteWorkflow","options":{},"payloads":"Ch4KFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SBCJvayI=","header":""}]	{"receive": true}
                # Destroy workflow
                [0m	[{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:21:53.3838443Z","replay":true}
                [0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
                LOG;

            $worker->run($this, Splitter::createFromString($log)->getQueue());
        }

        // Check there are no hanging requests
        $factory = self::getPrivate($worker, 'factory');
        $client = self::getPrivate($factory, 'client');
        $requests = self::getPrivate($client, 'requests');
        self::assertCount(0, $requests);
    }

    /**
     * Test case when an external Temporal SDK returns empty payload that doesn't contain even NULL value.
     *
     * In this case {@see \Temporal\DataConverter\EncodedValues::getValue()} should return {@see null}
     * if there is no non-nullable expected type.
     */
    public function testEmptyPayload(): void
    {
        $worker = WorkerMock::createMock();

        $id1 = 9001;
        $id2 = 9002;
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();
        $emptyPayload = (new Payloads());
        $emptyPayloadStr = \base64_encode($emptyPayload->serializeToString());

        $log = <<<LOG
            2021/01/12 15:25:13	[97mDEBUG[0m	[{"command":"StartWorkflow","options":{"info":{"WorkflowExecution":{"ID":"$uuid1","RunID":"$uuid2"},"WorkflowType":{"Name":"VoidActivityStubWorkflow"},"TaskQueueName":"default","WorkflowExecutionTimeout":315360000000000000,"WorkflowRunTimeout":315360000000000000,"WorkflowTaskTimeout":0,"Namespace":"default","Attempt":1,"CronSchedule":"","ContinuedExecutionRunID":"","ParentWorkflowNamespace":"","ParentWorkflowExecution":null,"Memo":null,"SearchAttributes":null,"BinaryChecksum":"8646d54f9f6b22f407d6d22254eea9f5"}},"payloads":"$emptyPayloadStr"}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.3983204Z"}
            2021/01/12 15:25:13	[97mDEBUG[0m	[{"id":$id1,"command":"ExecuteActivity","options":{"name":"SimpleActivity.empty","options":{"TaskQueueName":null,"ScheduleToCloseTimeout":0,"ScheduleToStartTimeout":0,"StartToCloseTimeout":5000000000,"HeartbeatTimeout":0,"WaitForCancellation":false,"ActivityID":"","RetryPolicy":null}},"payloads":"$emptyPayloadStr","header":""},{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            2021/01/12 15:25:13	[97mDEBUG[0m	[{"id":$id1,"payloads":"$emptyPayloadStr"}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.4849445Z"}
            2021/01/12 15:25:13	[97mDEBUG[0m	[{"id":$id2,"command":"CompleteWorkflow","options":{},"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs","header":""}]	{"receive": true}
            2021/01/12 15:25:13	[97mDEBUG[0m	[{"id":$id2,"payloads":"CiUKFgoIZW5jb2RpbmcSCmpzb24vcGxhaW4SCyJjb21wbGV0ZWQi"},{"command":"DestroyWorkflow","options":{"runId":"$uuid2"}}] {"taskQueue":"default","tickTime":"2021-01-12T15:25:13.5143426Z","replay":true}
            2021/01/12 15:25:13	[97mDEBUG[0m	[{"payloads":"ChkKFwoIZW5jb2RpbmcSC2JpbmFyeS9udWxs"}]	{"receive": true}
            LOG;

        $worker->run($this, Splitter::createFromString($log)->getQueue());
    }

    public function setUp(): void
    {
        parent::setUp();

        // emulate connection to parent server
        $_SERVER['RR_RPC'] = 'tcp://127.0.0.1:6001';
    }

    /**
     * Fetch a private property from an object.
     *
     * @param non-empty-string $key Property name
     */
    private static function getPrivate(object $object, string $key): mixed
    {
        return (fn (object $value) => $value->{$key} ?? null)->call($object, $object);
    }
}
