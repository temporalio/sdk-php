<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception;


use Google\Protobuf\Any;
use Throwable;

class ClientException extends \RuntimeException
{
    /**
     * @var \stdClass
     */
    private \stdClass $status;

    /**
     * ClientException constructor.
     * @param \stdClass $status
     * @param Throwable|null $previous
     */
    public function __construct(\stdClass $status, Throwable $previous = null)
    {
        parent::__construct($status->details, $status->code, $previous);
        $this->status = $status;
    }

    /**
     * @return \stdClass
     */
    public function getStatus(): \stdClass
    {
        return $this->status;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->status->metadata;
    }

    /**
     * @return string
     */
    public function getDetails(): string
    {
        return $this->status->details;
    }

    /**
     * @return string
     */
    public function getBinaryDetails(): array
    {
        if (!isset($this->status->metadata['grpc-status-details-bin'])) {
            return [];
        }

        return $this->status->metadata['grpc-status-details-bin'];
    }

    /**
     * @param string $class
     * @param string $marker
     * @return object|null
     */
    public function tryFailure(string $class, string $marker): ?object
    {
        $details = $this->getBinaryDetails();
        if (count($details) === 0) {
            return null;
        }

        if (strpos($details[0], $marker) === false) {
            return null;
        }

        $obj = new $class;
        $obj->mergeFromString($details[0]);

        return $obj;
    }
}
