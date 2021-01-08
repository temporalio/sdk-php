<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Fixtures;

/**
 * Reads the workflow logs to chunk out worklogs and contexts.
 */
class Splitter
{
    /** @var string */
    private string $filename;

    /** @var array */
    private array $in = [];

    /** @var array */
    private array $out = [];

    /**
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->parse();
    }

    /**
     * Returns work pairs for a first workflow.
     *
     * @return array
     */
    public function getQueue(): array
    {
        return [$this->out, $this->in];
    }

    /**
     * Parse log and fetch chunks.
     */
    private function parse()
    {
        $lines = file($this->filename);

        // skip get worker info
        $offset = 2;

        $workflow = null;

        while (isset($lines[$offset])) {
            $line = $lines[$offset];

            if (preg_match('/(?:\[0m\t)(\[.*\])\s*({.*})$/', $line, $matches)) {
                $ctx = json_decode($matches[2], true);
                if (isset($ctx['receive'])) {
                    $this->in[] = [$matches[1], $matches[2]];
                    $offset++;
                    continue;
                }

                $this->out[] = [$matches[1], $matches[2]];
            }

            $offset++;
        }
    }

    /**
     * @return Splitter
     */
    public static function create(string $name)
    {
        return new self(__DIR__ . '/data/' . $name);
    }
}
