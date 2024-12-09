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

final class GetVersion extends Request
{
    public const NAME = 'GetVersion';

    /**
     * @param positive-int $minSupported
     * @param positive-int $maxSupported
     */
    public function __construct(
        private string $changeId,
        private int $minSupported,
        private int $maxSupported,
    ) {
        parent::__construct(
            self::NAME,
            [
                'changeID' => $changeId,
                'minSupported' => $minSupported,
                'maxSupported' => $maxSupported,
            ],
        );
    }

    public function getChangeId(): string
    {
        return $this->changeId;
    }

    public function getMinSupported(): int
    {
        return $this->minSupported;
    }

    public function getMaxSupported(): int
    {
        return $this->maxSupported;
    }
}
