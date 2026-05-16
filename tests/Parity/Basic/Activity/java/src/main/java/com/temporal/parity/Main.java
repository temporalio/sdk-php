package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.ActivityOptions;
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
            return "echoed:" + name;
        }
    }

    @WorkflowInterface
    public interface ActivityWorkflow {
        @WorkflowMethod(name = "Parity_Basic_Activity")
        String run(String name);
    }

    public static final class ActivityWorkflowImpl implements ActivityWorkflow {
        @Override
        public String run(String name) {
            EchoActivity stub = Workflow.newActivityStub(
                EchoActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(5))
                    .build());
            return stub.say(name);
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "activity", "parity-activity", "parity-activity-tq",
            ActivityWorkflow.class,
            new Class<?>[]{ActivityWorkflowImpl.class},
            new Object[]{new EchoActivityImpl()},
            (client, stub) -> stub.run("world"),
            "echoed:world");
    }

    private Main() {}
}
