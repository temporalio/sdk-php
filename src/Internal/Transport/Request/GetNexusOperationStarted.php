<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

/**
 * `id` is the original ExecuteNexusOperation message ID.
 *
 * @psalm-immutable
 */
final class GetNexusOperationStarted extends Request
{
    public const NAME = 'GetNexusOperationStarted';

    public function __construct(int $id)
    {
        parent::__construct(self::NAME, ['id' => $id]);
    }
}
