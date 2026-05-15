<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework;

/**
 * Stateless replacement rule for one kind of value found inside a captured
 * Temporal event-history JSON tree (a timestamp, an ID, an identity string, …).
 *
 * `$source` is supplied so individual normalizers can vary per SDK if needed;
 * most don't. The dispatcher in `Sdk\AbstractSdkNormalizer` decides which
 * normalizer handles which JSON key.
 */
interface FieldNormalizerInterface
{
    public function normalize(mixed $value, Source $source): mixed;
}
