<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

/**
 * Indicates that the activity attempt was reset by the user.
 *
 * Catching this exception directly is discouraged and catching
 * the parent class {@see ActivityCompletionException} is recommended instead.
 */
final class ActivityResetException extends ActivityCompletionException {}
