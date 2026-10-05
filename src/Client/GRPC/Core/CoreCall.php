<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Client\GRPC\StatusCode;
use Temporal\Internal\Bridge\Bridge;

/**
 * @internal
 */
final class CoreCall
{
    private bool $waited = false;

    /**
     * @param array{class-string<\Google\Protobuf\Internal\Message>, string} $deserialize
     * @param \Closure(int): void $settle
     */
    public function __construct(
        private readonly Bridge $bridge,
        private readonly int $tag,
        private readonly array $deserialize,
        private readonly \Closure $settle,
    ) {}

    /**
     * @return array{\Google\Protobuf\Internal\Message|null, \stdClass}
     * @psalm-suppress UnsafeInstantiation, PossiblyInvalidArrayAccess
     */
    public function wait(): array
    {
        if ($this->waited) {
            throw new \LogicException('The result of this call is already taken');
        }
        $this->waited = true;
        [$grpcCode, $data] = $this->bridge->awaitCall($this->tag);
        ($this->settle)($grpcCode);
        $status = new \stdClass();
        $status->code = $grpcCode;
        $status->details = '';
        $status->metadata = [];
        if ($grpcCode === StatusCode::OK) {
            $response = new ($this->deserialize[0])();
            $response->mergeFromString($data);

            return [$response, $status];
        }

        $length = \unpack('V', $data)[1];
        $status->details = \substr($data, 4, $length);
        $details = \substr($data, 4 + $length);
        if ($details !== '') {
            $status->metadata['grpc-status-details-bin'] = [$details];
        }

        return [null, $status];
    }

    public function __destruct()
    {
        $this->bridge->forgetCall($this->tag);
    }
}
