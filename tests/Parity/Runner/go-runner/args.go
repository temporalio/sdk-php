package gorunner

type Mode int

const (
	ModeStarter Mode = iota
	ModeWorker
)

type Args struct {
	Address   string
	Namespace string
	TaskQueue string
	Mode      Mode
}

func ParseArgs(argv []string, defaultNamespace, defaultTaskQueue string) Args {
	out := Args{
		Address:   "127.0.0.1:7233",
		Namespace: defaultNamespace,
		TaskQueue: defaultTaskQueue,
		Mode:      ModeStarter,
	}
	for i := 0; i+1 < len(argv); i += 2 {
		switch argv[i] {
		case "--address":
			out.Address = argv[i+1]
		case "--namespace":
			out.Namespace = argv[i+1]
		case "--task-queue":
			out.TaskQueue = argv[i+1]
		case "--mode":
			switch argv[i+1] {
			case "worker":
				out.Mode = ModeWorker
			case "starter", "driver":
				out.Mode = ModeStarter
			default:
				panic("[parity-go] --mode must be 'worker' or 'starter', got: " + argv[i+1])
			}
		}
	}
	return out
}
