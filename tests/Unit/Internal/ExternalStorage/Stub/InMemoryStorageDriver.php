<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\ExternalStorage\Stub;

use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverInterface;
use Temporal\DataConverter\ExternalStorage\StorageDriverLimiterInterface;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;

final class InMemoryStorageDriver implements StorageDriverInterface
{
    /** @var array<string, string> */
    public array $objects = [];

    /** @var list<StorageDriverStoreContext> */
    public array $storeContexts = [];

    /** @var list<int> */
    public array $retrieveBatchSizes = [];

    /**
     * @param non-empty-string $name
     * @param null|\Closure(list<StorageDriverClaim>): array $claims
     */
    public function __construct(
        private readonly string $name = 'memory',
        private readonly ?\Closure $claims = null,
        private readonly bool $usePermits = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'test.memory';
    }

    public function store(StorageDriverStoreContext $context, array $payloads): array
    {
        $this->storeContexts[] = $context;

        $claims = [];
        foreach ($payloads as $payload) {
            $id = (string) \count($this->objects);
            $this->run($context->limiter, $payload, function () use ($id, $payload): void {
                $this->objects[$id] = $payload->serializeToString();
            });
            $claims[] = new StorageDriverClaim(['id' => $id]);
        }

        return $this->claims === null ? $claims : ($this->claims)($claims);
    }

    public function retrieve(StorageDriverRetrieveContext $context, array $claims): array
    {
        $this->retrieveBatchSizes[] = \count($claims);

        $payloads = [];
        foreach ($claims as $claim) {
            $payload = new Payload();
            $this->run($context->limiter, $claim, function () use ($payload, $claim): void {
                $payload->mergeFromString($this->objects[$claim->claimData['id']]);
            });
            $payloads[] = $payload;
        }

        return $payloads;
    }

    private function run(
        StorageDriverLimiterInterface $limiter,
        Payload|StorageDriverClaim $item,
        \Closure $operation,
    ): void {
        if ($this->usePermits) {
            $limiter->permit($item, $operation);
            return;
        }

        $operation();
    }
}
