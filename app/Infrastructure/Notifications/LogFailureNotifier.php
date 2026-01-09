<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Shared\Contracts\FailureNotifierInterface;
use Illuminate\Support\Facades\Log;

/**
 * LogFailureNotifier - Default implementation for System Alerts
 */
final class LogFailureNotifier implements FailureNotifierInterface
{
    public function notifySystemFailure(string $subject, string $message, array $metadata = []): void
    {
        // In a real SRE setup, this would dispatch to Slack/PagerDuty via Http facade.
        // For now, it promotes the error to CRITICAL log channel which monitors pick up.
        
        Log::critical("[{$subject}] {$message}", $metadata);
        
        // Example Slack implementation (commented):
        // Http::post(config('services.slack.webhook_url'), ['text' => ...]);
    }
}
