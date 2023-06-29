<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Interceptor\HeaderInterface;
use Temporal\Worker\Transport\Command\Request;

class UpsertSearchAttributes extends Request
{
    public const NAME = 'UpsertWorkflowSearchAttributes';

    /**
     * @param array<string, mixed> $searchAttributes
     */
    public function __construct(
        private array $searchAttributes,
        HeaderInterface $header
    ) {
        parent::__construct(name: self::NAME, options: ['searchAttributes' => $searchAttributes], header: $header);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchAttributes(): array
    {
        return $this->searchAttributes;
    }
}
