<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LocalizationTest extends TestCase
{
    #[Test]
    public function it_sets_carbon_locale_to_spanish_during_http_requests(): void
    {
        $this->get('/');

        self::assertSame('es', Carbon::getLocale());
    }

    #[Test]
    public function it_renders_relative_dates_in_spanish(): void
    {
        $this->get('/');

        $date = Carbon::now()->subMinute();
        $diff = $date->diffForHumans();

        self::assertStringContainsString('hace', $diff);
    }

    #[Test]
    public function it_renders_month_names_in_spanish(): void
    {
        $this->get('/');

        $date = Carbon::parse('2026-01-15');
        $formatted = $date->translatedFormat('F');

        self::assertSame('enero', strtolower($formatted));
    }
}
