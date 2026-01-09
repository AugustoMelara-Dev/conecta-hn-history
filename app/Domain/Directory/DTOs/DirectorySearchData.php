<?php

declare(strict_types=1);

namespace App\Domain\Directory\DTOs;

readonly class DirectorySearchData
{
    public function __construct(
        public ?string $search = null,
        public ?string $citySlug = null,
        public ?string $categorySlug = null,
        public int $page = 1,
        public int $perPage = 12,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            citySlug: $data['city'] ?? null,
            categorySlug: $data['category'] ?? null,
            page: (int) ($data['page'] ?? 1),
            perPage: 12
        );
    }
}
