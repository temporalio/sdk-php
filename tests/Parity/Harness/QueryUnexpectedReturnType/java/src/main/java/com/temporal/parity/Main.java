package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowStub;
import io.temporal.common.converter.DataConverterException;
import io.temporal.workflow.QueryMethod;
import io.temporal.workflow.SignalMethod;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface QueryUnexpectedReturnTypeWorkflow {
        @WorkflowMethod(name = "Parity_Harness_QueryUnexpectedReturnType")
        String run();

        @QueryMethod(name = "the_query")
        String theQuery();

        @SignalMethod(name = "finish")
        void finish();
    }

    public static final class QueryUnexpectedReturnTypeWorkflowImpl implements QueryUnexpectedReturnTypeWorkflow {
        private boolean done = false;

        @Override
        public String run() {
            Workflow.await(() -> done);
            return "finished";
        }

        @Override
        public String theQuery() {
            return "hi bob";
        }

        @Override
        public void finish() {
            done = true;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-query-unexpected-return-type",
            "parity-query-unexpected-return-type",
            "TemporalTestsParityHarnessQueryUnexpectedReturnTypePhp",
            QueryUnexpectedReturnTypeWorkflow.class,
            new Class<?>[]{QueryUnexpectedReturnTypeWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(200);

                WorkflowStub untyped = WorkflowStub.fromTyped(stub);
                boolean caught = false;
                try {
                    untyped.query("the_query", Integer.class);
                } catch (DataConverterException e) {
                    caught = true;
                }
                if (!caught) {
                    System.err.println("[parity-java] expected DataConverterException for bad return type");
                    Runtime.getRuntime().halt(2);
                }

                stub.finish();
                return untyped.getResult(String.class);
            },
            "finished");
    }

    private Main() {}
}
