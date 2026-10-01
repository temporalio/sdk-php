<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class ChildProcesses
{
    private const ENV_ROLE = 'TEMPORAL_CORE_ROLE';
    private const INI_PROBE = 'echo json_encode([ini_get_all(null, false), get_loaded_extensions(), get_loaded_extensions(true)]);';

    /** @var array<int, resource> */
    private array $processes = [];

    /** @var list<string>|null */
    private ?array $iniArguments = null;

    /**
     * @param \Closure(CoreRole): int $serve
     */
    public function __construct(
        private readonly \Closure $serve,
        private readonly LoggerInterface $logger,
    ) {}

    public static function currentRole(): ?CoreRole
    {
        $role = \getenv(self::ENV_ROLE);

        return \is_string($role) && $role !== '' ? CoreRole::from($role) : null;
    }

    public static function canFork(): bool
    {
        return \PHP_OS_FAMILY === 'Linux' && !\extension_loaded('grpc') && !Bridge::started();
    }

    public function start(CoreRole $role): int
    {
        return self::canFork() ? $this->fork($role) : $this->spawn($role);
    }

    public function release(int $pid): void
    {
        if (isset($this->processes[$pid])) {
            \proc_close($this->processes[$pid]);
            unset($this->processes[$pid]);
        }
    }

    private static function exitForked(int $code): never
    {
        \fflush(\STDOUT);
        \fflush(\STDERR);
        \FFI::cdef('void _exit(int status);')->_exit($code);
    }

    private function fork(CoreRole $role): int
    {
        $pid = \pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException(\sprintf('Unable to fork a %s worker process', $role->value));
        }
        if ($pid > 0) {
            return $pid;
        }

        \pcntl_signal(\SIGTERM, \SIG_DFL);
        \pcntl_signal(\SIGINT, \SIG_DFL);
        \register_shutdown_function(static fn() => self::exitForked(1));
        self::exitForked(($this->serve)($role));
    }

    private function spawn(CoreRole $role): int
    {
        $process = \proc_open(
            [\PHP_BINARY, ...$this->iniArguments(), \get_included_files()[0], ...\array_slice($_SERVER['argv'] ?? [], 1)],
            [\STDIN, \STDOUT, \STDERR],
            $pipes,
            null,
            [...\getenv(), self::ENV_ROLE => $role->value],
        );
        if ($process === false) {
            throw new \RuntimeException(\sprintf('Unable to start a %s worker process', $role->value));
        }
        $pid = \proc_get_status($process)['pid'];
        $this->processes[$pid] = $process;

        return $pid;
    }

    /**
     * @return list<string>
     */
    private function iniArguments(): array
    {
        if ($this->iniArguments !== null) {
            return $this->iniArguments;
        }

        $noIni = \php_ini_loaded_file() === false && \php_ini_scanned_files() === false ? ['-n'] : [];
        $probe = \json_decode(
            (string) \shell_exec(\implode(' ', \array_map(\escapeshellarg(...), [\PHP_BINARY, ...$noIni, '-r', self::INI_PROBE]))),
            true,
        );
        if (!\is_array($probe)) {
            $this->logger->warning('Unable to read the default ini of the PHP binary, the worker processes get every ini value of this process');
            $probe = [[], [], []];
        }
        [$defaults, $extensions, $zendExtensions] = $probe;

        $arguments = $noIni;
        foreach (\array_diff(\get_loaded_extensions(), $extensions) as $extension) {
            \array_push($arguments, '-d', 'extension=' . $this->extensionFile($extension));
        }
        foreach (\array_diff(\get_loaded_extensions(true), $zendExtensions) as $extension) {
            \array_push($arguments, '-d', 'zend_extension=' . $this->extensionFile($extension));
        }
        foreach (\ini_get_all(null, false) ?: [] as $name => $value) {
            if ($value !== null && ($defaults[$name] ?? null) !== $value) {
                \array_push($arguments, '-d', $name . '="' . \addcslashes((string) $value, '"\\') . '"');
            }
        }

        return $this->iniArguments = $arguments;
    }

    private function extensionFile(string $name): string
    {
        $file = \strtolower((string) \preg_replace('/^Zend\s+/i', '', $name));
        if (!\is_file((string) \ini_get('extension_dir') . \DIRECTORY_SEPARATOR . $file . '.' . \PHP_SHLIB_SUFFIX)) {
            $this->logger->warning(\sprintf('The worker processes may not load the extension "%s": %s is not in extension_dir', $name, $file));
        }

        return $file;
    }
}
