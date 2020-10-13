<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use App\Server\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\TcpServer;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ServerEmulator implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var \SplObjectStorage
     */
    private \SplObjectStorage $clients;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var ConsoleOutput
     */
    private ConsoleOutput $cli;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array|string[]
     */
    private array $servers = [];

    /**
     * @param LoopInterface|null $loop
     */
    public function __construct(LoopInterface $loop = null)
    {
        $this->clients = new \SplObjectStorage();
        $this->loop = $loop ?? Factory::create();

        $this->cli = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);
        $this->logger = new ConsoleLogger($this->cli);
    }

    /**
     * @param OutputInterface $output
     * @return ProgressIndicator
     */
    private function createProgressIndicator(OutputInterface $output): ProgressIndicator
    {
        $interval = 100;
        $chars = $output->isDecorated() ? ['⣷', '⣯', '⣟', '⡿', '⢿', '⣻', '⣽', '⣾'] : null;

        return new ProgressIndicator($output, null, $interval, $chars);
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = []): void
    {
        if ($this->cli->isDecorated()) {
            $this->cli->write("\x0D\x1B[2K");
        } else{
            $this->cli->writeln('');
        }

        $this->logger->log($level, $message, $context);
    }

    /**
     * @param string|int $uri
     * @return $this
     */
    public function listen($uri): self
    {
        $server = new TcpServer($uri, $this->loop);

        $this->servers[] = $server->getAddress();

        $server->on('connection', function (ConnectionInterface $conn) {
            $this->info($this->format($conn, 'Established'));
            $this->clients->attach($instance = new Connection($conn, $this));

            $conn->on('error', function (\Throwable $e) use ($conn) {
                $this->error($this->format($conn, \get_class($e) . ': ' . $e->getMessage()));
                $conn->close();
            });

            $conn->on('close', function () use ($instance, $conn) {
                $this->notice($this->format($conn, 'Closed'));
                $this->clients->detach($instance);
            });
        });

        return $this;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $progress = $this->createProgressIndicator($this->cli);

        $progress->start('Temporal Server Emulator (' . \implode(', ', $this->servers) . ')');

        $this->loop->addPeriodicTimer(.1, function () use ($progress) {
            $progress->advance();
        });

        $this->loop->run();
    }

    /**
     * @param ConnectionInterface $conn
     * @param string $message
     * @param mixed ...$args
     * @return string
     */
    private function format(ConnectionInterface $conn, string $message, ...$args): string
    {
        $message = \sprintf($message, ...$args);

        return \sprintf('[%s] %s', $conn->getRemoteAddress(), $message);
    }
}
