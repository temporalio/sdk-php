package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

public final class Main {

    @WorkflowInterface
    public interface HelloWorldWorkflow {
        @WorkflowMethod(name = "Parity_Basic_HelloWorld")
        String run(String name);
    }

    public static final class HelloWorldWorkflowImpl implements HelloWorldWorkflow {
        @Override
        public String run(String name) {
            return "hello, " + name + "!";
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "helloworld", "parity-helloworld", "parity-helloworld-task-queue",
            HelloWorldWorkflow.class,
            new Class<?>[]{HelloWorldWorkflowImpl.class},
            new Object[]{},
            (client, stub) -> stub.run("world"),
            "hello, world!");
    }

    private Main() {}
}
