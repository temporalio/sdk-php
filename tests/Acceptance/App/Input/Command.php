<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Input;

final class Command
{
    /** @var non-empty-string|null Temporal Namespace */
    public ?string $namespace = null;

    /** @var non-empty-string|null Temporal Address */
    public ?string $address = null;

    // /** @var list<Feature> */
    // public array $features = [];

    /** @var non-empty-string|null */
    public ?string $tlsKey = null;

    /** @var non-empty-string|null */
    public ?string $tlsCert = null;

    public static function fromEnv(): self
    {
        $self = new self();

        $self->namespace = \getenv('TEMPORAL_NAMESPACE') ?: 'default';
        $self->address = \getenv('TEMPORAL_ADDRESS') ?: 'localhost:7233';
        // $self->tlsCert =
        // $self->tlsKey =


        // [$dir, $taskQueue] = \explode(':', $chunk, 2);
        // $self->features[] = new Feature(
        //     dir: $dir,
        //     namespace: 'Harness\\Feature\\' . self::namespaceFromPath($dir),
        //     taskQueue: $taskQueue,
        // );

        return $self;
    }

    /**
     * Used in RR worker
     */
    public static function fromCommandLine(array $argv): self
    {
        $self = new self();

        \array_shift($argv); // remove the script name (worker.php or runner.php)
        foreach ($argv as $chunk) {
            if (\str_starts_with($chunk, 'namespace=')) {
                $self->namespace = \substr($chunk, 10);
                continue;
            }

            if (\str_starts_with($chunk, 'address=')) {
                $self->address = \substr($chunk, 8);
                continue;
            }

            if (\str_starts_with($chunk, 'tls.cert=')) {
                $self->tlsCert = \substr($chunk, 9);
                continue;
            }

            if (\str_starts_with($chunk, 'tls.key=')) {
                $self->tlsKey = \substr($chunk, 8);
                continue;
            }

            // if (!\str_contains($chunk, ':')) {
            //     continue;
            // }
            // [$dir, $taskQueue] = \explode(':', $chunk, 2);
            // $self->features[] = new Feature(
            //     dir: $dir,
            //     namespace: 'Harness\\Feature\\' . self::namespaceFromPath($dir),
            //     taskQueue: $taskQueue,
            // );
        }

        return $self;
    }

    /**
     * @return list<non-empty-string> CLI arguments that can be parsed by `fromCommandLine`
     */
    public function toCommandLineArguments(): array
    {
        $result = [];
        $this->namespace === null or $result[] = "namespace=$this->namespace";
        $this->address === null or $result[] = "address=$this->address";
        $this->tlsCert === null or $result[] = "tls.cert=$this->tlsCert";
        $this->tlsKey === null or $result[] = "tls.key=$this->tlsKey";
        // foreach ($this->features as $feature) {
        //     $result[] = "{$feature->dir}:{$feature->taskQueue}";
        // }

        return $result;
    }

    // private static function namespaceFromPath(string $dir): string
    // {
    //     $normalized = \str_replace('/', '\\', \trim($dir, '/\\')) . '\\';
    //     // snake_case to PascalCase:
    //     return \str_replace('_', '', \ucwords($normalized, '_\\'));
    // }
}
