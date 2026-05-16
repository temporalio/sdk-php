package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @WorkflowInterface
    public interface MultipleTimersWorkflow {
        @WorkflowMethod(name = "Parity_Basic_MultipleTimers")
        String run();
    }

    public static final class MultipleTimersWorkflowImpl implements MultipleTimersWorkflow {
        @Override
        public String run() {
            Workflow.sleep(Duration.ofMillis(50));
            Workflow.sleep(Duration.ofMillis(50));
            Workflow.sleep(Duration.ofMillis(50));
            return "tick-tick-tick";
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-multiple-timers", "parity-multiple-timers", "TemporalTestsParityBasicMultipleTimersPhp",
            MultipleTimersWorkflow.class,
            new Class<?>[]{MultipleTimersWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "tick-tick-tick");
    }

    private Main() {}
}
