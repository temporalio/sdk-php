# Eviction emulator for issue #507

A model of the eviction path without the SDK. It uses only `internal/promise` from `vendor/`.
It copies the object graph of `Scope`, `ScopeContext`, `CompletableResult`, `Client` and the combinators.

```bash
php emulator/run.php
php emulator/run.php --strategy=fix
```

Strategies:

- `exception`: the old behaviour. Eviction throws `Destruct` into the workflow.
- `none`: the base branch. Eviction only destroys the root scope.
- `fix`: owned promises settle with `Abandoned`, generators close read-only in their context.

Each shape runs in its own process with the GC disabled. The columns:

- `cycles`: the result of `gc_collect_cycles()` after the eviction batch. It must be 0.
- `alive`: the workflow instance is still referenced.
- `next`: a new run in the same worker completes after the eviction.
- the log shows what ran during eviction: `user` is user code, `finally` shows the run of its context.

The `fix` strategy fails on these shapes:

- `react_nested`, `react_fork`, `user_deferred`, `user_deferred_react`: pending promises that the SDK does not own.
  Only a settlement or a cancellation frees them, and both run user code.
- `nested_yield`: PHP destroys the outer generator before the generator that it yielded.
