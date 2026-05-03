<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Attribute;

use Temporal\Nexus\OperationInfo;

/**
 * Marks a method on a {@see Service}-annotated interface as an asynchronous Nexus operation.
 *
 * The annotated method must declare its return type as {@see OperationInfo}. Because the
 * return type does not carry the eventual wire output, declare it via {@see self::$output}
 * (a fully-qualified type name or `'void'` if there is no payload).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class AsyncOperation
{
    /**
     * @param string $name Operation name as exposed over the wire. Empty means "use the method name".
     * @param string $output Wire output type the async operation eventually produces. Empty means "void".
     */
    public function __construct(
        public readonly string $name = '',
        public readonly string $output = '',
    ) {}
}
