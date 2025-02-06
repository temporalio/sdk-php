<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueSet;
use Temporal\Worker\Transport\Command\Client\Request;

final class UpsertTypedSearchAttributes extends Request
{
    public const NAME = 'UpsertWorkflowTypedSearchAttributes';

    /**
     * @param array<array-key, SearchAttributeUpdate> $searchAttributes
     */
    public function __construct(
        private readonly array $searchAttributes,
    ) {
        parent::__construct(self::NAME, ['search_attributes' => (object) $this->prepareSearchAttributes()]);
    }

    /**
     * @return array<array-key, SearchAttributeUpdate>
     */
    public function getSearchAttributes(): array
    {
        return $this->searchAttributes;
    }

    private function prepareSearchAttributes(): array
    {
        $result = [];
        foreach ($this->searchAttributes as $attr) {
            $result[$attr->name] = $attr instanceof ValueSet
                ? [
                    'type' => $attr->type->value,
                    'operation' => 'set',
                    'value' => match (true) {
                        $attr->value instanceof \DateTimeInterface => $attr->value->format(\DateTimeInterface::RFC3339),
                        default => $attr->value,
                    },
                ]
                : [
                    'type' => $attr->type->value,
                    'operation' => 'unset',
                ];
        }

        return $result;
    }
}
