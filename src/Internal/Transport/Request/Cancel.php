<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Request;

use Temporal\Client\Worker\Command\Request;

final class Cancel extends Request
{
    /**
     * @var string
     */
    public const NAME = 'Cancel';

    /**
     * @param array<int> $requestIdentifiers
     */
    public function __construct(array $requestIdentifiers)
    {
        parent::__construct(self::NAME, [
            'ids' => $requestIdentifiers,
        ]);
    }
}
