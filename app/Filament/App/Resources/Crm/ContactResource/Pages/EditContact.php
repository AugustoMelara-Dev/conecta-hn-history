<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Filament\App\Resources\Crm\ContactResource;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Contact) {
            throw new \InvalidArgumentException('CRM contact edit requires a contact record.');
        }

        [$tenant, $actor] = $this->authority();
        // The canonical contact schema has no persisted notes column; do not pass the legacy form-only key.
        unset($data['notes']);

        return app(CanonicalCrmEntityWriter::class)->updateContact(
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            (string) $record->getKey(),
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
