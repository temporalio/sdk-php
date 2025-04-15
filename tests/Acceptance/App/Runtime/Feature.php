<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\DataConverter\PayloadConverterInterface;

final class Feature
{
    /** @var list<class-string> Workflow classes */
    public array $workflows = [];

    /** @var list<class-string> Activity classes */
    public array $activities = [];

    /** @var list<array<class-string, non-empty-string>> Lazy callables */
    public array $checks = [];

    /** @var list<class-string<PayloadConverterInterface>> Lazy callables */
    public array $converters = [];

    /**
     * @param non-empty-string $taskQueue
     */
    public function __construct(
        public readonly string $taskQueue,
    ) {}
}
