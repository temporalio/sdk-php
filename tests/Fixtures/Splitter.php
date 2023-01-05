<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Fixtures;

/**
 * Reads the workflow logs to chunk out worklogs and contexts.
 */
class Splitter
{
    /** @var string[] */
    private array $lines;

    /** @var array */
    private array $in = [];

    /** @var array */
    private array $out = [];

    /**
     * @param string $filename
     */
    public function __construct(array $lines)
    {
        $this->lines = $lines;
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
        // skip get worker info
        $offset = 0;
        while (isset($this->lines[$offset])) {
            $line = $this->lines[$offset];

            if (preg_match('/(?:\[0m\t)(\[.*\])\s*({.*})(?:[\r\n]*)$/', $line, $matches)) {
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
     * Create from file
     *
     * @return Splitter
     */
    public static function create(string $name): self
    {
        return new self(file(__DIR__ . '/data/' . $name));
    }

    /**
     * Create from text block
     *
     * @return Splitter
     */
    public static function createFromString(string $text): self
    {
        return new self(\explode("\n", $text));
    }
}
