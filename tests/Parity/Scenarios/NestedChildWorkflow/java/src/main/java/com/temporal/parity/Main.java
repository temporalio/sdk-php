package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.ChildWorkflowOptions;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @WorkflowInterface
    public interface GrandChildWorkflow {
        @WorkflowMethod(name = "Parity_Basic_NestedChildWorkflow_GrandChild")
        String run(String name);
    }

    public static final class GrandChildWorkflowImpl implements GrandChildWorkflow {
        @Override
        public String run(String name) {
            return "g:" + name;
        }
    }

    @WorkflowInterface
    public interface ChildWorkflow {
        @WorkflowMethod(name = "Parity_Basic_NestedChildWorkflow_Child")
        String run(String name);
    }

    public static final class ChildWorkflowImpl implements ChildWorkflow {
        @Override
        public String run(String name) {
            GrandChildWorkflow stub = Workflow.newChildWorkflowStub(
                GrandChildWorkflow.class,
                ChildWorkflowOptions.newBuilder()
                    .setWorkflowExecutionTimeout(Duration.ofSeconds(10))
                    .build());
            return "c:" + stub.run(name);
        }
    }

    @WorkflowInterface
    public interface ParentWorkflow {
        @WorkflowMethod(name = "Parity_Basic_NestedChildWorkflow")
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
            return "p:" + stub.run("hi");
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-nested-child-workflow", "parity-nested-child-workflow", "TemporalTestsParityBasicNestedChildWorkflowPhp",
            ParentWorkflow.class,
            new Class<?>[]{ParentWorkflowImpl.class, ChildWorkflowImpl.class, GrandChildWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "p:c:g:hi");
    }

    private Main() {}
}
