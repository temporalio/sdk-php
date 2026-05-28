<?php

declare(strict_types=1);

namespace Temporal\Testing;

final class Command
{
    /** @var non-empty-string|null Temporal Namespace */
    public ?string $namespace = null;

    /** @var non-empty-string|null Temporal Address */
    public ?string $address = null;

    public ?string $tlsKey = null;
    public ?string $tlsCert = null;
    private array $xdebug = [];

    /**
     * @param non-empty-string|null $address
     */
    public function __construct(
        ?string $address = null,
    ) {
        $this->address = $address;
    }

    public static function fromEnv(): self
    {
        $address = \getenv('TEMPORAL_ADDRESS');
        $self = new self((\is_string($address) && $address !== '') ? $address : '127.0.0.1:7233');

        $namespace = \getenv('TEMPORAL_NAMESPACE');
        $self->namespace = (\is_string($namespace) && $namespace !== '') ? $namespace : 'default';
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
        $address = '';
        $namespace = '';
        $tlsCert = null;
        $tlsKey = null;

        // remove the script name (worker.php or runner.php)
        $chunks = \array_slice($argv, 1);
        foreach ($chunks as $chunk) {
            switch (true) {
                case \str_starts_with($chunk, 'namespace='):
                    $namespace = \substr($chunk, 10);
                    break;
                case \str_starts_with($chunk, 'address='):
                    $address = \substr($chunk, 8);
                    break;
                case \str_starts_with($chunk, 'tls.cert='):
                    $tlsCert = \substr($chunk, 9);
                    break;
                case \str_starts_with($chunk, 'tls.key='):
                    $tlsKey = \substr($chunk, 8);
                    break;
            }
        }
        if (empty($address)) {
            throw new \InvalidArgumentException('Address cannot be empty');
        }
        if (empty($namespace)) {
            throw new \InvalidArgumentException('Namespace cannot be empty');
        }

        $self = new self($address);
        $self->namespace = $namespace;
        $self->tlsCert = $tlsCert;
        $self->tlsKey = $tlsKey;
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
