<?php


namespace Temporal\Tests\Fixtures;


use Temporal\Worker\Transport\Command\Command;

class CommandResetter extends Command
{
    public static function reset()
    {
        self::$lastID = 9000;
    }
}
