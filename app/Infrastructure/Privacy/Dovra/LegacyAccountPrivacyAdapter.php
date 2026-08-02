<?php

declare(strict_types=1);

namespace App\Infrastructure\Privacy\Dovra;

use App\Application\Privacy\AccountPrivacyService;
use App\Models\User;

final class LegacyAccountPrivacyAdapter
{
    public function __construct(
        private readonly AccountPrivacyService $inner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return $this->inner->export($user);
    }

    public function delete(User $user): void
    {
        $this->inner->delete($user);
    }
}
