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
        String say(String word);
    }

    public static final class EchoActivityImpl implements EchoActivity {
        @Override
        public String say(String word) {
            return "echoed:" + word;
        }
    }

    @WorkflowInterface
    public interface MultipleActivitiesWorkflow {
        @WorkflowMethod(name = "Parity_Basic_MultipleActivities")
        String run();
    }

    public static final class MultipleActivitiesWorkflowImpl implements MultipleActivitiesWorkflow {
        @Override
        public String run() {
            EchoActivity stub = Workflow.newActivityStub(
                EchoActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(5))
                    .build());
            return stub.say("one") + "|" + stub.say("two") + "|" + stub.say("three");
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "multipleactivities", "parity-multipleactivities", "parity-multipleactivities-tq",
            MultipleActivitiesWorkflow.class,
            new Class<?>[]{MultipleActivitiesWorkflowImpl.class},
            new Object[]{new EchoActivityImpl()},
            (client, stub) -> stub.run(),
            "echoed:one|echoed:two|echoed:three");
    }

    private Main() {}
}
