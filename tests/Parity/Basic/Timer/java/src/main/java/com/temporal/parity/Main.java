package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @WorkflowInterface
    public interface TimerWorkflow {
        @WorkflowMethod(name = "Parity_Basic_Timer")
        String run();
    }

    public static final class TimerWorkflowImpl implements TimerWorkflow {
        @Override
        public String run() {
            Workflow.sleep(Duration.ofSeconds(1));
            return "done";
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "timer", "parity-timer", "parity-timer-task-queue",
            TimerWorkflow.class,
            new Class<?>[]{TimerWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "done");
    }

    private Main() {}
}
