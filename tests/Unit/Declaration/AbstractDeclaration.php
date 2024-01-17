<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group declaration
 */
abstract class AbstractDeclaration extends AbstractUnit
{
    /**
     * @return WorkflowReader[][]
     */
    public static function workflowReaderDataProvider(): array
    {
        return [
            AttributeReader::class  => [new WorkflowReader(new AttributeReader())],
            AnnotationReader::class => [new WorkflowReader(new AnnotationReader())],
        ];
    }

    /**
     * @return ActivityReader[][]
     */
    public static function activityReaderDataProvider(): array
    {
        return [
            AttributeReader::class  => [new ActivityReader(new AttributeReader())],
            AnnotationReader::class => [new ActivityReader(new AnnotationReader())],
        ];
    }
}


