<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

/**
 * @template-covariant MetadataAttribute of object
 * @template-covariant MethodAttribute of object
 *
 * @template-implements DeclarationInterface<MetadataAttribute>
 */
interface HandledDeclarationInterface extends DeclarationInterface
{
    /**
     * @psalm-return MethodAttribute
     *
     * @return object
     */
    public function getMethod(): object;

    /**
     * @return \ReflectionFunctionAbstract
     */
    public function getHandler(): \ReflectionFunctionAbstract;
}
