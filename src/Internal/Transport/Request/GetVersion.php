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

final class GetVersion extends Request
{
    public const NAME = 'GetVersion';

    /**
     * @param string $changeId
     * @param positive-int $minSupported
     * @param positive-int $maxSupported
     */
    public function __construct(
        private string $changeId,
        private int $minSupported,
        private int $maxSupported
    ) {
        parent::__construct(
            self::NAME,
            [
                'changeID' => $changeId,
                'minSupported' => $minSupported,
                'maxSupported' => $maxSupported,
            ]
        );
    }

    /**
     * @return string
     */
    public function getChangeId(): string
    {
        return $this->changeId;
    }

    /**
     * @return int
     */
    public function getMinSupported(): int
    {
        return $this->minSupported;
    }

    /**
     * @return int
     */
    public function getMaxSupported(): int
    {
        return $this->maxSupported;
    }
}
