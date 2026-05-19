package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @WorkflowInterface
    public interface CounterWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ContinueAsNew")
        String run(int i);
    }

    public static final class CounterWorkflowImpl implements CounterWorkflow {
        @Override
        public String run(int i) {
            if (i < 2) {
                CounterWorkflow next = Workflow.newContinueAsNewStub(CounterWorkflow.class);
                next.run(i + 1);
            }
            return "done:" + i;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.runCapturingFirstRun(args, "continueasnew", "parity-continueasnew", "parity-continueasnew-tq",
            CounterWorkflow.class,
            new Class<?>[]{CounterWorkflowImpl.class},
            new Object[]{},
            Duration.ofSeconds(30),
            (client, stub) -> WorkflowClient.start(stub::run, 0),
            String.class,
            "done:2");
    }

    private Main() {}
}
