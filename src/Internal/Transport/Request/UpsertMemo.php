<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

final class UpsertMemo extends Request
{
    public const NAME = 'UpsertMemo';

    /**
     * @param array<string, mixed> $memo
     */
    public function __construct(
        private readonly array $memo,
    ) {
        parent::__construct(self::NAME, ['memo' => (object) $memo]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMemo(): array
    {
        return $this->memo;
    }
}
