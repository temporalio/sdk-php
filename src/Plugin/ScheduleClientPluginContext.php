<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

use Temporal\Client\ClientOptions;
use Temporal\DataConverter\DataConverterInterface;

/**
 * Builder-style configuration context for schedule client plugins.
 *
 * Plugins modify this builder in {@see ScheduleClientPluginInterface::configureScheduleClient()}.
 * Uses a fluent API similar to Java SDK's Options.Builder pattern.
 */
final class ScheduleClientPluginContext
{
    public function __construct(
        private ClientOptions $clientOptions,
        private ?DataConverterInterface $dataConverter = null,
    ) {}

    public function getClientOptions(): ClientOptions
    {
        return $this->clientOptions;
    }

    public function setClientOptions(ClientOptions $clientOptions): self
    {
        $this->clientOptions = $clientOptions;
        return $this;
    }

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
