package com.temporal.parity;

import com.temporal.parity.runner.ParityRunner;
import io.temporal.activity.ActivityInterface;
import io.temporal.activity.ActivityMethod;
import io.temporal.activity.ActivityOptions;
import io.temporal.workflow.Workflow;
import io.temporal.workflow.WorkflowInterface;
import io.temporal.workflow.WorkflowMethod;

import java.time.Duration;
import java.util.List;

public final class Main {

    @ActivityInterface
    public interface BagActivity {
        @ActivityMethod(name = "bag")
        List<String> bag();
    }

    public static final class BagActivityImpl implements BagActivity {
        @Override
        public List<String> bag() {
            return List.of("alpha", "beta", "gamma");
        }
    }

    @WorkflowInterface
    public interface ComplexActivityResultWorkflow {
        @WorkflowMethod(name = "Parity_Basic_ComplexActivityResult")
        String run();
    }

    public static final class ComplexActivityResultWorkflowImpl implements ComplexActivityResultWorkflow {
        @Override
        public String run() {
            BagActivity stub = Workflow.newActivityStub(
                BagActivity.class,
                ActivityOptions.newBuilder()
                    .setStartToCloseTimeout(Duration.ofSeconds(2))
                    .build());
            List<String> items = stub.bag();
            return String.join(":", items);
        }
    }

    public static void main(String[] args) throws Exception {
        ParityRunner.run(args, "basic-complex-activity-result", "parity-complex-activity-result", "TemporalTestsParityBasicComplexActivityResultPhp",
            ComplexActivityResultWorkflow.class,
            new Class<?>[]{ComplexActivityResultWorkflowImpl.class},
            new Object[]{new BagActivityImpl()},
            (client, stub) -> stub.run(),
            "alpha:beta:gamma");
    }

    private Main() {}
}
