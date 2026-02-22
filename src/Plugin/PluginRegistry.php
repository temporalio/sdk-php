<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

/**
 * Manages a collection of plugins with deduplication.
 *
 * Plugins are deduplicated by their {@see ClientPluginInterface::getName()} or
 * {@see WorkerPluginInterface::getName()} value. When a duplicate is detected,
 * an exception is thrown.
 *
 * @psalm-type TPlugin = (ClientPluginInterface|ScheduleClientPluginInterface|WorkerPluginInterface)
 * @internal
 */
final class PluginRegistry
{
    /** @var array<string, TPlugin> */
    private array $plugins = [];

    /**
     * @param iterable<TPlugin> $plugins
     */
    public function __construct(iterable $plugins = [])
    {
        foreach ($plugins as $plugin) {
            $this->add($plugin);
        }
    }

    public function add(ClientPluginInterface|ScheduleClientPluginInterface|WorkerPluginInterface $plugin): void
    {
        $name = $plugin->getName();
        if (isset($this->plugins[$name])) {
            throw new \RuntimeException(\sprintf(
                'Duplicate plugin "%s": a plugin with this name is already registered.',
                $name,
            ));
        }
        $this->plugins[$name] = $plugin;
    }

    /**
     * Merge another set of plugins. Throws on duplicate names.
     *
     * @param iterable<TPlugin> $plugins
     */
    public function merge(iterable $plugins): void
    {
        foreach ($plugins as $plugin) {
            $this->add($plugin);
        }
    }

    /**
     * Get all plugins implementing a given interface.
     *
     * @template T of TPlugin
     * @param class-string<T> $interface
     * @return list<T>
     */
    public function getPlugins(string $interface): array
    {
        $result = [];
        foreach ($this->plugins as $plugin) {
            if ($plugin instanceof $interface) {
                $result[] = $plugin;
            }
        }
        return $result;
    }
}
