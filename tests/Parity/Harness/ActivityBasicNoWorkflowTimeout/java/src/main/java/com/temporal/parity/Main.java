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
        @ActivityMethod(name = "echo")
        String echo();
    }

    public static final class EchoActivityImpl implements EchoActivity {
        @Override
        public String echo() {
            return "echo";
        }
    }

    @WorkflowInterface
    public interface ActivityBasicNoWorkflowTimeoutWorkflow {
        @WorkflowMethod(name = "Parity_Harness_ActivityBasicNoWorkflowTimeout")
        String run();
    }

    public static final class ActivityBasicNoWorkflowTimeoutWorkflowImpl implements ActivityBasicNoWorkflowTimeoutWorkflow {
        @Override
        public String run() {
            EchoActivity first = Workflow.newActivityStub(
                EchoActivity.class,
                ActivityOptions.newBuilder()
                    .setScheduleToCloseTimeout(Duration.ofMinutes(1))
                    .build());
            first.echo();

            EchoActivity second = Workflow.newActivityStub(
                EchoActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofMinutes(1))
                    .build());
            return second.echo();
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "harness-activity-basic-no-workflow-timeout",
            "parity-activity-basic-no-workflow-timeout",
            "TemporalTestsParityHarnessActivityBasicNoWorkflowTimeoutPhp",
            ActivityBasicNoWorkflowTimeoutWorkflow.class,
            new Class<?>[]{ActivityBasicNoWorkflowTimeoutWorkflowImpl.class},
            new Object[]{new EchoActivityImpl()},
            (client, stub) -> stub.run(),
            "echo");
    }

    private Main() {}
}
