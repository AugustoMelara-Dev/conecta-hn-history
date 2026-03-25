<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Mail\CustomerLifecycleMail;
use App\Models\CustomerProfile;
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
}
