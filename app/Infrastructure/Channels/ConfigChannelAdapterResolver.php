<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels;

use App\Application\Channels\ChannelContext;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\Contracts\ChannelAdapterResolverInterface;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Infrastructure\Channels\Voice\FailClosedVoiceAdapter;
use App\Infrastructure\Channels\WebChat\FakeWebChatAdapter;
use App\Infrastructure\Channels\WhatsApp\FailClosedWhatsAppAdapter;
use App\Infrastructure\Channels\Widget\FakeWidgetAdapter;

final class ConfigChannelAdapterResolver implements ChannelAdapterResolverInterface
{
    /** @param array<string, ChannelAdapterInterface> $adapters */
    public function __construct(private readonly array $adapters = []) {}

    public function resolve(ChannelContext $context): ChannelAdapterInterface
    {
        $provider = $context->connection['provider'] ?? $this->configuredProvider($context->channel);
        $provider = is_string($provider) && trim($provider) !== ''
            ? trim($provider)
            : $context->channel.'.unavailable';

        if (isset($this->adapters[$provider])) {
            return $this->adapters[$provider];
        }

        return match ($provider) {
            'web_chat.fake' => $this->fakeAllowed('web_chat')
                ? new FakeWebChatAdapter
                : new UnavailableChannelAdapter($context->channel, $provider, 'fake_adapter_disabled'),
            'widget.fake' => $this->fakeAllowed('widget')
                ? new FakeWidgetAdapter
                : new UnavailableChannelAdapter($context->channel, $provider, 'fake_adapter_disabled'),
            'email.fake' => $this->fakeAllowed('email')
                ? new FakeEmailAdapter((string) config('dovra_channels.fakes.email.webhook_secret', ''))
                : new UnavailableChannelAdapter($context->channel, $provider, 'fake_adapter_disabled'),
            'whatsapp.fail_closed' => new FailClosedWhatsAppAdapter,
            'sms.fail_closed' => new FailClosedSmsAdapter,
            'voice.fail_closed' => new FailClosedVoiceAdapter,
            default => new UnavailableChannelAdapter($context->channel, $provider),
        };
    }

    private function configuredProvider(string $channel): ?string
    {
        $configured = config("dovra_channels.ports.{$channel}.provider")
            ?? config("dovra_channels.fakes.{$channel}.provider");

        return is_string($configured) ? $configured : null;
    }

    private function fakeAllowed(string $channel): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config("dovra_channels.fakes.{$channel}.enabled", false);
    }
}
