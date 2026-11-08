<?php

declare(strict_types=1);

namespace Emu;

use React\Promise\Deferred;
use React\Promise\Internal\CancellationQueue;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

final class Emu
{
    public const NONE = 'none';
    public const EXCEPTION = 'exception';
    public const FIX = 'fix';

    public static string $strategy = self::FIX;
    public static array $log = [];
    public static bool $evicting = false;

    public static function record(string ...$entry): void
    {
        self::$log[] = [self::$evicting ? 'evict' : 'run', ...$entry];
    }

    public static function is(string $strategy): bool
    {
        return self::$strategy === $strategy;
    }
}

final class Destruct extends \RuntimeException {}

final class Canceled extends \RuntimeException {}

final class IllegalState extends \LogicException {}

interface Destroyable
{
    public function destroy(): void;
}

final class Command
{
    private static int $nextId = 0;
    public readonly int $id;

    public function __construct(
        public readonly string $name,
        public readonly mixed $argument = null,
    ) {
        $this->id = ++self::$nextId;
    }
}

final class Loop
{
    public const SIGNAL = 'signal';
    public const CALLBACK = 'callback';
    public const TICK = 'tick';
    public const FINALLY = 'finally';

    private array $listeners = [];

    public function once(string $layer, \Closure $listener): void
    {
        $this->listeners[$layer][] = $listener;
    }

    public function tick(): void
    {
        foreach ([self::SIGNAL, self::CALLBACK, self::TICK, self::FINALLY] as $layer) {
            $listeners = $this->listeners[$layer] ?? [];
            unset($this->listeners[$layer]);
            foreach ($listeners as $listener) {
                $listener();
            }
        }
    }
}

final class Ctx
{
    public static ?ScopeContext $current = null;

    public static function using(?ScopeContext $context, \Closure $callback): mixed
    {
        $saved = self::$current;
        self::$current = $context;
        try {
            return $callback();
        } finally {
            self::$current = $saved;
        }
    }
}

final class Services
{
    public array $commands = [];

    public function __construct(
        public readonly Loop $loop = new Loop(),
    ) {}
}

final class Client
{
    private array $requests = [];

    public function __construct(
        private Services $services,
    ) {}

    public function request(Command $command, ScopeContext $context): PromiseInterface
    {
        $this->services->commands[$command->id] = $command;
        $deferred = new Deferred();
        $this->requests[$command->id] = [$deferred, $context];

        return $deferred->promise();
    }

    public function dispatch(int $id, mixed $value): void
    {
        [$deferred, $context] = $this->requests[$id];
        unset($this->requests[$id]);
        Ctx::$current = $context;
        $deferred->resolve($value);
    }

    public function isQueued(Command $command): bool
    {
        return isset($this->services->commands[$command->id]);
    }

    public function cancel(Command $command): void
    {
        unset($this->services->commands[$command->id]);
        $this->reject($command, new Canceled('internal cancel'));
    }

    public function reject(Command $command, \Throwable $reason): void
    {
        [$deferred] = $this->requests[$command->id];
        unset($this->requests[$command->id]);
        $deferred->reject($reason);
    }

    public function destroy(): void
    {
        $this->requests = [];
    }
}

final class Run
{
    public array $awaits = [];
    private bool $readOnly = false;

    public function __construct(
        public readonly string $id,
        public readonly Services $services,
        public Client $client,
        public object $instance,
    ) {}

    public function assertWritable(): void
    {
        if ($this->readOnly) {
            throw new IllegalState('Commands are not allowed in read-only mode');
        }
    }

    public function runReadOnly(\Closure $callback): mixed
    {
        if ($this->readOnly) {
            return $callback();
        }

        $this->readOnly = true;
        try {
            return $callback();
        } finally {
            $this->readOnly = false;
        }
    }

    public function resolveConditions(): void
    {
        foreach ($this->awaits as $i => [$condition, $deferred]) {
            if ($this->runReadOnly($condition)) {
                unset($this->awaits[$i]);
                $deferred->resolve(null);
            }
        }
    }

