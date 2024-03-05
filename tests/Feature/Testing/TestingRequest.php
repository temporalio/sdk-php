<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Feature\Testing;

use PHPUnit\Framework\Assert;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\Payload;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * @template-extends TestingCommand<RequestInterface>
 */
class TestingRequest extends TestingCommand implements RequestInterface
{
    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        parent::__construct($request);
    }

    /**
     * @param string $expected
     * @param string $message
     * @return $this
     */
    public function assertName(string $expected, string $message = ''): self
    {
        Assert::assertSame($expected, $this->getName(), $message);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->command->getName();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getParam(string $key)
    {
        return $this->getArrayItem($this->getOptions(), $key);
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): array
    {
        return $this->command->getOptions();
    }

    public function getPayloads(): array
    {
        return $this->command->getPayloads();
    }

    /**
     * @param string $expected
     * @param string $message
     * @return $this
     */
    public function assertParamsSame(array $expected, string $message = ''): self
    {
        Assert::assertSame($expected, $this->getOptions(), $message);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $expected
     * @param string $message
     * @return $this
     */
    public function assertParamsKeySame(string $key, $expected, string $message = ''): self
    {
        if ($expected === null) {
            $this->assertParamsHasKey($key, $message);
        }

        Assert::assertEquals($expected, $this->getArrayItem($this->getOptions(), $key), $message);

        return $this;
    }

    /**
     * @param string $expected
     * @param string $message
     * @return $this
     */
    public function assertParamsHasKey(string $key, string $message = ''): self
    {
        Assert::assertTrue(array_key_exists($key, $this->getOptions()), $message);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $expected
     * @param string $message
     * @return $this
     */
    public function assertParamsKeySamePayload(string $key, $expected, string $message = ''): self
    {
        if ($expected === null) {
            $this->assertParamsHasKey($key, $message);
        }

        if (is_array($expected)) {
            $expected = array_map([$this, 'convertValue'], $expected);
        } else {
            $expected = $this->convertValue($expected);
        }

        Assert::assertEquals($expected, $this->getArrayItem($this->getOptions(), $key), $message);

        return $this;
    }

    private function convertValue($value): Payload
    {
        $dc = DataConverter::createDefault();

        return $dc->toPayloads([$value])[0];
    }

    /**
     * @param string $key
     * @param class-string $expected
     * @param string $message
     * @return $this
     */
    public function assertParamsKeyInstanceOf(string $key, string $expected, string $message = ''): self
    {
        Assert::assertInstanceOf($expected, $this->getArrayItem($this->getOptions(), $key), $message);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $expected
     * @param string $message
     * @return $this
     */
    public function assertParamsKeyNotSame(string $key, $expected, string $message = ''): self
    {
        $this->assertParamsHasKey($key, $message);

        Assert::assertNotSame($expected, $this->getArrayItem($this->getOptions(), $key), $message);

        return $this;
    }

    public function isCancellable(): bool
    {
        return true;
    }

    private function getArrayItem(array $array, string $key): mixed
    {
        return $array[$key] ?? null;
    }
}
