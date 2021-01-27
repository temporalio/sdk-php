<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({ "CLASS" })
 *
 * Note: We can not implement "NamedArgumentConstructorAttribute" interface
 *  on attribute classes without "__construct" method.
 *
 *  Otherwise, we may receive a NPE inside DocParser class, like:
 *   > Call to a member function getParameters() on null
 *   > in vendor/doctrine/annotations/lib/Doctrine/Common/Annotations/DocParser.php:518
 *
 *  Problem is relevant for doctrine/annotations 1.11 or lower on PHP 7.4 or lower.
 *
 * Note #2: We can not comment this problematic piece of code, because
 *  otherwise a second doctrine bug occurs (WTF!111?) due to which the doctrine
 *  cannot correctly read the annotations (like "Annotation") in the class.
 *
 *  Problem is relevant for doctrine/annotations 1.11 or lower on any PHP version.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WorkflowInterface
{
}
