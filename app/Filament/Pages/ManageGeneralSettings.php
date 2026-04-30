<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class ManageGeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-general-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog';

    public static function getNavigationLabel(): string
    {
        return 'Configuración general';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuración';
    }

    public function getSubheading(): ?string
    {
        return 'Administra precios, soporte y disponibilidad global desde el flujo nativo del superpanel.';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $superAdmins = config('auth.super_admins', []);
        $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);
        $hasSuperAdminRole = $user->role === UserRole::SUPER_ADMIN;

        return $hasSuperAdminRole || $user->isSuperAdmin() || $isWhitelisted;
    }

    public function mount(): void
    {
        $settings = app(GeneralSettings::class);

        $this->form->fill([
            'free_plan_price' => $settings->free_plan_price,
            'pro_plan_price' => $settings->pro_plan_price,
            'pro_plan_original_price' => $settings->pro_plan_original_price,
            'setup_fee_price' => $settings->setup_fee_price,
            'grace_period_days' => $settings->grace_period_days,
            'free_plan_product_limit' => $settings->free_plan_product_limit,
            'pro_plan_product_limit' => $settings->pro_plan_product_limit,
            'bank_transfer_instructions' => $settings->bank_transfer_instructions,
            'support_whatsapp_number' => $settings->support_whatsapp_number,
            'support_email' => $settings->support_email,
            'maintenance_mode' => $settings->maintenance_mode,
            'allow_registrations' => $settings->allow_registrations,
            'feature_rollouts' => $settings->feature_rollouts,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Configuraciones')
                    ->tabs([
                        Tab::make('Finanzas')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Precios y suscripciones')
                                    ->description('Controla precios, períodos de gracia y límites por plan.')
                                    ->schema([
                                        TextInput::make('free_plan_price')
                                            ->label('Precio plan Semilla (lempiras)')
                                            ->numeric()
                                            ->prefix('L.')
                                            ->required()
                                            ->minValue(0)
                                            ->helperText('Normalmente debe quedar en L. 0.00. Se deja editable por si cambia el modelo comercial.'),
                                        TextInput::make('pro_plan_price')
                                            ->label('Precio plan PRO (lempiras)')
                                            ->numeric()
                                            ->prefix('L.')
                                            ->required()
                                            ->minValue(0)
                                            ->helperText('Precio mensual de la suscripción PRO.'),
                                        TextInput::make('pro_plan_original_price')
                                            ->label('Precio referencia PRO (lempiras)')
                                            ->numeric()
                                            ->prefix('L.')
                                            ->required()
                                            ->minValue(0)
                                            ->helperText('Precio tachado o referencia comercial para mostrar descuento.'),
                                        TextInput::make('setup_fee_price')
                                            ->label('Setup asistido (lempiras)')
                                            ->numeric()
                                            ->prefix('L.')
                                            ->required()
                                            ->minValue(0)
                                            ->helperText('Fee unico para ventas asistidas y configuracion inicial.'),
                                        TextInput::make('grace_period_days')
                                            ->label('Días de gracia')
                                            ->numeric()
                                            ->suffix('días')
                                            ->required()
                                            ->helperText('Días antes de suspender el servicio por falta de pago.'),
                                    ])->columns(3),
                                Section::make('Límites de productos')
                                    ->description('Define la capacidad de catálogo disponible por plan.')
                                    ->schema([
                                        TextInput::make('free_plan_product_limit')
                                            ->label('Límite plan gratuito')
                                            ->numeric()
                                            ->suffix('productos')
                                            ->required()
                                            ->helperText('Productos máximos para cuentas gratuitas.'),
                                        TextInput::make('pro_plan_product_limit')
                                            ->label('Límite plan PRO')
                                            ->numeric()
                                            ->suffix('productos')
                                            ->required()
                                            ->helperText('Productos máximos para cuentas PRO.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Banco y soporte')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('Instrucciones de pago')
                                    ->description('Este texto aparece cuando un negocio sube su comprobante.')
                                    ->schema([
                                        RichEditor::make('bank_transfer_instructions')
                                            ->label('Instrucciones de transferencia')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'bulletList',
                                                'orderedList',
                                            ])
                                            ->required()
                                            ->columnSpanFull()
                                            ->helperText('Si cambias de banco, actualiza este texto.'),
                                    ]),
                                Section::make('Canales de soporte')
                                    ->description('Canales de contacto visibles para soporte y pagos.')
                                    ->schema([
                                        TextInput::make('support_whatsapp_number')
                                            ->label('WhatsApp de soporte')
                                            ->prefix('+504')
                                            ->placeholder('99999999')
                                            ->required()
                                            ->helperText('Número de soporte al que llegan consultas de cobros y activación.'),
                                        TextInput::make('support_email')
                                            ->label('Correo de soporte')
                                            ->email()
                                            ->required()
                                            ->helperText('Correo principal para consultas de clientes y negocios.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Sistema')
                            ->icon('heroicon-o-power')
                            ->schema([
                                Section::make('Control de disponibilidad')
                                    ->description('Interruptores globales del sistema.')
                                    ->schema([
                                        Toggle::make('maintenance_mode')
                                            ->label('Modo mantenimiento')
                                            ->helperText('Si está activo, los negocios verán una página temporal de mantenimiento.')
                                            ->onColor('danger')
                                            ->offColor('success'),
                                        Toggle::make('allow_registrations')
                                            ->label('Permitir nuevos registros')
                                            ->helperText('Desactívalo para cerrar temporalmente el registro de nuevos negocios.')
                                            ->onColor('success')
                                            ->offColor('danger'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Rollouts')
                            ->icon('heroicon-o-rocket-launch')
                            ->schema([
                                Section::make('Activación controlada por tenant')
                                    ->description('Publica codigo apagado por defecto y activa features solo para tenants o grupos seguros.')
                                    ->schema([
                                        Repeater::make('feature_rollouts')
                                            ->label('Feature rollouts')
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label('Clave')
                                                    ->placeholder('new_checkout')
                                                    ->required()
                                                    ->alphaDash()
                                                    ->helperText('Usa una clave estable para consultar desde código o middleware.'),
                                                Toggle::make('enabled')
                                                    ->label('Activo')
                                                    ->default(false)
                                                    ->helperText('Si está apagado, nadie accede aunque coincida el tenant.'),
                                                TagsInput::make('tenant_slugs')
                                                    ->label('Tenants permitidos')
                                                    ->placeholder('audit-prod-20260424')
                                                    ->helperText('Slugs exactos. Si un tenant está aquí, gana sobre plan/rubro.'),
                                                Select::make('plans')
                                                    ->label('Planes permitidos')
                                                    ->multiple()
                                                    ->options($this->tenantPlanOptions()),
                                                Select::make('business_types')
                                                    ->label('Rubros permitidos')
                                                    ->multiple()
                                                    ->options($this->businessTypeOptions()),
                                                Textarea::make('notes')
                                                    ->label('Notas')
                                                    ->rows(2)
                                                    ->columnSpanFull()
                                                    ->helperText('Motivo, alcance y criterio para ampliar rollout.'),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar rollout')
                                            ->itemLabel(fn (array $state): ?string => $state['key'] ?? 'Feature rollout'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(GeneralSettings::class);

        $settings->free_plan_price = (float) $data['free_plan_price'];
        $settings->pro_plan_price = (float) $data['pro_plan_price'];
        $settings->pro_plan_original_price = (float) $data['pro_plan_original_price'];
        $settings->setup_fee_price = (float) $data['setup_fee_price'];
        $settings->grace_period_days = (int) $data['grace_period_days'];
        $settings->free_plan_product_limit = (int) $data['free_plan_product_limit'];
        $settings->pro_plan_product_limit = (int) $data['pro_plan_product_limit'];
        $settings->bank_transfer_instructions = $data['bank_transfer_instructions'];
        $settings->support_whatsapp_number = $data['support_whatsapp_number'];
        $settings->support_email = $data['support_email'];
        $settings->maintenance_mode = (bool) $data['maintenance_mode'];
        $settings->allow_registrations = (bool) $data['allow_registrations'];
        $settings->feature_rollouts = $this->sanitizeRollouts($data['feature_rollouts'] ?? []);

        $settings->save();

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->body('Los cambios se aplicaron en toda la plataforma.')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar configuración')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->keyBindings(['mod+s'])
                ->action('save'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeRollouts(mixed $rollouts): array
    {
        if (! is_array($rollouts)) {
            return [];
        }

        $sanitized = [];

        foreach ($rollouts as $rollout) {
            if (! is_array($rollout)) {
                continue;
            }

            $key = strtolower(trim((string) ($rollout['key'] ?? '')));

            if ($key === '') {
                continue;
            }

            $sanitized[] = [
                'key' => $key,
                'enabled' => (bool) ($rollout['enabled'] ?? false),
                'tenant_slugs' => $this->sanitizeStringList($rollout['tenant_slugs'] ?? []),
                'plans' => $this->sanitizeStringList($rollout['plans'] ?? [], array_keys($this->tenantPlanOptions())),
                'business_types' => $this->sanitizeStringList($rollout['business_types'] ?? [], array_keys($this->businessTypeOptions())),
                'notes' => trim((string) ($rollout['notes'] ?? '')),
            ];
        }

        return $sanitized;
    }

    /**
     * @param  array<int, string>|null  $allowed
     * @return array<int, string>
     */
    private function sanitizeStringList(mixed $values, ?array $allowed = null): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $item = strtolower(trim((string) $value));

            if ($item === '') {
                continue;
            }

            if ($allowed !== null && ! in_array($item, $allowed, true)) {
                continue;
            }

            $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, string>
     */
    private function tenantPlanOptions(): array
    {
        return [
            TenantPlan::SEMILLA->value => 'Semilla',
            TenantPlan::PRO->value => 'PRO',
            TenantPlan::CRECIMIENTO->value => 'Crecimiento',
            TenantPlan::COSECHA->value => 'Cosecha',
            TenantPlan::ENTERPRISE->value => 'Enterprise',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function businessTypeOptions(): array
    {
        return [
            BusinessType::RETAIL->value => 'Retail',
            BusinessType::FOOD->value => 'Food',
            BusinessType::SERVICE->value => 'Service',
            BusinessType::HYBRID->value => 'Hybrid',
        ];
    }
}
