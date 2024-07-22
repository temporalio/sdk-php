<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Temporal\Tests\Acceptance\App\Input\Command;
use Temporal\DataConverter\PayloadConverterInterface;

final class State
{
    /** @var array<Feature> */
    public array $features = [];

    /** @var non-empty-string */
    public string $namespace;

    /** @var non-empty-string */
    public string $address;

    /**
     * @param non-empty-string $rrConfigDir Dir with rr.yaml
     * @param non-empty-string $workDir Dir where tests are run
     */
    public function __construct(
        public readonly Command $command,
        public readonly string $rrConfigDir,
        public readonly string $workDir,
    ) {
        $this->namespace = $command->namespace ?? 'default';
        $this->address = $command->address ?? 'localhost:7233';
    }

    /**
     * Iterate over all the Workflows.
     *
     * @return \Traversable<Feature, class-string>
     */
    public function workflows(): \Traversable
    {
        foreach ($this->features as $feature) {
            foreach ($feature->workflows as $workflow) {
                yield $feature => $workflow;
            }
        }
    }

    /**
     * Iterate over all the Activities.
     *
     * @return \Traversable<Feature, class-string>
     */
    public function activities(): \Traversable
    {
        foreach ($this->features as $feature) {
            foreach ($feature->activities as $activity) {
                yield $feature => $activity;
            }
        }
    }

    /**
     * Iterate over all the Payload Converters.
     *
     * @return \Traversable<Feature, class-string<PayloadConverterInterface>>
     */
    public function converters(): \Traversable
    {
        foreach ($this->features as $feature) {
            foreach ($feature->converters as $converter) {
                yield $feature => $converter;
            }
        }
    }

    /**
     * Iterate over all the Checks.
     *
     * @return \Traversable<Feature, array{class-string, non-empty-string}>
     */
    public function checks(): \Traversable
    {
        foreach ($this->features as $feature) {
            foreach ($feature->checks as $check) {
                yield $feature => $check;
            }
        }
    }

    /**
     * @param class-string<PayloadConverterInterface> $class
     */
    public function addConverter(\Temporal\Tests\Acceptance\App\Input\Feature $inputFeature, string $class): void
    {
        $this->getFeature($inputFeature)->converters[] = $class;
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    public function addCheck(\Temporal\Tests\Acceptance\App\Input\Feature $inputFeature, string $class, string $method): void
    {
        $this->getFeature($inputFeature)->checks[] = [$class, $method];
    }

    /**
     * @param class-string $class
     */
    public function addWorkflow(\Temporal\Tests\Acceptance\App\Input\Feature $inputFeature, string $class): void
    {
        $this->getFeature($inputFeature)->workflows[] = $class;
    }

    /**
     * @param class-string $class
     */
    public function addActivity(\Temporal\Tests\Acceptance\App\Input\Feature $inputFeature, string $class): void
    {
        $this->getFeature($inputFeature)->activities[] = $class;
    }

    private function getFeature(\Temporal\Tests\Acceptance\App\Input\Feature $feature): Feature
    {
        return $this->features[$feature->testClass] ??= new Feature($feature->taskQueue);
    }

    /**
     * @param class-string $class
     */
    public function getFeatureByTestCase(string $class): ?Feature
    {
        return $this->features[$class] ?? null;
    }
}
