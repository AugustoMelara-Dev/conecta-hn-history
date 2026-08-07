<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Boolean = 'boolean';
    case Email = 'email';
    case Url = 'url';
    case Textarea = 'textarea';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Number => 'Número',
            self::Date => 'Fecha',
            self::Select => 'Selección',
            self::Boolean => 'Sí/No',
            self::Email => 'Email',
            self::Url => 'URL',
            self::Textarea => 'Texto largo',
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
