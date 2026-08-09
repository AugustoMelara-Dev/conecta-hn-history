<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

/**
 * LifecycleStage - Estado del contacto en el ciclo de ventas.
 */
enum LifecycleStage: string
{
    case Lead = 'lead';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Qualified => 'Calificado',
            self::Proposal => 'Propuesta',
            self::Negotiation => 'Negociación',
            self::Won => 'Ganado',
            self::Lost => 'Perdido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Lead => 'gray',
            self::Qualified => 'info',
            self::Proposal => 'warning',
            self::Negotiation => 'primary',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost], true);
    }

    public function probability(): int
    {
        return match ($this) {
            self::Lead => 10,
            self::Qualified => 25,
            self::Proposal => 50,
            self::Negotiation => 75,
            self::Won => 100,
            self::Lost => 0,
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
