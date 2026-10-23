<?php

declare(strict_types=1);

namespace Grpc;

class CallCredentials
{
    private function __construct(private readonly \Closure $callback) {}

    public static function createFromPlugin(callable $callback): self
    {
        return new self($callback(...));
    }

    /**
     * @internal
     * @return array<string, list<string>>
     */
    public function metadata(string $serviceUrl, string $methodName): array
    {
        $context = new \stdClass();
        $context->service_url = $serviceUrl;
        $context->method_name = $methodName;

        return ($this->callback)($context);
    }
}
