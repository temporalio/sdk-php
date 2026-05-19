package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.LocalActivityOptions;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @ActivityInterface
    public interface EchoActivity {
        @ActivityMethod(name = "say")
        String say(String name);
    }

    public static final class EchoActivityImpl implements EchoActivity {
        @Override
        public String say(String name) {
            return "local-echoed:" + name;
        }
    }

    @WorkflowInterface
    public interface LocalActivityWorkflow {
        @WorkflowMethod(name = "Parity_Basic_LocalActivity")
        String run(String name);
    }

    public static final class LocalActivityWorkflowImpl implements LocalActivityWorkflow {
        @Override
        public String run(String name) {
            EchoActivity stub = Workflow.newLocalActivityStub(
                EchoActivity.class,
                LocalActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(5))
                    .build());
            return stub.say(name);
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "localactivity", "parity-localactivity", "parity-localactivity-tq",
            LocalActivityWorkflow.class,
            new Class<?>[]{LocalActivityWorkflowImpl.class},
            new Object[]{new EchoActivityImpl()},
            (client, stub) -> stub.run("world"),
            "local-echoed:world");
    }

    private Main() {}
}
