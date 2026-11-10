<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter\ExternalStorage;

use Temporal\Api\Common\V1\Payload;

/**
 * @experimental This API is experimental and may change in the future.
 */
final class ExternalStorage
{
    public const DEFAULT_PAYLOAD_SIZE_THRESHOLD = 256 * 1024;
    public const DEFAULT_MAX_OPERATIONS_PER_MESSAGE = 8;
    public const DEFAULT_MAX_DRIVER_OPERATIONS = 64;

    /**
     * @var non-empty-array<string, StorageDriverInterface>
     */
    public readonly array $drivers;

    /**
     * @var \Closure(StorageDriverSelectContext, Payload): ?StorageDriverInterface
     */
    public readonly \Closure $driverSelector;

    /**
     * @param list<StorageDriverInterface> $drivers
     * @param null|\Closure(StorageDriverSelectContext, Payload): ?StorageDriverInterface $driverSelector
     */
    public function __construct(
        array $drivers,
        ?\Closure $driverSelector = null,
        public readonly int $payloadSizeThreshold = self::DEFAULT_PAYLOAD_SIZE_THRESHOLD,
        public readonly int $maxOperationsPerMessage = self::DEFAULT_MAX_OPERATIONS_PER_MESSAGE,
        public readonly int $maxDriverOperations = self::DEFAULT_MAX_DRIVER_OPERATIONS,
    ) {
        if ($drivers === []) {
            throw new \InvalidArgumentException('At least one storage driver must be provided.');
        }

        if ($payloadSizeThreshold < 0) {
            throw new \InvalidArgumentException('`payloadSizeThreshold` must be greater than or equal to zero.');
        }

        if ($maxOperationsPerMessage < 1) {
            throw new \InvalidArgumentException('`maxOperationsPerMessage` must be a positive integer.');
        }

        if ($maxDriverOperations < 1) {
            throw new \InvalidArgumentException('`maxDriverOperations` must be a positive integer.');
        }

        if ($driverSelector === null && \count($drivers) > 1) {
            throw new \InvalidArgumentException('`driverSelector` must be specified when more than one driver is registered.');
        }

        $byName = [];
        foreach ($drivers as $driver) {
            $name = $driver->getName();
            if ($name === '') {
                throw new \InvalidArgumentException('A storage driver name must not be empty.');
            }

            if (isset($byName[$name])) {
                throw new \InvalidArgumentException("Multiple storage drivers registered with name '$name'.");
            }

            $byName[$name] = $driver;
        }

        $this->drivers = $byName;
        $this->driverSelector = $driverSelector ?? static fn(): StorageDriverInterface => $drivers[0];
    }
}
