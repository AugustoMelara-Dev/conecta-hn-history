<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

enum ReportFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diario',
            self::Weekly => 'Semanal',
            self::Monthly => 'Mensual',
        };
    }

    public function cron(): string
    {
        return match ($this) {
            self::Daily => '0 8 * * *',
            self::Weekly => '0 8 * * 1',
            self::Monthly => '0 8 1 * *',
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
