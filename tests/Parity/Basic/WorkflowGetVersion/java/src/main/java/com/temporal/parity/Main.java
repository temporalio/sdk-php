package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface GetVersionWorkflow {
        @WorkflowMethod(name = "Parity_Basic_WorkflowGetVersion")
        String run();
    }

    public static final class GetVersionWorkflowImpl implements GetVersionWorkflow {
        @Override
        public String run() {
            int version = Workflow.getVersion("change-a", 1, 2);
            return "v:" + version;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-workflow-get-version", "parity-workflow-get-version", "TemporalTestsParityBasicWorkflowGetVersionPhp",
            GetVersionWorkflow.class,
            new Class<?>[]{GetVersionWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run(),
            "v:2");
    }

    private Main() {}
}
