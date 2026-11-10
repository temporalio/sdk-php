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
interface StorageDriverInterface
{
    public function getName(): string;

    public function getType(): string;

    /**
     * @param list<Payload> $payloads
     * @return list<StorageDriverClaim>
     */
    public function store(StorageDriverStoreContext $context, array $payloads): array;

    /**
     * @param list<StorageDriverClaim> $claims
     * @return list<Payload>
     */
    public function retrieve(StorageDriverRetrieveContext $context, array $claims): array;
}
