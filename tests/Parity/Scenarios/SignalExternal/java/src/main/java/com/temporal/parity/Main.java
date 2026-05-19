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
    public interface SignalExternalWorkflow {
        @WorkflowMethod(name = "Parity_Harness_SignalExternal")
        String run();

        @SignalMethod(name = "external_signal")
        void externalSignal(String value);
    }

    public static final class SignalExternalWorkflowImpl implements SignalExternalWorkflow {
        private String result;

        @Override
        public String run() {
            Workflow.await(() -> result != null);
            return result;
        }

        @Override
        public void externalSignal(String value) {
            this.result = value;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-signal-external",
            "parity-signal-external",
            "TemporalTestsParityHarnessSignalExternalPhp",
            SignalExternalWorkflow.class,
            new Class<?>[]{SignalExternalWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                stub.externalSignal("Signaled!");
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "Signaled!");
    }

    private Main() {}
}
