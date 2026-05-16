package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowStub;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.util.ArrayList;
import java.util.List;

public final class Main {

    @WorkflowInterface
    public interface MultipleSignalsWorkflow {
        @WorkflowMethod(name = "Parity_Basic_MultipleSignals")
        String run();

        @SignalMethod(name = "push")
        void push(String value);
    }

    public static final class MultipleSignalsWorkflowImpl implements MultipleSignalsWorkflow {
        private final List<String> buffer = new ArrayList<>();

        @Override
        public String run() {
            Workflow.await(() -> buffer.size() >= 3);
            return String.join("|", buffer);
        }

        @Override
        public void push(String value) {
            buffer.add(value);
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-multiple-signals", "parity-multiple-signals", "TemporalTestsParityBasicMultipleSignalsPhp",
            MultipleSignalsWorkflow.class,
            new Class<?>[]{MultipleSignalsWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(150);
                stub.push("a");
                Thread.sleep(150);
                stub.push("b");
                Thread.sleep(150);
                stub.push("c");
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "a|b|c");
    }

    private Main() {}
}
