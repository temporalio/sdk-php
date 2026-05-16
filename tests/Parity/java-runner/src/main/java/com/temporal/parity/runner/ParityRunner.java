package com.temporal.parity.runner;

import io.temporal.api.common.v1.WorkflowExecution;
import io.temporal.client.WorkflowClient;
import io.temporal.client.WorkflowClientOptions;
import io.temporal.client.WorkflowOptions;
import io.temporal.client.WorkflowStub;
import io.temporal.serviceclient.WorkflowServiceStubs;
import io.temporal.serviceclient.WorkflowServiceStubsOptions;
import io.temporal.worker.Worker;
import io.temporal.worker.WorkerFactory;

import java.time.Duration;
import java.util.Objects;
import java.util.UUID;

public final class ParityRunner {

    private static final Duration DEFAULT_TIMEOUT = Duration.ofSeconds(15);

    public static <T> void run(
        String[] args,
        String slug,
        String defaultNamespace,
        String defaultTaskQueue,
        Class<T> workflowClass,
        Class<?>[] workflowImpls,
        Object[] activities,
        Driver<T> driver,
        Object expected
    ) throws Exception {
        Setup<T> setup = setup(args, slug, defaultNamespace, defaultTaskQueue,
            workflowClass, workflowImpls, activities, DEFAULT_TIMEOUT);

        Object result = driver.apply(setup.client, setup.stub);
        assertExpected(expected, result);

        System.out.println("WORKFLOW_ID=" + setup.workflowId);
        System.out.flush();
        Runtime.getRuntime().halt(0);
    }

    public static <T, R> void runCapturingFirstRun(
        String[] args,
        String slug,
        String defaultNamespace,
        String defaultTaskQueue,
        Class<T> workflowClass,
        Class<?>[] workflowImpls,
        Object[] activities,
        Duration workflowTimeout,
        AsyncStarter<T> starter,
        Class<R> resultClass,
        Object expected
    ) throws Exception {
        Setup<T> setup = setup(args, slug, defaultNamespace, defaultTaskQueue,
            workflowClass, workflowImpls, activities,
            workflowTimeout != null ? workflowTimeout : DEFAULT_TIMEOUT);

        WorkflowExecution execution = starter.start(setup.client, setup.stub);
        System.out.println("WORKFLOW_ID=" + setup.workflowId);
        System.out.println("RUN_ID=" + execution.getRunId());
        System.out.flush();

        R result = WorkflowStub.fromTyped(setup.stub).getResult(resultClass);
        assertExpected(expected, result);

        Runtime.getRuntime().halt(0);
    }

    private static <T> Setup<T> setup(
        String[] args,
        String slug,
        String defaultNamespace,
        String defaultTaskQueue,
        Class<T> workflowClass,
        Class<?>[] workflowImpls,
        Object[] activities,
        Duration workflowTimeout
    ) {
        ParityArgs parsed = ParityArgs.parse(args, defaultNamespace, defaultTaskQueue);

        WorkflowServiceStubs serviceStubs = WorkflowServiceStubs.newServiceStubs(
            WorkflowServiceStubsOptions.newBuilder().setTarget(parsed.address).build()
        );
        WorkflowClient client = WorkflowClient.newInstance(
            serviceStubs,
            WorkflowClientOptions.newBuilder().setNamespace(parsed.namespace).build()
        );

        WorkerFactory factory = WorkerFactory.newInstance(client);
        Worker worker = factory.newWorker(parsed.taskQueue);
        worker.registerWorkflowImplementationTypes(workflowImpls);
        if (activities != null && activities.length > 0) {
            worker.registerActivitiesImplementations(activities);
        }
        factory.start();

        String workflowId = "parity-" + slug + "-java-" + UUID.randomUUID();
        T stub = client.newWorkflowStub(
            workflowClass,
            WorkflowOptions.newBuilder()
                .setWorkflowId(workflowId)
                .setTaskQueue(parsed.taskQueue)
                .setWorkflowExecutionTimeout(workflowTimeout)
                .build()
        );

        return new Setup<>(client, stub, workflowId);
    }

    private static void assertExpected(Object expected, Object result) {
        if (!Objects.equals(expected, result)) {
            System.err.println("[parity-java] unexpected: " + result);
            System.err.flush();
            Runtime.getRuntime().halt(2);
        }
    }

    private static final class Setup<T> {
        final WorkflowClient client;
        final T stub;
        final String workflowId;

        Setup(WorkflowClient client, T stub, String workflowId) {
            this.client = client;
            this.stub = stub;
            this.workflowId = workflowId;
        }
    }

    private ParityRunner() {}
}
