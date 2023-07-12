<?php

declare(strict_types=1);

namespace Temporal\Testing;

final class Environments
{
    /** @var Environment[] */
    private array $environments = [];

    public function addEnvironment(Environment $environment): void
    {
        $this->environments[] = $environment;
    }

    public function stop(): void
    {
        foreach ($this->environments as $environment) {
            $environment->stop();
        }
    }
}
