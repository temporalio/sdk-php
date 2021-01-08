<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Request;

final class GetVersion extends Request
{
    public const NAME = 'GetVersion';

    /**
     * @param string $changeID
     * @param positive-int $minSupported
     * @param positive-int $maxSupported
     */
    public function __construct(string $changeID, int $minSupported, int $maxSupported)
    {
        parent::__construct(
            self::NAME,
            [
                'changeID' => $changeID,
                'minSupported' => $minSupported,
                'maxSupported' => $maxSupported,
            ]
        );
    }
}
