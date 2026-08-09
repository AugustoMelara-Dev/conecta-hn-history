<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TagResource\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Filament\App\Resources\Crm\TagResource;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Tag) {
            throw new \InvalidArgumentException('CRM tag edit requires a tag record.');
        }

        [$tenant, $actor] = $this->authority();

        return app(CanonicalCrmEntityWriter::class)->updateTag(
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
            throw new \RuntimeException('CRM tag mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
    }
}
