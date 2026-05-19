package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.ActivityOptions;
import io.temporal.workflow.Async;
import io.temporal.workflow.Promise;
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
    public interface ConcurrentActivitiesWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ConcurrentActivities")
        String run();
    }

    public static final class ConcurrentActivitiesWorkflowImpl implements ConcurrentActivitiesWorkflow {
        @Override
        public String run() {
            EchoActivity stub = Workflow.newActivityStub(
                EchoActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(5))
                    .build());

            Promise<String> a = Async.function(stub::say, "a");
            Promise<String> b = Async.function(stub::say, "b");
            Promise<String> c = Async.function(stub::say, "c");

            return a.get() + "|" + b.get() + "|" + c.get();
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "concurrentactivities", "parity-concurrentactivities", "parity-concurrentactivities-tq",
            ConcurrentActivitiesWorkflow.class,
            new Class<?>[]{ConcurrentActivitiesWorkflowImpl.class},
            new Object[]{new EchoActivityImpl()},
            (client, stub) -> stub.run(),
            "echoed:a|echoed:b|echoed:c");
    }

    private Main() {}
}
