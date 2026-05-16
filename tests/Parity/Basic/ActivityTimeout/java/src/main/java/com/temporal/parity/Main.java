package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.ActivityOptions;
import io.temporal.common.RetryOptions;
import io.temporal.failure.ActivityFailure;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @ActivityInterface
    public interface SlowActivity {
        @ActivityMethod(name = "sleepLong")
        String sleepLong();
    }

    public static final class SlowActivityImpl implements SlowActivity {
        @Override
        public String sleepLong() {
            try {
                Thread.sleep(5_000);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
            return "unreachable";
        }
    }

    @WorkflowInterface
    public interface ActivityTimeoutWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ActivityTimeout")
        String run();
    }

    public static final class ActivityTimeoutWorkflowImpl implements ActivityTimeoutWorkflow {
        @Override
        public String run() {
            SlowActivity stub = Workflow.newActivityStub(
                SlowActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofMillis(500))
                    .setRetryOptions(RetryOptions.newBuilder().setMaximumAttempts(1).build())
                    .build());
            try {
                stub.sleepLong();
                return "unexpected:no-timeout";
            } catch (ActivityFailure e) {
                return "timed-out";
            }
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "activitytimeout", "parity-activitytimeout", "parity-activitytimeout-tq",
            ActivityTimeoutWorkflow.class,
            new Class<?>[]{ActivityTimeoutWorkflowImpl.class},
            new Object[]{new SlowActivityImpl()},
            (client, stub) -> stub.run(),
            "timed-out");
    }

    private Main() {}
}
