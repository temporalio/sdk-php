<?php

declare(strict_types=1);

namespace Temporal\Tests\Core;

use Spiral\Attributes\AttributeReader;
use Temporal\Api\History\V1\History;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Testing\Replay\HistoryJsonCodec;
use Temporal\Worker\Core\CoreOptions;
use Temporal\Worker\Core\CoreRole;
use Temporal\Worker\Core\CoreWorkerConfig;
use Temporal\Worker\Core\CoreWorkerFactory;

final class Replayers
{
    private const HISTORY = __DIR__ . '/../Fixtures/history/squence-workflow-damaged.json';

    public static function history(int $lastEventId): History
    {
        return HistoryJsonCodec::decode((string) \file_get_contents(self::HISTORY), $lastEventId);
    }

    public static function config(): array
    {
        $config = new CoreWorkerConfig(CoreOptions::create(null, null, null, null, null), new Marshaller(new AttributeMapperFactory(new AttributeReader())));

        return $config->build(CoreWorkerFactory::create()->newWorker('default'), CoreRole::Workflow);
    }

    public static function create(Bridge $bridge, int $lastEventId, string $workflowId = 'replay'): \FFI\CData
    {
        return $bridge->newReplayer(self::config(), self::history($lastEventId)->serializeToString(), $workflowId);
    }
}
