package com.temporal.parity.runner;

import java.util.HashMap;
import java.util.Map;

public final class ParityArgs {

    public final String address;
    public final String namespace;
    public final String taskQueue;

    private ParityArgs(String address, String namespace, String taskQueue) {
        this.address = address;
        this.namespace = namespace;
        this.taskQueue = taskQueue;
    }

    public static ParityArgs parse(String[] args, String defaultNamespace, String defaultTaskQueue) {
        Map<String, String> map = new HashMap<>();
        for (int i = 0; i + 1 < args.length; i += 2) {
            map.put(args[i], args[i + 1]);
        }
        return new ParityArgs(
            map.getOrDefault("--address", "127.0.0.1:7233"),
            map.getOrDefault("--namespace", defaultNamespace),
            map.getOrDefault("--task-queue", defaultTaskQueue)
        );
    }

    private ParityArgs() { this(null, null, null); }
}
