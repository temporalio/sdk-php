<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Failure\FailureConverter;

class InvocationFailure
{
    private ?string $failureJson = null;

    /**
     * @param class-string<\Throwable> $errorClass
     */
    public function __construct(
        public string $errorClass,
        public string $errorMessage,
    ) {}

    public static function fromThrowable(\Throwable $error, ?DataConverterInterface $dataConverter = null): static
    {
        /** @psalm-suppress UnsafeInstantiation */
        $self = new static($error::class, $error->getMessage());

        if ($dataConverter !== null) {
            $self->failureJson = FailureConverter::mapExceptionToFailure($error, $dataConverter)
                ->serializeToJsonString();
        }

        return $self;
    }

    public function toThrowable(?DataConverterInterface $dataConverter = null): \Throwable
    {
        if ($this->failureJson !== null && $dataConverter !== null) {
            $failure = new Failure();
            $failure->mergeFromJsonString($this->failureJson);

            return FailureConverter::mapFailureToException($failure, $dataConverter);
        }

        return new ($this->errorClass)($this->errorMessage);
    }

    public function __serialize(): array
    {
        return [
            'errorClass' => $this->errorClass,
            'errorMessage' => $this->errorMessage,
            'failureJson' => $this->failureJson,
        ];
    }

    /**
     * @param array{errorClass: class-string<\Throwable>, errorMessage: string, failureJson?: string|null} $data
     */
    public function __unserialize(array $data): void
    {
        $this->errorClass = $data['errorClass'];
        $this->errorMessage = $data['errorMessage'];
        $this->failureJson = $data['failureJson'] ?? null;
    }
}
