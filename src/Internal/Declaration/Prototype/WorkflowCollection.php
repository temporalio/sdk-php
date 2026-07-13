<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Repository\Identifiable;

/**
 * @template-extends ArrayRepository<WorkflowPrototype>
 */
final class WorkflowCollection extends ArrayRepository
{
    /**
     * A dynamic (catch-all) workflow is the single fallback used when no
     * statically registered workflow matches the requested type. As in the
     * other SDKs (Go panics, Python raises), at most one may be registered per
     * worker — a second would make dispatch ambiguous.
     */
    public function add(Identifiable $entry, bool $overwrite = false): void
    {
        if ($entry instanceof WorkflowPrototype && $entry->isDynamic()) {
            foreach ($this as $existing) {
                if ($existing instanceof WorkflowPrototype
                    && $existing->isDynamic()
                    && $existing->getID() !== $entry->getID()
                ) {
                    throw new \LogicException(\sprintf(
                        'Cannot register dynamic workflow "%s": a dynamic (catch-all) workflow "%s" is '
                        . 'already registered. At most one dynamic workflow is allowed per worker.',
                        $entry->getID(),
                        $existing->getID(),
                    ));
                }
            }
        }

        parent::add($entry, $overwrite);
    }
}
