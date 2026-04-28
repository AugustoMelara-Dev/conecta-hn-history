<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

/**
 * PhoneNumber Value Object
 *
 * Encapsulates logic for formatting and link generation.
 */
final readonly class PhoneNumber
{
    public string $value;

    public function __construct(?string $rawNumber)
    {
        $this->value = preg_replace('/[^0-9]/', '', $rawNumber ?? '');
    }

    public function isValid(): bool
    {
        return ! empty($this->value);
    }

    public function toWhatsappLink(string $message = ''): string
    {
        $phone = $this->value;

        // Honduras Prefix Logic encapsulated
        if (strlen($phone) === 8) {
            $phone = '504'.$phone;
        }

        $url = "https://wa.me/{$phone}";

        if (! empty($message)) {
            $url .= '?text='.urlencode($message);
        }

        return $url;
    }

    public function toCallLink(): string
    {
        return "tel:{$this->value}";
    }
}
