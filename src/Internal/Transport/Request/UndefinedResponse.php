<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Request;

final class UndefinedResponse extends Request
{
    public const NAME = 'UndefinedResponse';

    /**
     * @param non-empty-string $message Error message
     */
    public function __construct(string $message)
    {
        parent::__construct(self::NAME, ['message' => $message]);
    }
}
