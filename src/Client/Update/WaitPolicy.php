<?php

declare(strict_types=1);

namespace Temporal\Client\Update;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * Specifies to the gRPC server how long the client wants the update-related
 * RPC call to wait before returning control to the caller.
 *
 * @see \Temporal\Api\Update\V1\WaitPolicy
 */
final class WaitPolicy
{
    use CloneWith;

    /**
     * Indicates the update lifecycle stage that the gRPC call should wait for before returning.
     */
    #[Marshal(name: "lifecycle_stage")]
    public readonly LifecycleStage $lifecycleStage;

    private function __construct()
    {
        $this->lifecycleStage = LifecycleStage::StageUnspecified;
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Indicates the update lifecycle stage that the gRPC call should wait for before returning.
     */
    public function withLifecycleStage(LifecycleStage $value): self
    {
        /** @see self::$lifecycleStage */
        return $this->with('lifecycleStage', $value);
    }
}
