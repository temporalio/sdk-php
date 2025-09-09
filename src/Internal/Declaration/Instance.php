<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Internal\Destroy\Destroyable;
use Temporal\Exception\InstantiationException;
use Temporal\Internal\Declaration\Prototype\Prototype;

abstract class Instance implements InstanceInterface, Destroyable
{
    private MethodHandler $handler;

    public function __construct(
        Prototype $prototype,
        protected object $context,
    ) {
        $handler = $prototype->getHandler();

        if ($handler === null) {
            throw new InstantiationException(\sprintf(
                'Unable to instantiate "%s" without handler method',
                $prototype->getID(),
            ));
        }

        $this->handler = $this->createHandler($handler);
    }

    public function getContext(): object
    {
        return $this->context;
    }

    public function getHandler(): MethodHandler
    {
        return $this->handler;
    }

    public function destroy(): void
    {
        $this->context instanceof Destroyable and $this->context->destroy();
        unset($this->handler, $this->context);
    }

    protected function createHandler(\ReflectionFunctionAbstract $func): MethodHandler
    {
        return new MethodHandler($this->context, $func);
    }
}
