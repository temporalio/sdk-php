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
 * Optional summary of the workflow.
 *
 * Single-line fixed summary for this workflow that will appear in UI/CLI.
 * This can be in single-line Temporal Markdown format.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class Summary
{
    public function __construct(
        public readonly string $text,
    ) {
        if ($text === '') {
            throw new \InvalidArgumentException('Summary text must not be empty.');
        }
    }
}
