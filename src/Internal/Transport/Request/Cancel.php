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

/**
 * Cancel internal request.
 *
 * @psalm-immutable
 */
final class Cancel extends Request
{
    public const NAME = 'Cancel';

    /** @var int[] */
    private array $requestIds;

    /**
     * @param int ...$requestId
     */
    public function __construct(int ...$requestId)
    {
        $this->requestIds = $requestId;
        parent::__construct(self::NAME, ['ids' => $requestId]);
    }

    /**
     * @return int[] ID list
     */
    public function getRequestIds(): array
    {
        return $this->requestIds;
    }
}
