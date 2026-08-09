<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ActivityResource\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Filament\App\Resources\Crm\ActivityResource;
use App\Models\CrmActivity;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof CrmActivity) {
            throw new \InvalidArgumentException('CRM activity edit requires an activity record.');
        }

        [$tenant, $actor] = $this->authority();

        return app(CanonicalCrmEntityWriter::class)->updateActivity(
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
            throw new \RuntimeException('CRM activity mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
    }
}
