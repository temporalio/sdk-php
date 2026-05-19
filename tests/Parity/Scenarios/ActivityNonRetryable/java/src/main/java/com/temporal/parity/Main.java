package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.ActivityOptions;
import io.temporal.common.RetryOptions;
import io.temporal.failure.ActivityFailure;
import io.temporal.failure.ApplicationFailure;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;

public final class Main {

    @ActivityInterface
    public interface FatalActivity {
        @ActivityMethod(name = "fatal")
        String fatal();
    }

    public static final class FatalActivityImpl implements FatalActivity {
        @Override
        public String fatal() {
            throw ApplicationFailure.newNonRetryableFailure("do-not-retry", "FatalAppError");
        }
    }

    @WorkflowInterface
    public interface ActivityNonRetryableWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ActivityNonRetryable")
        String run();
    }

    public static final class ActivityNonRetryableWorkflowImpl implements ActivityNonRetryableWorkflow {
        @Override
        public String run() {
            FatalActivity stub = Workflow.newActivityStub(
                FatalActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(2))
                    .setRetryOptions(
                        RetryOptions.newBuilder()
                            .setInitialInterval(Duration.ofMillis(10))
                            .setBackoffCoefficient(1.0)
                            .setMaximumAttempts(5)
                            .build())
                    .build());
            try {
                stub.fatal();
                return "unexpected:no-failure";
            } catch (ActivityFailure e) {
                return "non-retryable";
            }
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-activity-non-retryable", "parity-activity-non-retryable", "TemporalTestsParityBasicActivityNonRetryablePhp",
            ActivityNonRetryableWorkflow.class,
            new Class<?>[]{ActivityNonRetryableWorkflowImpl.class},
            new Object[]{new FatalActivityImpl()},
            (client, stub) -> stub.run(),
            "non-retryable");
    }

    private Main() {}
}
