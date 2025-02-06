<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

final class UpsertSearchAttributes extends Request
{
    public const NAME = 'UpsertWorkflowSearchAttributes';

    /**
     * @param array<string, mixed> $searchAttributes
     */
    public function __construct(
        private readonly array $searchAttributes,
    ) {
        parent::__construct(self::NAME, ['searchAttributes' => (object) $searchAttributes]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchAttributes(): array
    {
        return $this->searchAttributes;
    }
}
