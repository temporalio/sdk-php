package com.temporal.parity.runner;

import java.util.HashMap;
import java.util.Map;

public final class ParityArgs {

    public enum Mode { WORKER, STARTER }

    public final String address;
    public final String namespace;
    public final String taskQueue;
    public final Mode mode;

    private ParityArgs(String address, String namespace, String taskQueue, Mode mode) {
        this.address = address;
        this.namespace = namespace;
        this.taskQueue = taskQueue;
        this.mode = mode;
    }

    public static ParityArgs parse(String[] args, String defaultNamespace, String defaultTaskQueue) {
        Map<String, String> map = new HashMap<>();
        for (int i = 0; i + 1 < args.length; i += 2) {
            map.put(args[i], args[i + 1]);
        }
        String modeStr = map.getOrDefault("--mode", "starter");
        Mode mode = switch (modeStr) {
            case "worker" -> Mode.WORKER;
            case "starter", "driver" -> Mode.STARTER;
            default -> throw new IllegalArgumentException("--mode must be 'worker' or 'starter', got: " + modeStr);
        };
        return new ParityArgs(
            map.getOrDefault("--address", "127.0.0.1:7233"),
            map.getOrDefault("--namespace", defaultNamespace),
            map.getOrDefault("--task-queue", defaultTaskQueue),
            mode
        );
    }

    private ParityArgs() { this(null, null, null, Mode.STARTER); }
}