    public function destroy(): void
    {
        $this->awaits = [];
        $this->client->destroy();
        if ($this->instance instanceof Destroyable) {
            $this->instance->destroy();
        }
        unset($this->instance, $this->client);
    }
}

final class ScopeContext
{
    public function __construct(
        public Run $run,
        public Scope $scope,
        public \Closure $onRequest,
    ) {}

    public function request(Command $command, bool $cancellable = true): PromiseInterface
    {
        $this->run->assertWritable();
        $promise = $this->run->client->request($command, $this);
        ($this->onRequest)($command, $promise, $cancellable);

        $result = new CompletableResult($this, $this->run->services->loop, $promise, $this->scope->layer);
        $this->scope->own($result);

        return $result;
    }

    public function await(\Closure $condition): PromiseInterface
    {
        $this->run->assertWritable();
        $deferred = new Deferred();
        $this->run->awaits[] = [$condition, $deferred];
        $this->scope->onAwait($deferred);

        $result = new CompletableResult($this, $this->run->services->loop, $deferred->promise(), $this->scope->layer);
        $this->scope->own($result);

        return $result;
    }

    public function destroy(): void
    {
        unset($this->scope, $this->run, $this->onRequest);
    }
}

final class W
{
    public static function runId(): string
    {
        return self::context()->run->id;
    }

    public static function timer(int $seconds): PromiseInterface
    {
        return self::context()->request(new Command('timer', $seconds));
    }

    public static function await(\Closure $condition): PromiseInterface
    {
        return self::context()->await($condition);
    }

    public static function async(\Closure $handler): Scope
    {
        return self::context()->scope->startScope($handler, false);
    }

    public static function asyncDetached(\Closure $handler): Scope
    {
        return self::context()->scope->startScope($handler, true);
    }

    private static function context(): ScopeContext
    {
        return Ctx::$current ?? throw new \LogicException('No workflow context');
    }
}

final class Abandoned implements PromiseInterface
{
    public function __construct(
        private readonly bool $forward = false,
    ) {}

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        if (!$this->forward) {
            return new self(forward: true);
        }

        if ($onFulfilled !== null) {
            $onFulfilled(new self());
        }

        return new self();
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this;
    }

    public function cancel(): void {}

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this;
    }
}

final class CompletableResult implements PromiseInterface
{
    private Deferred $deferred;
    private PromiseInterface $promise;

    public function __construct(
        private ScopeContext $context,
        private Loop $loop,
        PromiseInterface $promise,
        private string $layer,
    ) {
        $this->deferred = new Deferred();
        $this->promise = $promise->then($this->onFulfilled(...), $this->onRejected(...));
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        return $this->deferred->promise()->then($this->wrapContext($onFulfilled), $this->wrapContext($onRejected));
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->deferred->promise()->catch($this->wrapContext($onRejected));
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->deferred->promise()->finally($this->wrapContext($onFulfilledOrRejected));
    }

    public function cancel(): void
    {
        Ctx::using($this->context, $this->deferred->promise()->cancel(...));
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->catch($onRejected);
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->finally($onFulfilledOrRejected);
    }

    public function abandon(): void
    {
        $this->deferred->resolve(new Abandoned());
    }

    private function onFulfilled(mixed $result): void
    {
        $this->loop->once($this->layer, function () use ($result): void {
            Ctx::using($this->context, fn() => $this->deferred->resolve($result));
        });
    }

    private function onRejected(\Throwable $e): void
    {
        $this->loop->once($this->layer, function () use ($e): void {
            Ctx::using($this->context, fn() => $this->deferred->reject($e));
        });
    }

    private function wrapContext(?callable $callback): ?callable
    {
        if ($callback === null) {
            return null;
        }

        return fn(mixed $value = null): mixed => Ctx::using($this->context, static fn(): mixed => $callback($value));
    }
}

