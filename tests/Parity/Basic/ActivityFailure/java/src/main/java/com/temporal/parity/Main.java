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
    public interface BoomActivity {
        @ActivityMethod(name = "boom")
        String boom();
    }

    public static final class BoomActivityImpl implements BoomActivity {
        @Override
        public String boom() {
            throw new RuntimeException("boom");
        }
    }

    @WorkflowInterface
    public interface ActivityFailureWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ActivityFailure")
        String run();
    }

    public static final class ActivityFailureWorkflowImpl implements ActivityFailureWorkflow {
        @Override
        public String run() {
            BoomActivity stub = Workflow.newActivityStub(
                BoomActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(2))
                    .setRetryOptions(
                        RetryOptions.newBuilder()
                            .setInitialInterval(Duration.ofMillis(10))
                            .setBackoffCoefficient(1.0)
                            .setMaximumAttempts(1)
                            .build())
                    .build());
            try {
                stub.boom();
                return "unexpected:no-failure";
            } catch (ActivityFailure e) {
                return "caught";
            }
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-activity-failure", "parity-activity-failure", "TemporalTestsParityBasicActivityFailurePhp",
            ActivityFailureWorkflow.class,
            new Class<?>[]{ActivityFailureWorkflowImpl.class},
            new Object[]{new BoomActivityImpl()},
            (client, stub) -> stub.run(),
            "caught");
    }

    private Main() {}
}
