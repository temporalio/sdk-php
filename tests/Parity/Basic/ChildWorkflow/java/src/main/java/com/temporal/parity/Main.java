package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.ChildWorkflowOptions;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @WorkflowInterface
    public interface ChildWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ChildWorkflow_Child")
        String run(String name);
    }

    public static final class ChildWorkflowImpl implements ChildWorkflow {
        @Override
        public String run(String name) {
            return "child-said:" + name;
        }
    }

    @WorkflowInterface
    public interface ParentWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ChildWorkflow_Parent")
        String run();
    }

    public static final class ParentWorkflowImpl implements ParentWorkflow {
        @Override
        public String run() {
            ChildWorkflow stub = Workflow.newChildWorkflowStub(
                ChildWorkflow.class,
                ChildWorkflowOptions.newBuilder()
                    .setWorkflowExecutionTimeout(Duration.ofSeconds(10))
                    .build());
            return "parent-got:" + stub.run("hello");
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "childworkflow-parent", "parity-childworkflow", "parity-childworkflow-tq",
            ParentWorkflow.class,
            new Class<?>[]{ChildWorkflowImpl.class, ParentWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "parent-got:child-said:hello");
    }

    private Main() {}
}
