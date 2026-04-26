<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

/**
 * Workflow-run operation token codec — Java/Go-compatible.
 *
 * Wire format: `base64url-no-padding(JSON{"t":1,"ns":"<namespace>","wid":"<workflowId>"})`.
 *
 * `t` is the operation token type discriminator (`1` = workflow-run).
 * `v` (version) is intentionally omitted on encode — its presence on decode
 * means the token is from a newer SDK and must be rejected (matches the Go
 * reference at `temporalnexus/token.go` and the Java reference at
 * `io.temporal.internal.nexus.WorkflowRunOperationToken`).
 *
 * @internal Used by {@see \Temporal\Nexus\WorkflowRunOperation} for both
 *           token generation on async-start and decoding on cancel.
 */
final class WorkflowRunOperationToken
{
    private const TYPE_WORKFLOW_RUN = 1;

    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
    ) {}

    /**
     * Encode a workflow-run operation token.
     */
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
     * Decode a workflow-run operation token.
     *
     * @throws \InvalidArgumentException if the token is malformed, has the
     *         wrong type discriminator, carries a non-empty version, or is
     *         missing the workflow id.
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

        // The Go reference rejects any token that surfaces a `v` field —
        // version 0 is implicit (omitted on encode), anything else means
        // the producer is newer than this decoder.
        if (\array_key_exists('v', $parsed) && $parsed['v'] !== 0) {
            throw new \InvalidArgumentException('invalid workflow run token: "v" field should not be present');
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
