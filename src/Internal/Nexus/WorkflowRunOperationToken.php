<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

/**
 * Workflow-run operation token: base64url(JSON{t:1, ns, wid}).
 * Java/Go-compatible. Non-zero `v` field on decode = newer producer, rejected.
 *
 * @internal
 */
final class WorkflowRunOperationToken
{
    private const TYPE_WORKFLOW_RUN = 1;

    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
    ) {}

    public static function generate(string $namespace, string $workflowId): string
    {
        $payload = [
            't' => self::TYPE_WORKFLOW_RUN,
            'ns' => $namespace,
            'wid' => $workflowId,
        ];

        $json = \json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        return \rtrim(\strtr(\base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @throws \InvalidArgumentException on malformed input.
     */
    public static function load(string $token): self
    {
        if ($token === '') {
            throw new \InvalidArgumentException('invalid workflow run token: token is empty');
        }

        $decoded = \base64_decode(\strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('failed to decode token');
        }

        try {
            $parsed = \json_decode($decoded, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                'failed to unmarshal workflow run operation token: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('failed to unmarshal workflow run operation token: not an object');
        }

        $type = $parsed['t'] ?? null;
        if ($type !== self::TYPE_WORKFLOW_RUN) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'invalid workflow token type: %s, expected: %d',
                    \var_export($type, true),
                    self::TYPE_WORKFLOW_RUN,
                ),
            );
        }

        // Non-zero version = newer producer.
        if (\array_key_exists('v', $parsed) && $parsed['v'] !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                'invalid workflow run token: unsupported version %s',
                \var_export($parsed['v'], true),
            ));
        }

        $workflowId = $parsed['wid'] ?? '';
        if (!\is_string($workflowId) || $workflowId === '') {
            throw new \InvalidArgumentException('invalid workflow run token: missing workflow ID (wid)');
        }

        $namespace = $parsed['ns'] ?? '';
        if (!\is_string($namespace)) {
            throw new \InvalidArgumentException('invalid workflow run token: namespace must be a string');
        }

        return new self($namespace, $workflowId);
    }
}
