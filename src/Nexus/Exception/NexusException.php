<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

/**
 * Root exception for all errors thrown by the Nexus RPC SDK.
 *
 * Catch this to intercept any SDK-originated error without coupling to specific subclasses.
 */
class NexusException extends \RuntimeException {}
