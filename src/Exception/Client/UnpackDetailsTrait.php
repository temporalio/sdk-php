<?php

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Google\Protobuf\Any;
use Google\Protobuf\Internal\RepeatedField;
use GPBMetadata\Temporal\Api\Errordetails\V1\Message;

/**
 * @internal
 */
trait UnpackDetailsTrait
{
    /**
     * @link https://dev.to/khepin/grpc-advanced-error-handling-from-go-to-php-1omc
     *
     * @template T
     * @param class-string<T> $class
     * @return T|null
     * @throws \Exception
     */
    public function getFailure(string $class): ?object
    {
        $details = $this->getDetails();
        if ($details->count() === 0) {
            return null;
        }

        // ensures that message descriptor was added to the pool
        Message::initOnce();

        /** @var Any $detail */
        foreach ($details as $detail) {
            if ($detail->is($class)) {
                return $detail->unpack();
            }
        }

        return null;
    }

    /**
     * @return \ArrayAccess<int, Any>&RepeatedField
     */
    abstract private function getDetails(): iterable;
}
