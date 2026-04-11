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
use Temporal\Internal\Interceptor\Interceptor;

/**
 * Builder-style configuration context for workflow client plugins.
 *
 * Plugins modify this builder in {@see ClientPluginInterface::configureClient()}.
 */
final class ClientPluginContext
{
    /** @var list<Interceptor> */
    private array $interceptors = [];

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

    /**
     * @return list<Interceptor>
     */
    public function getInterceptors(): array
    {
        return $this->interceptors;
    }

    /**
     * @param list<Interceptor> $interceptors
     */
    public function setInterceptors(array $interceptors): self
    {
        $this->interceptors = $interceptors;
        return $this;
    }

    /**
     * Add an interceptor to the client pipeline.
     */
    public function addInterceptor(Interceptor $interceptor): self
    {
        $this->interceptors[] = $interceptor;
        return $this;
    }
}
