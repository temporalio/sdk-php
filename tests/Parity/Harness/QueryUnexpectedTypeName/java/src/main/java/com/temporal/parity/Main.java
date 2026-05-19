package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowQueryException;
import io.temporal.client.WorkflowStub;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface QueryUnexpectedTypeNameWorkflow {
        @WorkflowMethod(name = "Parity_Harness_QueryUnexpectedTypeName")
        String run();

        @SignalMethod(name = "finish")
        void finish();
    }

    public static final class QueryUnexpectedTypeNameWorkflowImpl implements QueryUnexpectedTypeNameWorkflow {
        private boolean done = false;

        @Override
        public String run() {
            Workflow.await(() -> done);
            return "finished";
        }

        @Override
        public void finish() {
            done = true;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-query-unexpected-type-name",
            "parity-query-unexpected-type-name",
            "TemporalTestsParityHarnessQueryUnexpectedTypeNamePhp",
            QueryUnexpectedTypeNameWorkflow.class,
            new Class<?>[]{QueryUnexpectedTypeNameWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(200);

                WorkflowStub untyped = WorkflowStub.fromTyped(stub);
                boolean caught = false;
                try {
                    untyped.query("nonexistent", String.class);
                } catch (WorkflowQueryException e) {
                    caught = true;
                }
                if (!caught) {
                    System.err.println("[parity-java] expected WorkflowQueryException for unknown query name");
                    Runtime.getRuntime().halt(2);
                }

                stub.finish();
                return untyped.getResult(String.class);
            },
            "finished");
    }

    private Main() {}
}
