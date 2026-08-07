<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

/**
 * ActionType - Acciones que ejecuta un workflow.
 */
enum ActionType: string
{
    case SendEmail = 'send_email';
    case SendSms = 'send_sms';
    case SendWhatsApp = 'send_whatsapp';
    case CreateRecord = 'create_record';
    case UpdateRecord = 'update_record';
    case CallWebhook = 'call_webhook';
    case RunAiCommand = 'run_ai_command';
    case WaitForApproval = 'wait_for_approval';
    case Branch = 'branch';
    case Loop = 'loop';

    public function label(): string
    {
        return match ($this) {
            self::SendEmail => 'Enviar email',
            self::SendSms => 'Enviar SMS',
            self::SendWhatsApp => 'Enviar WhatsApp',
            self::CreateRecord => 'Crear registro',
            self::UpdateRecord => 'Actualizar registro',
            self::CallWebhook => 'Llamar webhook',
            self::RunAiCommand => 'Ejecutar comando AI',
            self::WaitForApproval => 'Esperar aprobación',
            self::Branch => 'Bifurcación',
            self::Loop => 'Loop',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SendEmail => 'heroicon-o-envelope',
            self::SendSms => 'heroicon-o-chat-bubble-left-right',
            self::SendWhatsApp => 'heroicon-o-chat-bubble-oval-left',
            self::CreateRecord => 'heroicon-o-document-plus',
            self::UpdateRecord => 'heroicon-o-document-magnifying-glass',
            self::CallWebhook => 'heroicon-o-cloud',
            self::RunAiCommand => 'heroicon-o-sparkles',
            self::WaitForApproval => 'heroicon-o-hand-raised',
            self::Branch => 'heroicon-o-arrows-right-left',
            self::Loop => 'heroicon-o-arrow-path-rounded-square',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SendEmail, self::SendSms, self::SendWhatsApp => 'success',
            self::CreateRecord, self::UpdateRecord => 'info',
            self::CallWebhook => 'primary',
            self::RunAiCommand => 'info',
            self::WaitForApproval => 'warning',
            self::Branch, self::Loop => 'gray',
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
