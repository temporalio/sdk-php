<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

class ActivityCompletionClientTestCase extends ClientTestCase
{
    public function testCompletedExternallyByToken()
    {
        $w = $this->createClient();
        $simple = $w->newUntypedWorkflowStub('ExternalCompleteWorkflow');

        $e = $simple->start(['hello world']);
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        sleep(1);
        $this->assertFileExists(__DIR__ . '/../taskToken');
        $taskToken = file_get_contents(__DIR__ . '/../taskToken');
        unlink(__DIR__ . '/../taskToken');

        $act = $w->newActivityCompletionClient();

        $act->completeByToken($taskToken, 'Completed Externally');

        $this->assertSame('Completed Externally', $simple->getResult(0));
    }

    // todo: by id
}
