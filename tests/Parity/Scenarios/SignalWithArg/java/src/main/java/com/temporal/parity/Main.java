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
    public interface SignalWithArgWorkflow {
        @WorkflowMethod(name = "Parity_Basic_SignalWithArg")
        String run();

        @SignalMethod(name = "greet")
        void greet(String value);
    }

    public static final class SignalWithArgWorkflowImpl implements SignalWithArgWorkflow {
        private String payload = null;

        @Override
        public String run() {
            Workflow.await(() -> payload != null);
            return "hi:" + payload;
        }

        @Override
        public void greet(String value) {
            payload = value;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-signal-with-arg", "parity-signal-with-arg", "TemporalTestsParityBasicSignalWithArgPhp",
            SignalWithArgWorkflow.class,
            new Class<?>[]{SignalWithArgWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(200);
                stub.greet("world");
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "hi:world");
    }

    private Main() {}
}
