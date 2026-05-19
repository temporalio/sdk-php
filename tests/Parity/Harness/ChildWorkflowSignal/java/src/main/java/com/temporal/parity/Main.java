package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.Async;
import io.temporal.workflow.Promise;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface ChildWorkflow {
        @WorkflowMethod(name = "Parity_Harness_ChildWorkflowSignal_Child")
        String run();

        @SignalMethod(name = "unblock-signal")
        void unblock(String message);
    }

    public static final class ChildWorkflowImpl implements ChildWorkflow {
        private String message;

        @Override
        public String run() {
            Workflow.await(() -> message != null);
            return message;
        }

        @Override
        public void unblock(String message) {
            this.message = message;
        }
    }

    @WorkflowInterface
    public interface ParentWorkflow {
        @WorkflowMethod(name = "Parity_Harness_ChildWorkflowSignal_Parent")
        String run();
    }

    public static final class ParentWorkflowImpl implements ParentWorkflow {
        @Override
        public String run() {
            ChildWorkflow child = Workflow.newChildWorkflowStub(ChildWorkflow.class);
            Promise<String> childResult = Async.function(child::run);
            child.unblock("unblock");
            return childResult.get();
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-child-workflow-signal",
            "parity-child-workflow-signal",
            "TemporalTestsParityHarnessChildWorkflowSignalPhp",
            ParentWorkflow.class,
            new Class<?>[]{ParentWorkflowImpl.class, ChildWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "unblock");
    }

    private Main() {}
}
