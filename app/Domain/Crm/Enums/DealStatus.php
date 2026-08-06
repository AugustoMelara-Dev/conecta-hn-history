<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Won => 'Ganado',
            self::Lost => 'Perdido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Won => 'success',
            self::Lost => 'danger',
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
