<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Filament\App\Resources\Crm\ContactResource;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$tenant, $actor] = $this->authority();
        // The canonical contact schema has no persisted notes column; do not pass the legacy form-only key.
        unset($data['notes']);

        return app(CanonicalCrmEntityWriter::class)->createContact(
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
            throw new \RuntimeException('CRM contact mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
    }
}
