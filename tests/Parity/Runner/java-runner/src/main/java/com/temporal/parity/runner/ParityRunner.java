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
import java.util.Arrays;
import java.util.Objects;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

/**
 * Parity-tier Java harness. Each scenario's Main.java calls one of the {@code run}
 * methods, passing both worker registrations (workflows + activities) and a driver
 * closure. The harness dispatches based on {@code --mode worker|starter}:
 *
 * <ul>
 *   <li><b>worker</b>: register workflows + activities, {@code factory.start()},
 *       and block until SIGTERM. A shutdown hook drains gRPC cleanly so Temporal
 *       sees the worker disconnect before the next scenario's worker spins up on
 *       the same shared task queue. {@code main} returns and the JVM stays alive
 *       on gRPC non-daemon threads; SIGTERM triggers the hook.</li>
 *   <li><b>starter</b>: build a client (no worker, no factory), call the driver
 *       (which executes the workflow), print {@code WORKFLOW_ID=}, close gRPC
 *       stubs, {@code System.exit(0)}.</li>
 * </ul>
 *
 * Shell scripts coordinate: launch the worker JVM in the background, run the
 * starter JVM in the foreground, then SIGTERM the worker once the starter exits.
 * This mirrors the canonical {@code samples-java} worker/starter split and avoids
 * the {@code Runtime.halt} mid-poll race that flaked shared-task-queue runs.
 */
public final class ParityRunner {

    private static final Duration DEFAULT_TIMEOUT = Duration.ofSeconds(15);
    private static final Duration WORKER_SHUTDOWN_BUDGET = Duration.ofSeconds(30);

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
    ) {
        runWithTimeout(args, slug, defaultNamespace, defaultTaskQueue,
            workflowClass, workflowImpls, activities, DEFAULT_TIMEOUT,
            driver, expected);
    }

    public static <T> void runWithTimeout(
        String[] args,
        String slug,
        String defaultNamespace,
        String defaultTaskQueue,
        Class<T> workflowClass,
        Class<?>[] workflowImpls,
        Object[] activities,
        Duration workflowTimeout,
        Driver<T> driver,
        Object expected
    ) {
        ParityArgs parsed = ParityArgs.parse(args, defaultNamespace, defaultTaskQueue);
        switch (parsed.mode) {
            case WORKER -> runWorker(parsed, workflowImpls, activities);
            case STARTER -> runStarter(parsed, slug, workflowClass, workflowTimeout, driver, expected);
        }
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
    ) {
        ParityArgs parsed = ParityArgs.parse(args, defaultNamespace, defaultTaskQueue);
        switch (parsed.mode) {
            case WORKER -> runWorker(parsed, workflowImpls, activities);
            case STARTER -> runStarterCapturingFirstRun(parsed, slug, workflowClass,
                workflowTimeout != null ? workflowTimeout : DEFAULT_TIMEOUT,
                starter, resultClass, expected);
        }
    }

    private static void runWorker(ParityArgs parsed, Class<?>[] workflowImpls, Object[] activities) {
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

        System.out.println("WORKER_READY");
        System.out.flush();

        CountDownLatch latch = new CountDownLatch(1);
        Runtime.getRuntime().addShutdownHook(new Thread(() -> {
            try {
                factory.shutdown();
                factory.awaitTermination(WORKER_SHUTDOWN_BUDGET.toMillis() / 2, TimeUnit.MILLISECONDS);
            } catch (Throwable ignored) {}
            try {
                serviceStubs.shutdownNow();
                serviceStubs.awaitTermination(WORKER_SHUTDOWN_BUDGET.toMillis() / 2, TimeUnit.MILLISECONDS);
            } catch (Throwable ignored) {}
            latch.countDown();
        }, "parity-worker-shutdown"));

        try {
            latch.await();
        } catch (InterruptedException ignored) {
            Thread.currentThread().interrupt();
        }
    }

    private static <T> void runStarter(
        ParityArgs parsed,
        String slug,
        Class<T> workflowClass,
        Duration workflowTimeout,
        Driver<T> driver,
        Object expected
    ) {
        WorkflowServiceStubs serviceStubs = WorkflowServiceStubs.newServiceStubs(
            WorkflowServiceStubsOptions.newBuilder().setTarget(parsed.address).build()
        );
        WorkflowClient client = WorkflowClient.newInstance(
            serviceStubs,
            WorkflowClientOptions.newBuilder().setNamespace(parsed.namespace).build()
        );

        String workflowId = "parity-" + slug + "-java-" + UUID.randomUUID();
        T stub = client.newWorkflowStub(
            workflowClass,
            WorkflowOptions.newBuilder()
                .setWorkflowId(workflowId)
                .setTaskQueue(parsed.taskQueue)
                .setWorkflowExecutionTimeout(workflowTimeout != null ? workflowTimeout : DEFAULT_TIMEOUT)
                .build()
        );

        int exitCode;
        try {
            Object result = driver.apply(client, stub);
            if (!Objects.equals(expected, result)) {
                System.err.println("[parity-java] unexpected: " + result);
                System.err.flush();
                exitCode = 2;
            } else {
                System.out.println("WORKFLOW_ID=" + workflowId);
                System.out.flush();
                exitCode = 0;
            }
        } catch (Throwable t) {
            System.err.println("[parity-java] driver threw: " + t);
            t.printStackTrace(System.err);
            System.err.flush();
            exitCode = 1;
        } finally {
            try {
                serviceStubs.shutdownNow();
                serviceStubs.awaitTermination(2, TimeUnit.SECONDS);
            } catch (Throwable ignored) {}
        }
        System.exit(exitCode);
    }

    private static <T, R> void runStarterCapturingFirstRun(
        ParityArgs parsed,
        String slug,
        Class<T> workflowClass,
        Duration workflowTimeout,
        AsyncStarter<T> starter,
        Class<R> resultClass,
        Object expected
    ) {
        WorkflowServiceStubs serviceStubs = WorkflowServiceStubs.newServiceStubs(
            WorkflowServiceStubsOptions.newBuilder().setTarget(parsed.address).build()
        );
        WorkflowClient client = WorkflowClient.newInstance(
            serviceStubs,
            WorkflowClientOptions.newBuilder().setNamespace(parsed.namespace).build()
        );

        String workflowId = "parity-" + slug + "-java-" + UUID.randomUUID();
        T stub = client.newWorkflowStub(
            workflowClass,
            WorkflowOptions.newBuilder()
                .setWorkflowId(workflowId)
                .setTaskQueue(parsed.taskQueue)
                .setWorkflowExecutionTimeout(workflowTimeout)
                .build()
        );

        int exitCode;
        try {
            WorkflowExecution execution = starter.start(client, stub);
            System.out.println("WORKFLOW_ID=" + workflowId);
            System.out.println("RUN_ID=" + execution.getRunId());
            System.out.flush();

            R result = WorkflowStub.fromTyped(stub).getResult(resultClass);
            if (!Objects.equals(expected, result)) {
                System.err.println("[parity-java] unexpected: " + result);
                System.err.flush();
                exitCode = 2;
            } else {
                exitCode = 0;
            }
        } catch (Throwable t) {
            System.err.println("[parity-java] driver threw: " + t);
            t.printStackTrace(System.err);
            System.err.flush();
            exitCode = 1;
        } finally {
            try {
                serviceStubs.shutdownNow();
                serviceStubs.awaitTermination(2, TimeUnit.SECONDS);
            } catch (Throwable ignored) {}
        }
        System.exit(exitCode);
    }

    private ParityRunner() {}
}
