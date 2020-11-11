<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Prototype;

use Temporal\Client\Activity\Meta\ActivityInterface;
use Temporal\Client\Activity\Meta\ActivityMethod;

final class ActivityPrototype extends Prototype implements ActivityPrototypeInterface
{
    /**
     * @param ActivityInterface $meta
     * @param ActivityMethod $method
     * @param \ReflectionFunctionAbstract $handler
     */
    public function __construct(ActivityInterface $meta, ActivityMethod $method, \ReflectionFunctionAbstract $handler)
    {
        parent::__construct($meta, $method, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(): ActivityInterface
    {
        $result = parent::getMetadata();

        assert($result instanceof ActivityInterface, 'Postcondition failed');

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): ActivityMethod
    {
        $result = parent::getMethod();

        assert($result instanceof ActivityMethod, 'Postcondition failed');

        return $result;
    }
}
