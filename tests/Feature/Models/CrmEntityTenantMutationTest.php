<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmCompany;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects dirty tenant ownership updates for canonical CRM models', function (string $modelClass, array $attributes): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $model = $modelClass::withoutGlobalScopes()->forceCreate(array_merge([
        'tenant_id' => $tenant->id,
    ], $attributes));

    $model->tenant_id = $otherTenant->id;

    expect(fn () => $model->save())
        ->toThrow(LogicException::class, 'Tenant ownership is immutable after creation.');
    expect((string) $model->getRawOriginal('tenant_id'))->toBe((string) $tenant->id);
})->with([
    [CrmCompany::class, ['name' => 'Company']],
    [Contact::class, ['first_name' => 'Contact']],
    [CrmActivity::class, ['type' => 'call']],
    [Tag::class, ['name' => 'Tag', 'slug' => 'tag']],
]);
