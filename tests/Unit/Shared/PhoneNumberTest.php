<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Domain\Shared\ValueObjects\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function test_local_honduras_numbers_are_normalized_for_whatsapp(): void
    {
        $phone = new PhoneNumber('9999-9999');

        self::assertSame('50499999999', $phone->toInternationalDigits());
        self::assertSame('https://wa.me/50499999999?text=Hola+Vito', $phone->toWhatsappLink('Hola Vito'));
    }

    public function test_international_prefix_is_preserved_without_plus_symbol(): void
    {
        self::assertSame('14155552671', (new PhoneNumber('+1 (415) 555-2671'))->toInternationalDigits());
        self::assertSame('14155552671', (new PhoneNumber('0014155552671'))->toInternationalDigits());
    }
}
