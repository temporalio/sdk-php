<?php

declare(strict_types=1);

namespace Temporal\Worker\Versioning;

use Temporal\Common\VersioningBehavior;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\EnumValueType;
use Temporal\Internal\Traits\CloneWith;

/**
 * Options for configuring the Worker Versioning feature.
 *
 * @internal Experimental.
 */
class WorkerDeploymentOptions
{
    use CloneWith;

    /**
     * If set, opts this worker into the Worker Deployment Versioning feature.
     * It will only operate on workflows it claims to be compatible with.
     */
    #[Marshal(name: 'UseVersioning')]
    private readonly bool $useVersioning;

    #[Marshal(name: 'Version', nullable: true)]
    private readonly ?WorkerDeploymentVersion $version;

    #[Marshal(name: 'DefaultVersioningBehavior', type: EnumValueType::class)]
    private readonly VersioningBehavior $defaultVersioningBehavior;

    private function __construct()
    {
        $this->useVersioning = false;
        $this->version = null;
        $this->defaultVersioningBehavior = VersioningBehavior::Unspecified;
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * If set, opts this worker into the Worker Deployment Versioning feature.
     *
     * It will only operate on workflows it claims to be compatible with.
     * You must also call {@see self::withVersion()} if this flag is true.
     */
    public function withUseVersioning(bool $value): self
    {
        /** @see self::$useVersioning */
        return $this->with('useVersioning', $value);
    }

    /**
     * Sets the version of the worker deployment.
     *
     * @param non-empty-string|WorkerDeploymentVersion $version
     */
    public function withVersion(string|WorkerDeploymentVersion $version): self
    {
        /** @see self::$version */
        return $this->with('version', \is_string($version) ? WorkerDeploymentVersion::fromString($version) : $version);
    }

    /**
     * Sets the default versioning behavior for this worker.
     *
     */
    public function withDefaultVersioningBehavior(VersioningBehavior $behavior): self
    {
        /** @see self::$defaultVersioningBehavior */
        return $this->with('defaultVersioningBehavior', $behavior);
    }
}
