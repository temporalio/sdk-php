<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Exception;

use Temporal\Internal\Transport\Request\UndefinedResponse;

/**
 * The exception is converted into {@see UndefinedResponse} and sent to the client.
 * This kind of failure raises panic in the Temporal Worker on the RoadRunner side.
 *
 * @internal
 */
final class UndefinedRequestException extends \LogicException {}
