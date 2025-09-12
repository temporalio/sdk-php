<?php

declare(strict_types=1);

namespace Temporal;

use function React\Promise\set_rejection_handler;

if (\function_exists('\React\Promise\set_rejection_handler')) {
    set_rejection_handler(null);
}
