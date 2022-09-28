<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Request;

class UpsertSearchAttributes extends Request
{
    public const NAME = 'UpsertWorkflowSearchAttributes';

    /**
     * @param array<string, mixed> $searchAttributes
     */
    public function __construct(array $searchAttributes)
    {
        parent::__construct(self::NAME, ['searchAttributes' => $searchAttributes]);
    }
}
