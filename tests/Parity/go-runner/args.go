package gorunner

type Args struct {
	Address   string
	Namespace string
	TaskQueue string
}

func ParseArgs(argv []string, defaultNamespace, defaultTaskQueue string) Args {
	out := Args{
		Address:   "127.0.0.1:7233",
		Namespace: defaultNamespace,
		TaskQueue: defaultTaskQueue,
	}
	for i := 0; i+1 < len(argv); i += 2 {
		switch argv[i] {
		case "--address":
			out.Address = argv[i+1]
		case "--namespace":
			out.Namespace = argv[i+1]
		case "--task-queue":
			out.TaskQueue = argv[i+1]
		}
	}
	return out
}
