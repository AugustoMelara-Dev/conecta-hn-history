<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\Geography\Contracts\TenantCoverageWriter;
use App\Domain\Geography\Enums\FulfillmentMode;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @property-read Form $form */
final class ManageCoverage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.app.pages.manage-coverage';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Tenant && $user->canAccessTenant($tenant);
    }

    public static function getNavigationLabel(): string
    {
        return 'Cobertura';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Negocio';
    }

    public function getTitle(): string
    {
        return 'Cobertura comercial';
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Tenant, 404);
        $tenantId = (string) $tenant->getKey();
        $location = DB::table('locations')->where('tenant_id', $tenantId)
            ->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->first();
        $areas = $location === null ? collect() : DB::table('location_service_areas')
            ->where('tenant_id', $tenantId)->where('location_id', $location->id)->where('is_active', true)->get();
        $representative = $areas->firstWhere('area_type', 'radius')
            ?? $areas->firstWhere('area_type', 'administrative_area')
            ?? $areas->firstWhere('area_type', 'locality');

        $this->form->fill([
            'location_id' => $location?->id,
            'modes' => $areas->pluck('fulfillment_mode')->unique()->values()->all(),
            'area_type' => $representative?->area_type ?? 'locality',
            'radius_km' => $representative?->radius_km,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('location_id')->label('Ubicacion operativa')
                ->options(function (): array {
                    $tenant = Filament::getTenant();
                    if (! $tenant instanceof Tenant) {
                        return [];
                    }

                    return DB::table('locations')->where('tenant_id', $tenant->getKey())->where('is_active', true)
                        ->orderByDesc('is_primary')->orderBy('name')->pluck('name', 'id')->all();
                })->searchable()->required(),
            CheckboxList::make('modes')->label('Modalidades de atencion')->options([
                FulfillmentMode::Storefront->value => 'En local',
                FulfillmentMode::Delivery->value => 'Entrega',
                FulfillmentMode::Pickup->value => 'Recoger en local',
                FulfillmentMode::OnSite->value => 'A domicilio',
                FulfillmentMode::Remote->value => 'Remoto',
            ])->columns(2)->required()->minItems(1),
            Radio::make('area_type')->label('Alcance para entrega o atencion a domicilio')->options([
                'locality' => 'Localidad de la ubicacion',
                'administrative_area' => 'Area administrativa de la ubicacion',
                'national' => 'Todo el pais',
                'radius' => 'Radio desde la localidad',
            ])->required()->live(),
            TextInput::make('radius_km')->label('Radio (km)')->numeric()->minValue(0.01)->maxValue(500)
                ->visible(fn ($get): bool => $get('area_type') === 'radius'),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Tenant, 404);
        $data = $this->form->getState();
        $modes = [];
        foreach ((array) ($data['modes'] ?? []) as $value) {
            $mode = FulfillmentMode::tryFrom((string) $value);
            if ($mode === null) {
                throw ValidationException::withMessages(['data.modes' => 'Una modalidad seleccionada no es valida.']);
            }
            $modes[] = $mode;
        }

        app(TenantCoverageWriter::class)->replace(
            (string) $tenant->getKey(),
            (string) ($data['location_id'] ?? ''),
            $modes,
            (string) ($data['area_type'] ?? ''),
            isset($data['radius_km']) && $data['radius_km'] !== '' ? (float) $data['radius_km'] : null,
        );

        Notification::make()->success()->title('Cobertura actualizada')->send();
    }
}
