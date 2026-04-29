<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Tenant\Commands\UpdateTenantPublicProfileCommand;
use App\Application\Tenant\Handlers\UpdateTenantPublicProfileHandler;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * EditTenantProfile - Centro de Control de Identidad del Negocio
 *
 * ESTRATEGIA DE MONETIZACIÓN:
 * - FREE ("La Jaula"): Campos premium visibles pero DESHABILITADOS
 * - PRO ("El Estudio"): Acceso completo a personalización
 */
class EditTenantProfile extends BaseEditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Perfil de mi Negocio';
    }

    public static function canView(Model $tenant): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            Capability::CATALOG_BASIC,
            Permission::SETTINGS_VIEW
        );
    }

    public function form(Schema $schema): Schema
    {
        $tenant = $this->getTenantRecord();
        $hasAdvancedBranding = $tenant instanceof Tenant ? $tenant->hasFeature(TenantFeature::ADVANCED_BRANDING) : false;
        $billingUrl = '#';

        try {
            $billingUrl = route('filament.app.pages.manage-billing', ['tenant' => $tenant instanceof Tenant ? $tenant->slug : '']);
        } catch (\Exception $e) {
            // Route may not exist during app boot
        }

        return $schema
            ->schema([
                Tabs::make('ProfileSettings')
                    ->tabs([
                        // ═══════════════════════════════════════════════════════════
                        // TAB 1: IDENTIDAD VISUAL
                        // ═══════════════════════════════════════════════════════════
                        Tab::make('Identidad')
                            ->label('🎨 Marca')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Section::make('Información del Negocio')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre del Negocio')
                                            ->required()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Personalizada')
                                            ->prefix('vitobusinessos.com/')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->alphaDash()
                                            ->helperText(new HtmlString(
                                                '<span class="text-warning-600">⚠️ Cambiar esto romperá enlaces compartidos.</span>'
                                            )),

                                        Forms\Components\TextInput::make('slogan')
                                            ->label('Eslogan / Frase de Marca')
                                            ->maxLength(100)
                                            ->placeholder('La mejor pizza de Honduras...')
                                            ->helperText('Máximo 100 caracteres. Se muestra en la portada debajo del nombre.')
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripción Corta (SEO)')
                                            ->rows(3)
                                            ->maxLength(255)
                                            ->helperText('Breve descripción para buscadores. Máx 255 caracteres.')
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('about')
                                            ->label('Nuestra Historia')
                                            ->rows(5)
                                            ->placeholder('Cuéntale a tus clientes la historia de tu negocio, tu pasión, tus valores...')
                                            ->helperText('Texto extendido para conectar emocionalmente con tus clientes.')
                                            ->columnSpanFull(),

                                        Forms\Components\Select::make('category_id')
                                            ->label('Categoría del Negocio')
                                            ->relationship('category', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->native(false)
                                            ->helperText('Determina en qué sección apareces en el directorio.'),
                                    ])
                                    ->columns(2),

                                Section::make('Logo')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo_path')
                                            ->label('Logo')
                                            ->avatar()
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('1:1')
                                            ->directory('tenants/logos')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048),
                                    ]),

                                Section::make('Personalización de Marca')
                                    ->description($hasAdvancedBranding ? '✨ Personaliza tu identidad visual.' : '🔒 Funciones PRO - Actualiza tu plan para desbloquear')
                                    ->icon('heroicon-o-sparkles')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('brand_color')
                                            ->label('Color Principal de Marca')
                                            ->required()
                                            ->default('#2563EB')
                                            ->regex('/^#[0-9A-Fa-f]{6}$/')
                                            ->helperText('Se aplica a botones, enlaces y elementos destacados de tu micrositio.'),

                                        Forms\Components\FileUpload::make('hero_image')
                                            ->label('Imagen de Portada (Hero)')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('16:9')
                                            ->imageResizeMode('cover')
                                            ->imageResizeTargetWidth('1920')
                                            ->imageResizeTargetHeight('600')
                                            ->directory('tenant-heroes')
                                            ->maxSize(2048)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->disabled(! $hasAdvancedBranding)
                                            ->hint(! $hasAdvancedBranding ? '🔒 PRO' : null)
                                            ->helperText('Máx 2MB. Recomendado: 1920x600px. Se muestra en la cabecera de tu sitio.'),

                                        Forms\Components\FileUpload::make('banner_path')
                                            ->label('Banner Secundario')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('16:9')
                                            ->directory('tenants/banners')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048)
                                            ->disabled(! $hasAdvancedBranding)
                                            ->hint(! $hasAdvancedBranding ? '🔒 PRO' : null),

                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label('Color Secundario')
                                            ->disabled(! $hasAdvancedBranding)
                                            ->hint(! $hasAdvancedBranding ? '🔒 PRO' : null)
                                            ->helperText('Color alternativo para variaciones.'),
                                    ])
                                    ->columns(2),
                            ]),

                        // ═══════════════════════════════════════════════════════════
                        // TAB 2: UBICACIÓN
                        // ═══════════════════════════════════════════════════════════
                        Tab::make('Ubicación')
                            ->label('📍 Ubicación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Contacto')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->prefixIcon('heroicon-o-envelope'),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->prefixIcon('heroicon-o-phone'),

                                        Forms\Components\TextInput::make('whatsapp_number')
                                            ->label('WhatsApp')
                                            ->tel()
                                            ->prefixIcon('heroicon-o-chat-bubble-left-right'),
                                    ])
                                    ->columns(3),

                                Section::make('Dirección')
                                    ->schema([
                                        Forms\Components\Select::make('city_id')
                                            ->label('Ciudad')
                                            ->relationship('city', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Forms\Components\Textarea::make('address')
                                            ->label('Dirección')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('GPS')
                                    ->description($hasAdvancedBranding ? 'Pin en el mapa' : '🔒 PRO-only')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->label('Latitud')
                                            ->numeric()
                                            ->disabled(! $hasAdvancedBranding)
                                            ->hint(! $hasAdvancedBranding ? '🔒 PRO' : null),

                                        Forms\Components\TextInput::make('longitude')
                                            ->label('Longitud')
                                            ->numeric()
                                            ->disabled(! $hasAdvancedBranding)
                                            ->hint(! $hasAdvancedBranding ? '🔒 PRO' : null),
                                    ])
                                    ->columns(2),
                            ]),

                        // ═══════════════════════════════════════════════════════════
                        // TAB 3: OPERACIONES
                        // ═══════════════════════════════════════════════════════════
                        Tab::make('Operaciones')
                            ->label('⏰ Operaciones')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Redes Sociales')
                                    ->description($hasAdvancedBranding ? 'Integra tus redes' : '🔒 PRO-only')
                                    ->schema([
                                        Forms\Components\TextInput::make('facebook_url')
                                            ->label('Facebook')
                                            ->url()
                                            ->disabled(! $hasAdvancedBranding)
                                            ->prefixIcon('heroicon-o-globe-alt'),

                                        Forms\Components\TextInput::make('instagram_url')
                                            ->label('Instagram')
                                            ->url()
                                            ->disabled(! $hasAdvancedBranding)
                                            ->prefixIcon('heroicon-o-camera'),

                                        Forms\Components\TextInput::make('tiktok_url')
                                            ->label('TikTok')
                                            ->url()
                                            ->disabled(! $hasAdvancedBranding)
                                            ->prefixIcon('heroicon-o-play'),

                                        Forms\Components\TextInput::make('website_url')
                                            ->label('Sitio Web')
                                            ->url()
                                            ->disabled(! $hasAdvancedBranding)
                                            ->prefixIcon('heroicon-o-globe-americas'),
                                    ])
                                    ->columns(2),

                                // Horario Simple para FREE
                                Section::make('Horario General')
                                    ->visible(! $hasAdvancedBranding)
                                    ->schema([
                                        Forms\Components\TextInput::make('simple_schedule')
                                            ->label('Horario de Atención')
                                            ->placeholder('Ej: Lunes a Viernes de 8:00 AM a 5:00 PM')
                                            ->columnSpanFull(),
                                    ]),

                                // Horario Complejo para PRO
                                Section::make('Horarios Detallados')
                                    ->visible($hasAdvancedBranding)
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Repeater::make('hours_data')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Select::make('day')
                                                    ->label('Día')
                                                    ->options([
                                                        'lunes' => 'Lunes',
                                                        'martes' => 'Martes',
                                                        'miercoles' => 'Miércoles',
                                                        'jueves' => 'Jueves',
                                                        'viernes' => 'Viernes',
                                                        'sabado' => 'Sábado',
                                                        'domingo' => 'Domingo',
                                                    ])
                                                    ->required(),

                                                Forms\Components\TimePicker::make('open')
                                                    ->label('Apertura')
                                                    ->seconds(false),

                                                Forms\Components\TimePicker::make('close')
                                                    ->label('Cierre')
                                                    ->seconds(false),

                                                Forms\Components\Toggle::make('is_closed')
                                                    ->label('Cerrado'),
                                            ])
                                            ->columns(4)
                                            ->reorderable()
                                            ->collapsible()
                                            ->addActionLabel('+ Agregar día')
                                            ->defaultItems(0)
                                            ->maxItems(7),
                                    ]),

                                Section::make('WhatsApp')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Textarea::make('whatsapp_message')
                                            ->label('Mensaje Predeterminado')
                                            ->rows(2)
                                            ->placeholder('Hola, me interesa...'),
                                    ]),
                            ]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $tenant = $this->getTenantRecord();
        $canUpgrade = $tenant instanceof Tenant ? $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT) : false;

        return [
            Action::make('view_site')
                ->label('Ver mi Sitio')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => $tenant instanceof Tenant
                    ? route('tenant.microsite.show', ['tenant' => $tenant->slug])
                    : url('/'))
                ->openUrlInNewTab(),

            Action::make('upgrade')
                ->label('✨ Mejorar a PRO')
                ->icon('heroicon-m-sparkles')
                ->color('warning')
                ->visible($canUpgrade)
                ->url(fn (): string => route('filament.app.pages.manage-billing', ['tenant' => $tenant instanceof Tenant ? $tenant->slug : ''])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        request()->attributes->set('onboarding_module', 'settings');

        return [
            \App\Filament\App\Widgets\OnboardingActivationWidget::class,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (array_key_exists('cover_path', $data) && ! array_key_exists('banner_path', $data)) {
            $data['banner_path'] = $data['cover_path'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('banner_path', $data)) {
            $data['cover_path'] = $data['banner_path'];
            unset($data['banner_path']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Tenant) {
            return parent::handleRecordUpdate($record, $data);
        }

        app(UpdateTenantPublicProfileHandler::class)->handle(
            new UpdateTenantPublicProfileCommand(
                tenantId: (string) $record->getKey(),
                profileData: $data,
                hoursData: is_array($data['hours_data'] ?? null) ? $data['hours_data'] : null,
            )
        );

        $record->refresh();

        return $record;
    }

    protected function getTenantRecord(): ?Tenant
    {
        return $this->tenant instanceof Tenant ? $this->tenant : null;
    }
}
