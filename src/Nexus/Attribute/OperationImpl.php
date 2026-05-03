<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Attribute;

use Temporal\Nexus\Handler\OperationHandlerInterface;

/**
 * Marks a factory method on a {@see ServiceImpl}-annotated class as the handler for a Nexus operation.
 *
 * The annotated method must be public, non-static, take no parameters, and return an
 * {@see OperationHandlerInterface}. The method name must match the corresponding operation
 * method on the service interface.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class OperationImpl {}
