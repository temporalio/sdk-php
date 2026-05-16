package com.temporal.parity.runner;

import io.temporal.api.common.v1.WorkflowExecution;
import io.temporal.client.WorkflowClient;

@FunctionalInterface
public interface AsyncStarter<T> {
    WorkflowExecution start(WorkflowClient client, T stub) throws Exception;
}
