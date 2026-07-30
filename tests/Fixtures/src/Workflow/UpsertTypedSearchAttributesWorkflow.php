<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class UpsertTypedSearchAttributesWorkflow
{
    #[WorkflowMethod]
    public function handler(string $keyword = 'CustomValue')
    {
        Workflow::upsertTypedSearchAttributes(
            SearchAttributeKey::forKeyword('CustomKeyword')->valueSet($keyword),
            SearchAttributeKey::forInteger('CustomInt')->valueSet(42),
            SearchAttributeKey::forKeyword('index')->valueSet('idx'),
            SearchAttributeKey::forKeyword('2024')->valueSet('year'),
        );

        return 'done';
    }
}
