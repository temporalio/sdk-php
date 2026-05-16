package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowStub;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @WorkflowInterface
    public interface AwaitWithTimeoutWorkflow {
        @WorkflowMethod(name = "Parity_Basic_AwaitWithTimeout")
        String run();

        @SignalMethod(name = "release")
        void release();
    }

    public static final class AwaitWithTimeoutWorkflowImpl implements AwaitWithTimeoutWorkflow {
        private boolean released = false;

        @Override
        public String run() {
            boolean got = Workflow.await(Duration.ofSeconds(5), () -> released);
            return got ? "got" : "timed-out";
        }

        @Override
        public void release() {
            released = true;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-await-with-timeout", "parity-await-with-timeout", "TemporalTestsParityBasicAwaitWithTimeoutPhp",
            AwaitWithTimeoutWorkflow.class,
            new Class<?>[]{AwaitWithTimeoutWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(200);
                stub.release();
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "got");
    }

    private Main() {}
}
