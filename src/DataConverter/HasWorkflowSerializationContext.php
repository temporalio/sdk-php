<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

interface HasWorkflowSerializationContext extends SerializationContext
{
    public function getNamespace(): string;

    /**
     * Null for payloads not bound to a workflow (for example a standalone activity).
     */
    public function getWorkflowId(): ?string;
}
