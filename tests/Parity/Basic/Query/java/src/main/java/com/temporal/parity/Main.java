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
    public interface QueryWorkflow {
        @WorkflowMethod(name = "Parity_Basic_Query")
        String run();

        @QueryMethod(name = "get_counter")
        int getCounter();

        @SignalMethod(name = "bump")
        void bump();

        @SignalMethod(name = "finish")
        void finish();
    }

    public static final class QueryWorkflowImpl implements QueryWorkflow {
        private int counter = 0;
        private boolean done = false;

        @Override
        public String run() {
            Workflow.await(() -> done);
            return "final:" + counter;
        }

        @Override
        public int getCounter() {
            return counter;
        }

        @Override
        public void bump() {
            counter++;
        }

        @Override
        public void finish() {
            done = true;
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-query", "parity-query", "TemporalTestsParityBasicQueryPhp",
            QueryWorkflow.class,
            new Class<?>[]{QueryWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> {
                WorkflowClient.start(stub::run);
                Thread.sleep(150);
                stub.bump();
                Thread.sleep(150);
                stub.bump();
                Thread.sleep(150);
                int counter = stub.getCounter();
                if (counter != 2) {
                    System.err.println("[parity-java] unexpected counter: " + counter);
                    Runtime.getRuntime().halt(2);
                }
                stub.finish();
                return WorkflowStub.fromTyped(stub).getResult(String.class);
            },
            "final:2");
    }

    private Main() {}
}
