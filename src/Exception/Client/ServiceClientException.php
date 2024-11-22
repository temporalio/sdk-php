<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Google\Protobuf\Any;
use Google\Protobuf\Internal\RepeatedField;
use Google\Rpc\Status;

class ServiceClientException extends \RuntimeException
{
    use UnpackDetailsTrait;

    /**
     * @var Status
     */
    private Status $status;

    /**
     * @param \stdClass $status
     * @param \Throwable|null $previous
     * @throws \Exception
     */
    public function __construct(\stdClass $status, \Throwable $previous = null)
    {
        $this->status = new Status();

        if (isset($status->metadata['grpc-status-details-bin'][0])) {
            $this->status->mergeFromString($status->metadata['grpc-status-details-bin'][0]);
        }

        parent::__construct($status->details . " (code: $status->code)", $status->code, $previous);
    }

    /**
     * @return Status
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @return \ArrayAccess<int, Any>&RepeatedField
     */
    public function getDetails(): RepeatedField
    {
        return $this->status->getDetails();
    }
}
