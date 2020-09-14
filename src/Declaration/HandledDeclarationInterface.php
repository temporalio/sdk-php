<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Declaration;

interface HandledDeclarationInterface extends DeclarationInterface
{
    /**
     * @var int
     */
    public const MODE_AUTO = 0x00;

    /**
     * @var int
     */
    public const MODE_MANUAL = 0x01;

    /**
     * @var int
     */
    public const MODE_GENERATORS = 0x02;

    /**
     * @return int
     */
    public function getHandlerMode(): int;

    /**
     * @return callable
     */
    public function getHandler(): callable;

    /**
     * @return \ReflectionFunctionAbstract
     * @throws \ReflectionException
     */
    public function getReflectionHandler(): \ReflectionFunctionAbstract;
}
