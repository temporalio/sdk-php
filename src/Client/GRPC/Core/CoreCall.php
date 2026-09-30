<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Client\GRPC\StatusCode;
use Temporal\Worker\Core\Bridge;

/**
 * @internal
 */
final class CoreCall
{
    /**
     * @param array{class-string<\Google\Protobuf\Internal\Message>, string} $deserialize
     */
    public function __construct(
        private readonly Bridge $bridge,
        private readonly int $tag,
        private readonly array $deserialize,
    ) {}

    /**
     * @return array{\Google\Protobuf\Internal\Message|null, \stdClass}
     * @psalm-suppress UnsafeInstantiation
     */
    public function wait(): array
    {
        [$code, $data] = $this->bridge->awaitCall($this->tag);
        $status = new \stdClass();
        $status->code = $code;
        $status->details = '';
        $status->metadata = [];
        if ($code === StatusCode::OK) {
            $response = new ($this->deserialize[0])();
            $response->mergeFromString($data);

            return [$response, $status];
        }

        $header = \unpack('Vlength', $data);
        $length = \is_array($header) && isset($header['length']) ? (int) $header['length'] : 0;
        $status->details = \substr($data, 4, $length);
        $details = \substr($data, 4 + $length);
        if ($details !== '') {
            $status->metadata['grpc-status-details-bin'] = [$details];
        }

        return [null, $status];
    }
}
