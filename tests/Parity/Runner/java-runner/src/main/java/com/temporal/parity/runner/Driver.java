package com.temporal.parity.runner;

import io.temporal.client.WorkflowClient;

@FunctionalInterface
public interface Driver<T> {
    Object apply(WorkflowClient client, T stub) throws Exception;
}
