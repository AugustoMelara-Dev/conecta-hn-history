<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

/**
 * TriggerType - Tipos de eventos que inician un workflow.
 */
enum TriggerType: string
{
    case RecordCreated = 'record_created';
    case RecordUpdated = 'record_updated';
    case RecordDeleted = 'record_deleted';
    case ScheduleReached = 'schedule_reached';
    case WebhookReceived = 'webhook_received';
    case ManualRun = 'manual_run';
    case AiMessageReceived = 'ai_message_received';

    public function label(): string
    {
        return match ($this) {
            self::RecordCreated => 'Registro creado',
            self::RecordUpdated => 'Registro actualizado',
            self::RecordDeleted => 'Registro eliminado',
            self::ScheduleReached => 'Programación alcanzada',
            self::WebhookReceived => 'Webhook recibido',
            self::ManualRun => 'Ejecución manual',
            self::AiMessageReceived => 'Mensaje AI recibido',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RecordCreated => 'heroicon-o-plus-circle',
            self::RecordUpdated => 'heroicon-o-pencil-square',
            self::RecordDeleted => 'heroicon-o-trash',
            self::ScheduleReached => 'heroicon-o-clock',
            self::WebhookReceived => 'heroicon-o-arrow-down-on-square',
            self::ManualRun => 'heroicon-o-play',
            self::AiMessageReceived => 'heroicon-o-sparkles',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RecordCreated, self::RecordUpdated => 'info',
            self::RecordDeleted => 'danger',
            self::ScheduleReached => 'warning',
            self::WebhookReceived => 'primary',
            self::ManualRun => 'success',
            self::AiMessageReceived => 'info',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
