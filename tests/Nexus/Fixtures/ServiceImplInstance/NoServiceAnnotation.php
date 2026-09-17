<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceImplInstance;

/**
 * Class that does not implement any #[Service]-annotated interface, so the
 * factory cannot derive a contract.
 */
final class NoServiceAnnotation
{
    public function operation(): string
    {
        return '';
    }
}
