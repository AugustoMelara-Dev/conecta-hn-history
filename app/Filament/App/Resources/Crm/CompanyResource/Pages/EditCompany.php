<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CompanyResource\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Filament\App\Resources\Crm\CompanyResource;
use App\Models\CrmCompany;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof CrmCompany) {
            throw new \InvalidArgumentException('CRM company edit requires a company record.');
        }

        [$tenant, $actor] = $this->authority();

        return app(CanonicalCrmEntityWriter::class)->updateCompany(
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
            throw new \RuntimeException('CRM company mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
    }
}
