<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow\Attribute;

use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Search attributes key-value pairs that will be attached to the workflow.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class SearchAttributes
{
    public function __construct(
        public readonly array $values,
    ) {}
}
