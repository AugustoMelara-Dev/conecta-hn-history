<?php

declare(strict_types=1);

namespace App\Contracts\Notifications;

/**
 * NotificationChannel Contract - Multi-Provider Notification Strategy
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Tenant-Specific Notification Providers        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THE PROBLEM WITHOUT THIS CONTRACT:                                      ║
 * ║    if ($tenant->sms_provider === 'twilio') {                            ║
 * ║        $twilio->messages->create(...);                                  ║
 * ║    } elseif ($tenant->sms_provider === 'tigo') {                        ║
 * ║        $tigo->sendSms(...);                                             ║
 * ║    } elseif ($tenant->sms_provider === 'whatsapp') {                    ║
 * ║        $whatsapp->sendMessage(...);                                     ║
 * ║    }                                                                     ║
 * ║    // Spaghetti code in every notification location                     ║
 * ║                                                                          ║
 * ║  ─────────────────────────────────────────────────────────────────────── ║
 * ║                                                                          ║
 * ║  THE SOLUTION: Inject the correct implementation via Service Container  ║
 * ║    $channel->send($recipient, $message); // Tenant's provider injected  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface NotificationChannel
{
    /**
     * Get the channel identifier (e.g., 'sms', 'whatsapp', 'push').
     */
    public function getIdentifier(): string;

    /**
     * Send a notification.
     *
     * @param string $recipient Phone number, email, or device token
     * @param string $message The notification content
     * @param array $options Additional options (template_id, media_url, etc.)
     * @return bool Whether the notification was queued/sent successfully
     */
    public function send(string $recipient, string $message, array $options = []): bool;

    /**
     * Check if the channel is properly configured and available.
     */
    public function isAvailable(): bool;

    /**
     * Get the delivery status of a previous notification.
     *
     * @param string $notificationId The ID returned by send()
     * @return string Status: 'pending', 'delivered', 'failed', 'unknown'
     */
    public function getDeliveryStatus(string $notificationId): string;
}
