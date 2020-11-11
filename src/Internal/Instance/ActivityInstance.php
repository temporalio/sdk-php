<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance;

use Temporal\Client\Activity\Meta\ActivityInterface;
use Temporal\Client\Activity\Meta\ActivityMethod;
use Temporal\Client\Internal\Prototype\ActivityPrototypeInterface;

final class ActivityInstance extends Instance implements ActivityInstanceInterface
{
    /**
     * @param ActivityPrototypeInterface $prototype
     * @param object $context
     */
    public function __construct(ActivityPrototypeInterface $prototype, object $context)
    {
        parent::__construct($prototype, $context);
    }

    /**
     * @return ActivityInterface
     */
    public function getMetadata(): ActivityInterface
    {
        $result = parent::getMetadata();

        assert($result instanceof ActivityInterface, 'Postcondition failed');

        return $result;
    }

    /**
     * @return ActivityMethod
     */
    public function getMethod(): ActivityMethod
    {
        $result = parent::getMethod();

        assert($result instanceof ActivityMethod, 'Postcondition failed');

        return $result;
    }
}
