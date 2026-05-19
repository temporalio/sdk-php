package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowStub;
import io.temporal.workflow.QueryMethod;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface QueryUnexpectedArgumentsWorkflow {
        @WorkflowMethod(name = "Parity_Harness_QueryUnexpectedArguments")
        String run();

        @QueryMethod(name = "the_query")
        String theQuery(int arg);

        @SignalMethod(name = "finish")
        void finish();
    }

    public static final class QueryUnexpectedArgumentsWorkflowImpl implements QueryUnexpectedArgumentsWorkflow {
        private boolean done = false;

        @Override
        public String run() {
            Workflow.await(() -> done);
            return "finished";
        }

        @Override
        public String theQuery(int arg) {
            return "got " + arg;
        }

        @Override
        public void finish() {
            done = true;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-query-unexpected-arguments",
            "parity-query-unexpected-arguments",
            "TemporalTestsParityHarnessQueryUnexpectedArgumentsPhp",
            QueryUnexpectedArgumentsWorkflow.class,
            new Class<?>[]{QueryUnexpectedArgumentsWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(200);
                String queryResult = stub.theQuery(42);
                if (!"got 42".equals(queryResult)) {
                    System.err.println("[parity-java] unexpected query result: " + queryResult);
                    Runtime.getRuntime().halt(2);
                }
                stub.finish();
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "finished");
    }

    private Main() {}
}
