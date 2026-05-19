package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface SideEffectWorkflow {
        @WorkflowMethod(name = "Parity_Basic_SideEffect")
        String run();
    }

    public static final class SideEffectWorkflowImpl implements SideEffectWorkflow {
        @Override
        public String run() {
            int value = Workflow.sideEffect(int.class, () -> 42);
            return "value:" + value;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "sideeffect", "parity-sideeffect", "parity-sideeffect-tq",
            SideEffectWorkflow.class,
            new Class<?>[]{SideEffectWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "value:42");
    }

    private Main() {}
}
