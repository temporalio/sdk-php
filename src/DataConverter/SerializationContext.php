<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

/**
 * Marker for the context in which a payload is serialized or deserialized.
 *
 * A converter or codec that implements {@see SerializationContextAwareInterface} receives the
 * same context instance for a value and for the {@see \Temporal\Api\Common\V1\Payload} it
 * produces, so it can sign or encrypt the payload using details such as the workflow id.
 */
interface SerializationContext {}
