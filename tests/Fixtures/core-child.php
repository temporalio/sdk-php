<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

\file_put_contents($argv[1], (string) \ini_get('memory_limit'));

exit(\getenv('TEMPORAL_CORE_ROLE') === 'activity' ? 3 : 4);
