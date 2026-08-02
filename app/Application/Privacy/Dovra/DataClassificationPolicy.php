<?php

declare(strict_types=1);

namespace App\Application\Privacy\Dovra;

use App\Domain\Privacy\Dovra\DataDecision;
use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Domain\Privacy\Dovra\SensitivePayloadRedactor;

final class DataClassificationPolicy
{
    private const DISALLOWED_UPLOAD_MIME = [
        'image/svg+xml',
        'text/html',
        'application/javascript',
        'text/javascript',
    ];

    public function __construct(
        private readonly SensitivePayloadRedactor $redactor,
    ) {}

    public function classifyAndValidate(DataEnvelope $data): DataDecision
    {
        $redacted = $this->redactor->scrub($data->payload);
        $reasons = [];

        if ($this->containsPhi($data)) {
            $reasons[] = 'phi_prohibited';
        }

        if ($this->containsCardData($data->payload)) {
            $reasons[] = 'card_data_prohibited';
        }

        if ($this->containsBiometricData($data->payload)) {
            $reasons[] = 'biometric_prohibited';
        }

        if ($this->containsSecrets($data->payload)) {
            $reasons[] = 'secret_prohibited';
        }

        if ($this->containsMinorData($data->payload)) {
            $reasons[] = 'minor_data_prohibited';
        }

        if ($this->containsPromptInjection($data->payload)) {
            $reasons[] = 'prompt_injection_detected';
        }

        if ($this->containsUnsafeUrl($data->payload)) {
            $reasons[] = 'ssrf_target_prohibited';
        }

        if ($this->containsUnsafeUpload($data->payload)) {
            $reasons[] = 'upload_type_prohibited';
        }

        if ($reasons !== []) {
            return new DataDecision(
                allowed: false,
                classification: 'prohibited',
                retentionCategory: 'prohibited_quarantine',
                reasons: array_values(array_unique($reasons)),
                redactedPayload: $redacted,
                integrationSeams: $this->seamsFor($data->sourceSystem),
            );
        }

        return new DataDecision(
            allowed: true,
            classification: $this->classificationFor($data),
            retentionCategory: $this->retentionCategoryFor($data),
            reasons: [],
            redactedPayload: $redacted,
            integrationSeams: $this->seamsFor($data->sourceSystem),
        );
    }

    private function classificationFor(DataEnvelope $data): string
    {
        if (in_array($data->sourceSystem, ['audit', 'log', 'webhook'], true)) {
            return 'operational_sensitive';
        }

        if ($this->containsContactData($data->payload)) {
            return 'ordinary_personal';
        }

        return 'public_business';
    }

    private function retentionCategoryFor(DataEnvelope $data): string
    {
        return match ($data->sourceSystem) {
            'export' => 'dsr_audit',
            'audit', 'log', 'webhook' => 'security_incident',
            default => 'conversation_standard',
        };
    }

    /**
     * @return list<string>
     */
    private function seamsFor(string $sourceSystem): array
    {
        return match ($sourceSystem) {
            'export' => ['legacy_account_privacy', 'sales_export_tenant_guard', 'audit_context'],
            'upload', 'website_import' => ['safe_catalog_image_normalizer', 'postgres_rls_runtime'],
            'private_ai_prompt', 'knowledge_ingestion' => ['ai_capability_policy', 'postgres_rls_runtime'],
            'audit', 'log', 'webhook' => ['sensitive_log_scrubber', 'audit_context'],
            default => ['postgres_rls_runtime'],
        };
    }

    private function containsPhi(DataEnvelope $data): bool
    {
        $text = $this->flattenToString($data->payload);

        if ($text === '') {
            return false;
        }

        $markers = [
            'diagnosis',
            'patient',
            'prescription',
            'medical record',
            'symptom',
            'blood pressure',
            'arrhythmia',
            'diabetes',
        ];

        foreach ($markers as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsCardData(array $payload): bool
    {
        foreach ($this->flattenValues($payload) as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $normalizedValue = preg_replace('/\D+/', '', (string) $value) ?? '';

            if (str_contains($normalizedKey, 'cvv') || str_contains($normalizedKey, 'cvc')) {
                return true;
            }

            if ($normalizedValue !== '' && strlen($normalizedValue) >= 13 && strlen($normalizedValue) <= 19 && $this->passesLuhn($normalizedValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsBiometricData(array $payload): bool
    {
        foreach (array_keys($this->flattenValues($payload)) as $key) {
            $normalizedKey = strtolower((string) $key);
            foreach (['biometric', 'faceprint', 'fingerprint', 'voiceprint', 'retina'] as $token) {
                if (str_contains($normalizedKey, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsSecrets(array $payload): bool
    {
        foreach ($this->flattenValues($payload) as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (preg_match('/(?:password|secret|token|api_key|authorization|private_key)/i', $normalizedKey) === 1) {
                return true;
            }

            if (is_string($value) && preg_match('/\bsk-[a-z0-9_-]+\b/i', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsMinorData(array $payload): bool
    {
        foreach ($this->flattenValues($payload) as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (preg_match('/minor|child|guardian|school|student/', $normalizedKey) === 1) {
                return true;
            }

            if (preg_match('/age$/', $normalizedKey) === 1 && is_numeric($value) && (int) $value < 18) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsPromptInjection(array $payload): bool
    {
        $text = $this->flattenToString($payload);

        foreach ([
            'ignore previous instructions',
            'reveal the system prompt',
            'override policy',
            'bypass confirmation',
            'tool call without approval',
        ] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsUnsafeUrl(array $payload): bool
    {
        foreach ($this->flattenValues($payload) as $value) {
            if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }

            $parts = parse_url($value);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));

            if (! in_array($scheme, ['http', 'https'], true)) {
                return true;
            }

            if (in_array($host, ['localhost', '127.0.0.1', '::1', '169.254.169.254'], true)) {
                return true;
            }

            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsUnsafeUpload(array $payload): bool
    {
        $filename = strtolower((string) ($payload['filename'] ?? ''));
        $mime = strtolower((string) ($payload['mime'] ?? ''));

        return in_array($mime, self::DISALLOWED_UPLOAD_MIME, true)
            || str_ends_with($filename, '.svg')
            || str_ends_with($filename, '.html')
            || str_ends_with($filename, '.js');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsContactData(array $payload): bool
    {
        foreach (array_keys($this->flattenValues($payload)) as $key) {
            if (preg_match('/(?:name|email|phone|address|message|requested_slot)/i', (string) $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flattenValues(array $payload, string $prefix = ''): array
    {
        $values = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $values += $this->flattenValues($value, $path);

                continue;
            }

            $values[$path] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function flattenToString(array $payload): string
    {
        return strtolower(implode(' ', array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $this->flattenValues($payload),
        )));
    }

    private function passesLuhn(string $value): bool
    {
        $sum = 0;
        $alternate = false;

        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $digit = (int) $value[$index];

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
