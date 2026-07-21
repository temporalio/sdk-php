<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * Marks non-cancellable requests that must be locally rejected with a CanceledFailure when their scope is cancelled.
 */
interface RejectedOnCancelInterface extends RequestInterface {}
