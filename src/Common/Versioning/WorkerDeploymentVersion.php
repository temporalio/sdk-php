<?php

declare(strict_types=1);

namespace Temporal\Common\Versioning;

use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * @internal Experimental.
 *
 * @see \Temporal\Api\Deployment\V1\WorkerDeploymentVersion
 */
class WorkerDeploymentVersion implements \Stringable
{
    use CloneWith;

    private function __construct(
        /**
         * A unique identifier for this Version within the Deployment it is a part of.
         * Not necessarily unique within the namespace.
         * The combination of {@see $deployment_name} and {@see $buildId} uniquely identifies this
         * Version within the namespace, because Deployment names are unique within a namespace.
         */
        #[Marshal('DeploymentName')]
        public readonly string $deploymentName,

        /**
         * Identifies the Worker Deployment this Version is part of.
         */
        #[Marshal('BuildId')]
        public readonly string $buildId,
    ) {}

    /**
     * Create a new worker deployment version with the given deployment name and build ID.
     *
     * @param non-empty-string $deploymentName The name of the worker deployment. Must not contain a ".".
     * @param non-empty-string $buildId The build ID of the worker deployment.
     *
     * @throws InvalidArgumentException if the deployment name or build ID is empty or invalid.
     */
    public static function new(string $deploymentName, string $buildId): self
    {
        return new self($deploymentName, $buildId);
    }

    /**
     * Build a worker deployment version from a canonical string representation.
     *
     * @param non-empty-string $canonicalString The canonical string representation of the worker deployment version,
     *        formatted as "deploymentName.buildId". Deployment name must not have a "." in it.
     *
     * @throws InvalidArgumentException if the input string is not in the expected format.
     */
    public static function fromString(string $canonicalString): self
    {
        $parts = \explode('.', $canonicalString, 2);
        \count($parts) === 2 or throw new InvalidArgumentException(
            "Invalid canonical string format. Expected 'deploymentName.buildId'",
        );

        return new self($parts[0], $parts[1]);
    }

    /**
     * @return non-empty-string canonical string representation of this worker deployment version.
     */
    public function __toString()
    {
        return "{$this->deploymentName}.{$this->buildId}";
    }
}
