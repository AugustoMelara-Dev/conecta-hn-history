<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum ActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Task = 'task';
    case Note = 'note';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Llamada',
            self::Email => 'Email',
            self::Meeting => 'Reunión',
            self::Task => 'Tarea',
            self::Note => 'Nota',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'heroicon-o-phone',
            self::Email => 'heroicon-o-envelope',
            self::Meeting => 'heroicon-o-calendar-days',
            self::Task => 'heroicon-o-clipboard-document-check',
            self::Note => 'heroicon-o-pencil-square',
            self::Sms => 'heroicon-o-chat-bubble-left-right',
            self::WhatsApp => 'heroicon-o-chat-bubble-oval-left',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Call => 'info',
            self::Email => 'primary',
            self::Meeting => 'success',
            self::Task => 'warning',
            self::Note => 'gray',
            self::Sms => 'info',
            self::WhatsApp => 'success',
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
