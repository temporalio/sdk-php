<?php

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Internal\Traits\CloneWith;

/**
 * DTO with credential configuration for connecting RoadRunner to the Temporal service.
 */
final class ServiceCredentials
{
    use CloneWith;

    public readonly string $apiKey;

    private function __construct()
    {
        $this->apiKey = '';
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the authentication token for API calls.
     *
     * To update the API key in runtime, call the `UpdateAPIKey` RPC method with the new key:
     *
     * ```
     *  $result = \Temporal\Worker\Transport\Goridge::create()->call(
     *      'temporal.UpdateAPIKey',
     *      $newApiKey,
     *  );
     * ```
     *
     * @link https://docs.temporal.io/cloud/api-keys
     * @since SDK 2.12.0
     * @since RoadRunner 2024.3.0
     */
    public function withApiKey(string $key): static
    {
        /** @see self::$apiKey */
        return $this->cloneWith('apiKey', $key);
    }
}