final class Coroutine
{
    private bool $started = false;
    private bool $finished = false;
    private \Generator $generator;
    private array $catchers = [];
    private ?\Closure $handler = null;

    public static function fromHandler(\Closure $handler): self
    {
        $self = new self();
        $self->handler = $handler;
        return $self;
    }

    public static function fromGenerator(\Generator $generator): self
    {
        $self = new self();
        $self->generator = $generator;
        $self->started = true;
        return $self;
    }

    public function catch(callable $handler): self
    {
        $this->catchers[] = $handler;
        return $this;
    }

    public function valid(): bool
    {
        $this->start();
        try {
            $result = $this->generator->valid() or $this->finished = true;
            return $result;
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function current(): mixed
    {
        $this->start();
        try {
            return $this->generator->current();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function send(mixed $value): mixed
    {
        $this->start();
        try {
            return $this->generator->send($value);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function throw(\Throwable $exception): void
    {
        try {
            $this->generator->throw($exception);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function getReturn(): mixed
    {
        try {
            return $this->generator->getReturn();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        try {
            $result = ($this->handler)();
            if ($result instanceof \Generator) {
                $this->generator = $result;
                return;
            }

            $this->generator = (static function (mixed $result): \Generator {
                return $result;
                yield;
            })($result);
            $this->finished = true;
        } catch (\Throwable $e) {
            $this->generator = (static function (): \Generator {
                yield;
            })();
            $this->handleException($e);
        } finally {
            $this->handler = null;
        }
    }

    private function handleException(\Throwable $e): never
    {
        $this->finished and throw $e;
        $this->finished = true;
        foreach ($this->catchers as $catcher) {
            try {
                $catcher($e);
            } catch (\Throwable) {
            }
        }

        $this->catchers = [];
        throw $e;
    }
}

class Scope implements PromiseInterface
{
    public string $layer = Loop::TICK;
    protected Services $services;
    protected Run $context;
    protected ScopeContext $scopeContext;
    protected Deferred $deferred;
    protected Coroutine $coroutine;
    private int $cancelID = 0;
    private array $onCancel = [];
    private array $onClose = [];
    private bool $detached = false;
    private bool $cancelled = false;
    private bool $closed = false;
    private \WeakMap $owned;
    private ?PromiseInterface $awaiting = null;

    public function __construct(Services $services)
    {
        $this->services = $services;
        $this->deferred = new Deferred();
        $this->owned = new \WeakMap();
    }

    public function start(\Closure $handler): void
    {
        $this->coroutine = Coroutine::fromHandler($handler)->catch($this->onException(...));
        $this->next();
    }

    public function attach(\Generator $generator): self
    {
        $this->coroutine = Coroutine::fromGenerator($generator)->catch($this->onException(...));
        $this->next();
        return $this;
    }

    public function onCancel(callable $then): self
    {
        $this->addOnCancel($then);
        return $this;
    }

    public function onClose(callable $then): self
    {
        $this->onClose[] = $then;
        return $this;
    }

    public function cancel(?\Throwable $reason = null): void
    {
        if ($this->detached && !(Emu::is(Emu::EXCEPTION) && $reason instanceof Destruct)) {
            return;
        }

        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        Ctx::using($this->scopeContext, function () use ($reason): void {
            foreach ($this->onCancel as $i => $handler) {
                $this->makeCurrent();
                unset($this->onCancel[$i]);
                $handler($reason);
            }
        });
    }

    public function startScope(\Closure $handler, bool $detached, ?string $layer = null): self
    {
        $scope = $this->createScope($detached, $layer);
        $scope->start($handler);

        return $scope;
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        return $this->deferred->promise()->then($onFulfilled, $onRejected);
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->deferred->promise()->catch($onRejected);
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->deferred->promise()->finally($onFulfilledOrRejected);
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->catch($onRejected);
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->finally($onFulfilledOrRejected);
    }

    public function onAwait(Deferred $deferred): void
    {
        $cancelID = $this->addOnCancel(static function (?\Throwable $e = null) use ($deferred): void {
            $deferred->reject($e ?? new Canceled(''));
        });

        $cleanup = function () use ($cancelID): void {
            Ctx::using($this->scopeContext, function () use ($cancelID): void {
                $this->context->resolveConditions();
                unset($this->onCancel[$cancelID]);
            });
        };

        $deferred->promise()->then($cleanup, $cleanup);
    }

    public function own(CompletableResult|\Closure $root): void
    {
        if (Emu::is(Emu::FIX)) {
            $this->owned[$root] = null;
        }
    }

    public function destroy(): void
    {
        if (Emu::is(Emu::FIX)) {
            $this->evict();
            return;
        }

        Ctx::using($this->scopeContext, function (): void {
            $this->context->destroy();
            $this->scopeContext->destroy();
        });

        $this->release();
    }

    protected function createScope(bool $detached, ?string $layer = null): self
    {
        $scope = new self($this->services);
        $scope->setContext($this->context);
        $scope->detached = $detached;

        if ($layer !== null) {
            $scope->layer = $layer;
        }

        $cancelID = $this->addOnCancel($scope->cancel(...));
        $scope->onClose(function () use ($cancelID): void {
            unset($this->onCancel[$cancelID]);
        });

        if (Emu::is(Emu::FIX)) {
            $scope->owned = $this->owned;
            $this->owned[$scope] = null;
        }

        return $scope;
    }

    protected function setContext(Run $context): void
    {
        $this->context = $context;
        $this->scopeContext = new ScopeContext($context, $this, $this->onRequest(...));
    }

    protected function onRequest(Command $request, PromiseInterface $promise, bool $cancellable = true): void
    {
        $cancelID = $this->addOnCancel(function (?\Throwable $reason = null) use ($request, $cancellable): void {
            $client = $this->context->client;
            if (Emu::is(Emu::EXCEPTION) && $reason instanceof Destruct) {
                $client->reject($request, $reason);
                return;
            }

            if ($client->isQueued($request)) {
                $client->cancel($request);
                return;
            }

            if (!$cancellable) {
                return;
            }

            $client->request(new Command('cancel', $request->id), $this->scopeContext);
        });

        $cleanup = function () use ($cancelID): void {
            Ctx::using($this->scopeContext, function () use ($cancelID): void {
                $this->context->resolveConditions();
                unset($this->onCancel[$cancelID]);
            });
        };

        $promise->then($cleanup, $cleanup);
    }

    protected function makeCurrent(): void
    {
        Ctx::$current = $this->scopeContext;
    }

    protected function next(): void
    {
        $this->makeCurrent();
        begin:
        $this->context->resolveConditions();

        try {
            if (!$this->coroutine->valid()) {
                $this->onResult($this->coroutine->getReturn());
                return;
            }
        } catch (\Throwable) {
            $this->onResult(null);
            return;
        }

        $current = $this->coroutine->current();
        $this->context->resolveConditions();

        switch (true) {
            case $current instanceof PromiseInterface:
                $this->nextPromise($current);
                break;

            case $current instanceof \Generator:
                $this->nextPromise($this->createScope(false)->attach($current));
                break;

            default:
                try {
                    $this->coroutine->send($current);
                } catch (\Throwable) {
                }
                goto begin;
        }
    }

    private function evict(): void
    {
        $scopes = [];
        $roots = [];
        foreach ($this->owned as $item => $_) {
            if ($item instanceof self) {
                $scopes[] = $item;
            } else {
                $roots[] = $item;
            }
        }

        $scopes = [...\array_reverse($scopes), $this];
        foreach ($scopes as $scope) {
            $scope->cancelled = true;
            $scope->closed = true;
        }

        $this->context->runReadOnly(static function () use ($scopes): void {
            foreach ($scopes as $scope) {
                $scope->closeCoroutine();
            }
        });

        foreach ($roots as $root) {
            if ($root instanceof \Closure) {
                $root(new Abandoned());
            } else {
                $root->abandon();
            }
        }

        $this->context->runReadOnly(static function () use ($scopes): void {
            foreach ($scopes as $scope) {
                $scope->cancelAwaited();
            }
        });

        Ctx::using($this->scopeContext, function (): void {
            $this->context->destroy();
            $this->scopeContext->destroy();
        });

        foreach ($scopes as $scope) {
            $scope->release();
        }
    }

    private function closeCoroutine(): void
    {
        Ctx::using($this->scopeContext, function (): void {
            try {
                unset($this->coroutine);
            } catch (\Throwable) {
            }
        });
    }

    private function cancelAwaited(): void
    {
        Ctx::using($this->scopeContext, function (): void {
            try {
                $this->awaiting?->cancel();
            } catch (\Throwable) {
            }
        });
    }

    private function release(): void
    {
        $this->awaiting = null;
        unset(
            $this->coroutine,
            $this->context,
            $this->scopeContext,
            $this->deferred,
            $this->services,
            $this->onCancel,
            $this->onClose,
        );
    }

    private function addOnCancel(callable $handler): int
    {
        $id = ++$this->cancelID;
        $this->onCancel[$id] = $handler;
        return $id;
    }

    private function nextPromise(PromiseInterface $promise): void
    {
        $onFulfilled = function (mixed $result): mixed {
            $this->defer(function () use ($result): void {
                $this->makeCurrent();
                try {
                    $this->coroutine->send($result);
                    $this->next();
                } catch (\Throwable $e) {
                    $this->onException($e);
                }
            });

            return $result;
        };

        $onRejected = function (\Throwable $e): void {
            $this->defer(function () use ($e): void {
                $this->handleError($e);
            });

            throw $e;
        };

        $awaiting = $promise
            ->then($onFulfilled, $onRejected)
            ->then(null, static fn(\Throwable $e) => null);

        if (Emu::is(Emu::FIX)) {
            $this->awaiting = $awaiting;
        }
    }

    private function handleError(\Throwable $e): void
    {
        $this->makeCurrent();

        try {
            $this->coroutine->throw($e);
        } catch (\Throwable $e) {
            $this->onException($e);
            return;
        }

        $this->next();
    }

    private function onException(\Throwable $e): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->deferred->reject($e);
        $this->makeCurrent();
        $this->context->resolveConditions();

        foreach ($this->onClose as $close) {
            $close($e);
        }
    }

    private function onResult(mixed $result): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->deferred->resolve($result);
        $this->makeCurrent();
        $this->context->resolveConditions();

        foreach ($this->onClose as $close) {
            $close($result);
        }
    }

    private function defer(\Closure $tick): void
    {
        $this->services->loop->once(
            $this->layer,
            fn(): mixed => Emu::is(Emu::FIX) && $this->closed ? null : Ctx::using($this->scopeContext, $tick),
        );
        $this->services->commands === [] and $this->services->loop->tick();
    }
}

final class Process extends Scope
{
    public function __construct(Services $services, string $runId, object $instance, \Closure $handler)
    {
        parent::__construct($services);
        $this->setContext(new Run($runId, $services, new Client($services), $instance));
        $this->then($this->complete(...), $this->complete(...));
        $this->start($handler);
    }

    public function client(): Client
    {
        return $this->context->client;
    }

    public function signal(\Closure $handler): void
    {
        $this->createScope(true, Loop::SIGNAL)
            ->onClose(function (mixed $result): void {
                if ($result instanceof \Throwable) {
                    $this->complete($result);
                }
            })
            ->start($handler);
    }

    private function complete(mixed $result): void
    {
        if ($result instanceof Destruct) {
            return;
        }

        $command = new Command($result instanceof \Throwable ? 'fail' : 'complete');
        $this->services->commands[$command->id] = $command;
    }
}

final class Worker
{
    public readonly Services $services;
    private array $running = [];

    public function __construct()
    {
        $this->services = new Services();
    }

    public function start(string $runId, object $instance, \Closure $handler): void
    {
        $this->running[$runId] = new Process($this->services, $runId, $instance, $handler);
    }

    public function signal(string $runId, \Closure $handler): void
    {
        $this->running[$runId]->signal($handler);
    }

    public function respond(string $runId, Command $command): void
    {
        $this->running[$runId]->client()->dispatch($command->id, null);
    }

    public function evict(string $runId): void
    {
        $process = $this->running[$runId];
        unset($this->running[$runId]);

        if (Emu::is(Emu::EXCEPTION)) {
            $process->cancel(new Destruct());
        }

        $this->services->loop->once(Loop::FINALLY, static function () use ($process): void {
            $process->destroy();
        });
    }

    public function batch(\Closure ...$steps): array
    {
        foreach ($steps as $step) {
            $step($this);
        }

        $this->services->loop->tick();
        $commands = $this->services->commands;
        $this->services->commands = [];

        return \array_values($commands);
    }
}

final class TPromise
{
    public static function all(iterable $promises): PromiseInterface
    {
        return self::map($promises, static fn(mixed $value): mixed => $value);
    }

    public static function any(iterable $promises): PromiseInterface
    {
        return self::some([...$promises], 1)->then(static fn(array $values): mixed => \array_shift($values));
    }

    public static function some(array $promises, int $count): PromiseInterface
    {
        $cancellationQueue = new CancellationQueue();

        return new Promise(
            static function (callable $resolve, callable $reject) use ($promises, $count, $cancellationQueue): void {
                self::own($resolve);
                $toResolve = $count;
                $toReject = \count($promises) - $toResolve + 1;
                $values = [];
                foreach ($promises as $i => $promiseOrValue) {
                    $cancellationQueue->enqueue($promiseOrValue);
                    resolve($promiseOrValue)->then(
                        static function (mixed $value) use ($i, &$values, &$toResolve, $toReject, $resolve): void {
                            if ($toResolve < 1 || $toReject < 1) {
                                return;
                            }
                            $values[$i] = $value;
                            if (--$toResolve === 0) {
                                $resolve($values);
                            }
                        },
                        static function (\Throwable $reason) use (&$toReject, $toResolve, $reject): void {
                            if ($toResolve < 1 || $toReject < 1) {
                                return;
                            }
                            if (--$toReject === 0) {
                                $reject($reason);
                            }
                        },
                    );
                }
            },
            $cancellationQueue,
        );
    }

    public static function map(iterable $promises, callable $map): PromiseInterface
    {
        $cancellationQueue = new CancellationQueue();

        return new Promise(
            static function (callable $resolve, callable $reject) use ($promises, $map, $cancellationQueue): void {
                self::own($resolve);
                $toResolve = \count($promises);
                $values = [];
                foreach ($promises as $i => $promiseOrValue) {
                    $cancellationQueue->enqueue($promiseOrValue);
                    $values[$i] = null;
                    resolve($promiseOrValue)->then($map)->then(
                        static function (mixed $mapped) use ($i, &$values, &$toResolve, $resolve): void {
                            $values[$i] = $mapped;
                            if (--$toResolve === 0) {
                                $resolve($values);
                            }
                        },
                        $reject,
                    );
                }
            },
            $cancellationQueue,
        );
    }

    public static function race(iterable $promises): PromiseInterface
    {
        if (!Emu::is(Emu::FIX)) {
            return \React\Promise\race([...$promises]);
        }

        $cancellationQueue = new CancellationQueue();

        return new Promise(
            static function (callable $resolve, callable $reject) use ($promises, $cancellationQueue): void {
                self::own($resolve);
                foreach ($promises as $promiseOrValue) {
                    $cancellationQueue->enqueue($promiseOrValue);
                    resolve($promiseOrValue)->then($resolve, $reject);
                }
            },
            $cancellationQueue,
        );
    }

    private static function own(callable $resolve): void
    {
        Ctx::$current?->scope->own(\Closure::fromCallable($resolve));
    }
}
