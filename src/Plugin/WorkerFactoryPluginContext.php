<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

use Temporal\DataConverter\DataConverterInterface;

/**
 * Builder-style configuration context for worker factory plugins.
 *
 * Plugins modify this builder in {@see WorkerPluginInterface::configureWorkerFactory()}.
 * Uses a fluent API similar to Java SDK's Options.Builder pattern.
 */
final class WorkerFactoryPluginContext
{
    public function __construct(
        private ?DataConverterInterface $dataConverter = null,
    ) {}

    public function getDataConverter(): ?DataConverterInterface
    {
        return $this->dataConverter;
    }

    public function setDataConverter(?DataConverterInterface $dataConverter): self
    {
        $this->dataConverter = $dataConverter;
        return $this;
    }
}
