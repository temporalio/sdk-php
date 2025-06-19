<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Google\Protobuf\RepeatedField;
use Google\Rpc\Status;

class ServiceClientException extends \RuntimeException
{
    use UnpackDetailsTrait;

    private Status $status;

    /**
     * @throws \Exception
     */
    public function __construct(\stdClass $status, ?\Throwable $previous = null)
    {
        $this->status = new Status();

        if (isset($status->metadata['grpc-status-details-bin'][0])) {
            $this->status->mergeFromString($status->metadata['grpc-status-details-bin'][0]);
        }

        parent::__construct(\sprintf(
            "%s (code: %d)",
            isset($status->details) ? (string) $status->details : '',
            $status->code,
        ), $status->code, $previous);
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @return RepeatedField
     */
    public function getDetails(): \ArrayAccess&\Countable&\IteratorAggregate
    {
        return $this->status->getDetails();
    }
}
