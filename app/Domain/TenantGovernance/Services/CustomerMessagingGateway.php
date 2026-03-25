<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Mail\CustomerLifecycleMail;
use App\Models\CustomerProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class CustomerMessagingGateway
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{provider: string, provider_message_id: string|null, delivery_status: string|null, payload: array<string, mixed>}
     */
    public function sendLifecycleEmail(CustomerProfile $profile, CustomerLifecycleMail $mailable, array $context = []): array
    {
        $provider = (string) config('customer-automation.email_provider', 'mail');
        $resendKey = (string) config('services.resend.key', '');

        if ($provider === 'resend_api' && $resendKey !== '') {
            $resend = \Resend::client($resendKey);
            $envelope = $mailable->envelope();
            $from = $envelope->from;
            $replyTo = collect($envelope->replyTo ?? [])
                ->filter(static fn ($address): bool => $address instanceof \Illuminate\Mail\Mailables\Address)
                ->map(static fn (\Illuminate\Mail\Mailables\Address $address): string => $address->address)
                ->values()
                ->all();

            $email = $resend->emails->send([
                'from' => $from instanceof \Illuminate\Mail\Mailables\Address
                    ? "{$from->name} <{$from->address}>"
                    : config('mail.from.address'),
                'to' => [$profile->email],
                'subject' => $mailable->subjectLine,
                'html' => $mailable->render(),
                'text' => trim(strip_tags($mailable->render())),
                'reply_to' => $replyTo,
                'tags' => $this->buildTags($profile, $context),
            ]);

            return [
                'provider' => 'resend',
                'provider_message_id' => (string) ($email->id ?? ''),
                'delivery_status' => (string) (($email->last_event ?? '') ?: 'sent'),
                'payload' => [
                    'object' => (string) ($email->object ?? 'email'),
                    'last_event' => (string) ($email->last_event ?? 'sent'),
                ],
            ];
        }

        Mail::to($profile->email)->send($mailable);

        return [
            'provider' => (string) config('mail.default', 'mail'),
            'provider_message_id' => null,
            'delivery_status' => 'sent',
            'payload' => [
                'driver' => (string) config('mail.default', 'mail'),
            ],
        ];
    }

    /**
     * @param  array{body: string, template_name?: string|null, template_language?: string|null, template_variables?: array<int, string>}  $message
     * @param  array<string, mixed>  $context
     * @return array{provider: string, provider_message_id: string|null, delivery_status: string|null, payload: array<string, mixed>}
     */
    public function sendLifecycleWhatsApp(CustomerProfile $profile, array $message, array $context = []): array
    {
        $provider = (string) config('customer-automation.whatsapp_provider', 'whatsapp_cloud');

        if ($provider !== 'whatsapp_cloud') {
            throw new \RuntimeException('WhatsApp provider is not supported.');
        }

        $accessToken = (string) config('services.whatsapp_cloud.access_token', '');
        $phoneNumberId = (string) config('services.whatsapp_cloud.phone_number_id', '');
        $apiVersion = (string) config('services.whatsapp_cloud.version', 'v23.0');
        $templateName = (string) ($message['template_name'] ?? '');
        $templateLanguage = (string) ($message['template_language'] ?? 'es_HN');
        $templateVariables = array_values((array) ($message['template_variables'] ?? []));
        $to = $this->normalizeWhatsAppNumber($profile->phone);

        if ($accessToken === '' || $phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp Cloud API is not configured.');
        }

        if ($to === null) {
            throw new \RuntimeException('Customer phone number is not available for WhatsApp delivery.');
        }

        if ($templateName === '') {
            throw new \RuntimeException('WhatsApp template name is missing.');
        }

        $components = [];

        if ($templateVariables !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => collect($templateVariables)
                    ->map(function (string $key) use ($profile, $context): array {
                        return [
                            'type' => 'text',
                            'text' => $this->resolveTemplateVariable($key, $profile, $context),
                        ];
                    })
                    ->all(),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $templateLanguage],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload)
            ->throw()
            ->json();

        return [
            'provider' => 'whatsapp_cloud',
            'provider_message_id' => (string) data_get($response, 'messages.0.id'),
            'delivery_status' => 'sent',
            'payload' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{name: string, value: string}>
     */
    private function buildTags(CustomerProfile $profile, array $context): array
    {
        $candidateTags = [
            'tenant_id' => (string) $profile->tenant_id,
            'customer_profile_id' => (string) $profile->getKey(),
            'automation_key' => isset($context['automation_key']) ? (string) $context['automation_key'] : null,
            'touchpoint_id' => isset($context['touchpoint_id']) ? (string) $context['touchpoint_id'] : null,
            'segment' => isset($context['segment']) ? (string) $context['segment'] : null,
        ];

        return collect($candidateTags)
            ->filter(static fn (?string $value): bool => filled($value))
            ->map(static fn (string $value, string $name): array => [
                'name' => $name,
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    private function normalizeWhatsAppNumber(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 8) {
            return '504'.$digits;
        }

        if (str_starts_with($digits, '504') && strlen($digits) === 11) {
            return $digits;
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveTemplateVariable(string $key, CustomerProfile $profile, array $context): string
    {
        $businessName = (string) ($profile->tenant?->name ?? 'Conecta HN');
        $ctaUrl = (string) ($context['cta_url'] ?? '');

        return match ($key) {
            'customer_name' => (string) ($profile->name ?? 'cliente'),
            'business_name' => $businessName,
            'cta_url' => $ctaUrl,
            default => (string) ($context[$key] ?? ''),
        };
    }
}
