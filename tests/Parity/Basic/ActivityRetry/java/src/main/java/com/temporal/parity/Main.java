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
    public interface FlakyActivity {
        @ActivityMethod(name = "fail")
        String fail();
    }

    public static final class FlakyActivityImpl implements FlakyActivity {
        @Override
        public String fail() {
            throw new RuntimeException("always-fails");
        }
    }

    @WorkflowInterface
    public interface ActivityRetryWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ActivityRetry")
        String run();
    }

    public static final class ActivityRetryWorkflowImpl implements ActivityRetryWorkflow {
        @Override
        public String run() {
            FlakyActivity stub = Workflow.newActivityStub(
                FlakyActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(2))
                    .setRetryOptions(
                        RetryOptions.newBuilder()
                            .setInitialInterval(Duration.ofMillis(10))
                            .setBackoffCoefficient(1.0)
                            .setMaximumAttempts(3)
                            .build())
                    .build());
            try {
                stub.fail();
                return "unexpected:no-failure";
            } catch (ActivityFailure e) {
                return "caught";
            }
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "activityretry", "parity-activityretry", "parity-activityretry-tq",
            ActivityRetryWorkflow.class,
            new Class<?>[]{ActivityRetryWorkflowImpl.class},
            new Object[]{new FlakyActivityImpl()},
            (client, stub) -> stub.run(),
            "caught");
    }

    private Main() {}
}
