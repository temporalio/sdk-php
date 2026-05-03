<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Service;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

#[Service]
interface UntypedInputServiceInterface
{
    /** @param mixed $input */
    #[Operation]
    public function operation($input): string;
}
