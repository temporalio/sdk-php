package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowStub;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface WaitForSignalWorkflow {
        @WorkflowMethod(name = "Parity_Basic_Signal")
        String run();

        @SignalMethod(name = "release")
        void release();
    }

    public static final class WaitForSignalWorkflowImpl implements WaitForSignalWorkflow {
        private boolean signaled = false;

        @Override
        public String run() {
            Workflow.await(() -> signaled);
            return "signaled";
        }

        @Override
        public void release() {
            signaled = true;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "signal", "parity-signal", "parity-signal-tq",
            WaitForSignalWorkflow.class,
            new Class<?>[]{WaitForSignalWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(200);
                stub.release();
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "signaled");
    }

    private Main() {}
}
