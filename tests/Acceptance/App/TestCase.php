<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App;

use Spiral\Core\Container;
use Spiral\Core\Scope;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\Runtime\State;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var State $state */
        $state = ContainerFacade::$container->get(State::class);
        $state->countFeatures() === 0 and RuntimeBuilder::hydrateClasses($state);
    }

    protected function runTest(): mixed
    {
        $c = ContainerFacade::$container;
        /** @var State $runtime */
        $runtime = $c->get(State::class);

        return $c->runScope(
            new Scope(name: 'feature',bindings: [
                Feature::class => $runtime->getFeatureByTestCase(static::class),
                static::class => $this,
                State::class => $runtime,
            ]),
            function (Container $container) {
                $reflection = new \ReflectionMethod($this, $this->name());
                $args = $container->resolveArguments($reflection);
                $this->setDependencyInput($args);

                try {
                    return parent::runTest();
                } catch (\Throwable $e) {
                    // Restart RR if a Error occurs
                    /** @var RRStarter $runner */
                    $runner = $container->get(RRStarter::class);
                    $runner->stop();
                    $runner->start();

                    throw $e;
                } finally {
                    // Cleanup: terminate injected workflow if any
                    foreach ($args as $arg) {
                        if ($arg instanceof WorkflowStubInterface) {
                            try {
                                $arg->terminate('test-end');
                            } catch (\Throwable $e) {
                                // ignore
                            }
                        }
                    }
                }
            },
        );
    }
}
