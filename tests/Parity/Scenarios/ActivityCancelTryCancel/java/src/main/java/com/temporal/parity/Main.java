package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.Activity;
import io.temporal.activity.ActivityCancellationType;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.ActivityOptions;
import io.temporal.client.ActivityCanceledException;
import io.temporal.common.RetryOptions;
import io.temporal.failure.ActivityFailure;
import io.temporal.failure.CanceledFailure;
import io.temporal.workflow.Async;
import io.temporal.workflow.CancellationScope;
import io.temporal.workflow.Promise;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;
import java.util.concurrent.atomic.AtomicReference;

public final class Main {

    @ActivityInterface
    public interface CancellableActivity {
        @ActivityMethod(name = "cancellable")
        void cancellable();
    }

    public static final class CancellableActivityImpl implements CancellableActivity {
        @Override
        public void cancellable() {
            for (int i = 0; i < 50; i++) {
                try {
                    Thread.sleep(100);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    return;
                }
                try {
                    Activity.getExecutionContext().heartbeat(i);
                } catch (ActivityCanceledException e) {
                    return;
                }
            }
        }
    }

    @WorkflowInterface
    public interface ActivityCancelTryCancelWorkflow {
        @WorkflowMethod(name = "Parity_Harness_ActivityCancelTryCancel")
        String run();
    }

    public static final class ActivityCancelTryCancelWorkflowImpl implements ActivityCancelTryCancelWorkflow {
        @Override
        public String run() {
            CancellableActivity activity = Workflow.newActivityStub(
                CancellableActivity.class,
                ActivityOptions.newBuilder()
                    .setScheduleToCloseTimeout(Duration.ofMinutes(1))
                    .setHeartbeatTimeout(Duration.ofSeconds(5))
                    .setRetryOptions(RetryOptions.newBuilder().setMaximumAttempts(1).build())
                    .setCancellationType(ActivityCancellationType.TRY_CANCEL)
                    .build());

            AtomicReference<Promise<Void>> activityPromise = new AtomicReference<>();
            CancellationScope scope = Workflow.newCancellationScope(() ->
                activityPromise.set(Async.procedure(activity::cancellable))
            );
            scope.run();

            Workflow.sleep(Duration.ofSeconds(1));

            scope.cancel();
            try {
                activityPromise.get().get();
                return "unexpected:not-cancelled";
            } catch (ActivityFailure e) {
                if (e.getCause() instanceof CanceledFailure) {
                    return "cancelled";
                }
                throw e;
            }
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-activity-cancel-try-cancel",
            "parity-activity-cancel-try-cancel",
            "TemporalTestsParityHarnessActivityCancelTryCancelPhp",
            ActivityCancelTryCancelWorkflow.class,
            new Class<?>[]{ActivityCancelTryCancelWorkflowImpl.class},
            new Object[]{new CancellableActivityImpl()},
            (client, stub) -> stub.run(),
            "cancelled");
    }

    private Main() {}
}
