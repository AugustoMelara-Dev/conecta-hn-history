<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TagResource\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Filament\App\Resources\Crm\TagResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;
    protected function handleRecordCreation(array $data): Model
    {
        [$tenant, $actor] = $this->authority();

        return app(CanonicalCrmEntityWriter::class)->createTag(
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            $data,
        );
    }

    /** @return array{Tenant, User} */
    private function authority(): array
    {
        $tenant = Filament::getTenant();
        $actor = Filament::auth()->user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new \RuntimeException('CRM tag mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
    }
}
