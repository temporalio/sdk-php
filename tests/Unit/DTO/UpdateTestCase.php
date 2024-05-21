<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\Update\WaitPolicy;
use Temporal\Tests\Unit\AbstractUnit;

class UpdateTestCase extends AbstractUnit
{
    public function testCreateWithDefaults(): void
    {
        $options = UpdateOptions::new('test-update');

        self::assertSame('test-update', $options->updateName);
        self::assertSame(LifecycleStage::StageAccepted, $options->waitPolicy->lifecycleStage);
    }

    public function testCreateWithCustomWaitPolicy(): void
    {
        $options = UpdateOptions::new('test-update', LifecycleStage::StageCompleted);

        self::assertSame('test-update', $options->updateName);
        self::assertSame(LifecycleStage::StageCompleted, $options->waitPolicy->lifecycleStage);
    }

    public function testWithUpdateName(): void
    {
        $options = UpdateOptions::new('test-update');
        $new = $options->withUpdateName('test-update-2');

        self::assertNotSame($options, $new);
        self::assertSame('test-update', $options->updateName);
        self::assertSame('test-update-2', $new->updateName);
    }

    public function testWithWaitPolicy(): void
    {
        $options = UpdateOptions::new('test-update');
        $new = $options->withWaitPolicy(WaitPolicy::new()->withLifecycleStage(LifecycleStage::StageCompleted));

        self::assertNotSame($options, $new);
        self::assertSame(LifecycleStage::StageAccepted, $options->waitPolicy->lifecycleStage);
        self::assertSame(LifecycleStage::StageCompleted, $new->waitPolicy->lifecycleStage);
    }

    public function testWithUpdateId(): void
    {
        $options = UpdateOptions::new('test-update');
        $new = $options->withUpdateId('test-update-id');

        self::assertNotSame($options, $new);
        self::assertNull($options->updateId);
        self::assertSame('test-update-id', $new->updateId);
    }

    public function testWithResultType(): void
    {
        $options = UpdateOptions::new('test-update');
        $new = $options->withResultType('array');

        self::assertNotSame($options, $new);
        self::assertNull($options->resultType);
        self::assertSame('array', $new->resultType);
    }

    public function testWithFirstExecutionRunId(): void
    {
        $options = UpdateOptions::new('test-update');
        $new = $options->withFirstExecutionRunId('test-run-id');

        self::assertNotSame($options, $new);
        self::assertNull($options->firstExecutionRunId);
        self::assertSame('test-run-id', $new->firstExecutionRunId);
    }
}
