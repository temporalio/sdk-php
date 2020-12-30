<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Command\Request;

final class GetVersion extends Request
{
    /**
     * @var string
     */
    public const NAME = 'GetVersion';

    /**
     * @param string $changeId
     * @param positive-int $minSupported
     * @param positive-int $maxSupported
     */
    public function __construct(string $changeId, int $minSupported, int $maxSupported)
    {
        parent::__construct(self::NAME, [
            'changeID'     => $changeId,
            'minSupported' => $minSupported,
            'maxSupported' => $maxSupported,
        ]);
    }
}
