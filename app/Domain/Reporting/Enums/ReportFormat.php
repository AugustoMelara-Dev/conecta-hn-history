<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Xlsx => 'Excel',
            self::Csv => 'CSV',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function mime(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv',
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
