<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow\Update;

use Temporal\Api\Update\V1\UpdateRef;
use Temporal\DataConverter\ValuesInterface;

final class StartUpdateOutput
{
    public function __construct(
        public UpdateRef $reference,
        public readonly bool $hasResult,
        public readonly ?ValuesInterface $result,
    ) {
    }
}
