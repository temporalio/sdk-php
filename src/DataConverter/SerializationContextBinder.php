<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

final class SerializationContextBinder
{
    public static function bind(
        DataConverterInterface $converter,
        ?SerializationContext $context,
    ): DataConverterInterface {
        if ($context === null) {
            return $converter;
        }

        if (!$converter instanceof SerializationContextAwareInterface) {
            return $converter;
        }

        return $converter->withSerializationContext($context);
    }
}
