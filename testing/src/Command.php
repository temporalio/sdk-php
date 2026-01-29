<?php

declare(strict_types=1);

namespace Temporal\Testing;

final class Command
{
    /** @var non-empty-string|null Temporal Namespace */
    public ?string $namespace = null;

    /** @var non-empty-string|null Temporal Address */
    public ?string $address = null;

    /** @var non-empty-string|null */
    public ?string $tlsKey = null;

    /** @var non-empty-string|null */
    public ?string $tlsCert = null;

    private array $xdebug;

    public static function fromEnv(): self
    {
        $self = new self();

        $self->namespace = \getenv('TEMPORAL_NAMESPACE') ?: 'default';
        $self->address = \getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233';
        $self->xdebug = [
            'xdebug.mode' => \ini_get('xdebug.mode'),
            'xdebug.start_with_request' => \ini_get('xdebug.start_with_request'),
            'xdebug.start_upon_error' => \ini_get('xdebug.start_upon_error'),
        ];
        // $self->tlsCert =
        // $self->tlsKey =

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
        }

        return $self;
    }

    /**
     * @return list<non-empty-string> CLI arguments that can be parsed by `fromCommandLine`
     */
    public function getCommandLineArguments(): array
    {
        $result = [];
        $this->namespace === null or $result[] = "namespace=$this->namespace";
        $this->address === null or $result[] = "address=$this->address";
        $this->tlsCert === null or $result[] = "tls.cert=$this->tlsCert";
        $this->tlsKey === null or $result[] = "tls.key=$this->tlsKey";

        return $result;
    }

    public function getPhpBinaryArguments(): array
    {
        $result = [];
        foreach ($this->xdebug as $key => $value) {
            $result[] = "-d$key=$value";
        }
        return $result;
    }
}
