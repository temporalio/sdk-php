<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Command;

use Temporal\Client\Transport\Protocol\Command\Request;

final class GetVersion extends Request
{
    /**
     * @var string
     */
    public const NAME = 'GetVersion';

    /**
     * @param string $changeID
     * @param int    $minSupported
     * @param int    $maxSupported
     */
    public function __construct(string $changeID, int $minSupported, int $maxSupported)
    {
        parent::__construct(self::NAME, [
            'changeID'     => $changeID,
            'minSupported' => $minSupported,
            'maxSupported' => $maxSupported,
        ]);
    }
}
