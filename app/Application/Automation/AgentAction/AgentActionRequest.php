<?php

declare(strict_types=1);

namespace App\Application\Automation\AgentAction;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AgentActionRequest
{
    /** @param array<string, mixed> $input */
    private function __construct(
        public string $tenantId,
        public string $agentVersionId,
        public string $conversationId,
        public string $actionKey,
        public array $input,
        public string $idempotencyKey,
        public string $risk,
        public string $payloadHash,
        public string $workflowVersion,
        public ?string $confirmationId,
        public ?string $confirmationToken,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromWorkforce(
        string $tenantId,
        string $agentVersionId,
        string $conversationId,
        string $actionKey,
        array $input,
        string $idempotencyKey,
        string $risk,
    ): self {
        foreach ([
            'tenantId' => $tenantId,
            'agentVersionId' => $agentVersionId,
            'conversationId' => $conversationId,
        ] as $field => $value) {
            if (! Str::isUuid($value)) {
                throw new InvalidArgumentException("Agent action {$field} must be a UUID.");
            }
        }

        $actionKey = trim($actionKey);
        if (preg_match('/^[a-z0-9]+(?:[._:-][a-z0-9]+)*$/', $actionKey) !== 1 || mb_strlen($actionKey) > 128) {
            throw new InvalidArgumentException('Agent action key is invalid.');
        }
        if (! in_array($risk, ['read', 'prepare', 'mutation', 'economic'], true)) {
            throw new InvalidArgumentException('Agent action risk is invalid.');
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 160) {
            throw new InvalidArgumentException('Agent action idempotency key is invalid.');
        }

        $workflowVersion = trim((string) ($input['_workflow_version'] ?? 'v1'));
        if ($workflowVersion === '' || mb_strlen($workflowVersion) > 100) {
            throw new InvalidArgumentException('Agent action workflow version is invalid.');
        }
        $confirmationId = self::nullableControl($input, '_confirmation_id');
        $confirmationToken = self::nullableControl($input, '_confirmation_token');
        if ($confirmationId !== null && ! Str::isUuid($confirmationId)) {
            throw new InvalidArgumentException('Agent action confirmation ID is invalid.');
        }

        unset($input['_workflow_version'], $input['_confirmation_id'], $input['_confirmation_token']);
        $input = self::sort($input);

        return new self(
            tenantId: strtolower($tenantId),
            agentVersionId: strtolower($agentVersionId),
            conversationId: strtolower($conversationId),
            actionKey: $actionKey,
            input: $input,
            idempotencyKey: $idempotencyKey,
            risk: $risk,
            payloadHash: hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            workflowVersion: $workflowVersion,
            confirmationId: $confirmationId,
            confirmationToken: $confirmationToken,
        );
    }

    /** @param array<string, mixed> $input */
    private static function nullableControl(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private static function sort(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sort($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
