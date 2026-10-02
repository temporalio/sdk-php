<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Bridge\CoreEnvironment;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class ChildProcesses
{
    private const STOP_SIGNALS = [\SIGTERM, \SIGINT];
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
        $role = CoreEnvironment::string(CoreEnvironment::ROLE);

        return $role === null ? null : CoreRole::from($role);
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

    private static function canFork(): bool
    {
        return \PHP_OS_FAMILY === 'Linux' && !\extension_loaded('grpc') && !Bridge::started();
    }

    /**
     * @psalm-suppress UndefinedMethod, InvalidReturnType
     */
    private static function exitForked(int $code): never
    {
        \fflush(\STDOUT);
        \fflush(\STDERR);
        \FFI::cdef('void _exit(int status);')->_exit($code);
    }

    /**
     * @psalm-suppress UnusedFunctionCall
     */
    private function fork(CoreRole $role): int
    {
        \pcntl_sigprocmask(\SIG_BLOCK, self::STOP_SIGNALS);
        $pid = \pcntl_fork();
        if ($pid !== 0) {
            \pcntl_sigprocmask(\SIG_UNBLOCK, self::STOP_SIGNALS);
            if ($pid === -1) {
                throw new \RuntimeException(\sprintf('Unable to fork a %s worker process', $role->value));
            }
            return $pid;
        }

        foreach (self::STOP_SIGNALS as $signal) {
            \pcntl_signal($signal, \SIG_DFL);
        }
        \pcntl_sigprocmask(\SIG_UNBLOCK, self::STOP_SIGNALS);
        \register_shutdown_function(static fn() => self::exitForked(1));
        try {
            $code = ($this->serve)($role);
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Worker process (%s) failed: %s', $role->value, $e->getMessage()));
            $code = 1;
        }
        self::exitForked($code);
    }

    private function spawn(CoreRole $role): int
    {
        $process = \proc_open(
            [\PHP_BINARY, ...$this->iniArguments(), \get_included_files()[0], ...\array_slice($_SERVER['argv'] ?? [], 1)],
            [\STDIN, \STDOUT, \STDERR],
            $pipes,
            null,
            [...\getenv(), CoreEnvironment::ROLE => $role->value],
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
     * @psalm-suppress ForbiddenCode
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
        $ini = \ini_get_all(null, false);
        foreach ($ini === false ? [] : $ini as $name => $value) {
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
