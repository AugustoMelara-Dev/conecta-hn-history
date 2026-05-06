<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;
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

    public function form(Form $form): Form
    {
        $isPro = Filament::getTenant()?->is_pro ?? false;
        $billingUrl = '#';

        try {
            $billingUrl = route('filament.app.pages.manage-billing', ['tenant' => Filament::getTenant()?->slug ?? '']);
        } catch (\Exception $e) {
            // Route may not exist during app boot
        }

        return $form
            ->schema([
                Forms\Components\Tabs::make('ProfileSettings')
                    ->tabs([
                        // ═══════════════════════════════════════════════════════════
                        // TAB 1: IDENTIDAD VISUAL
                        // ═══════════════════════════════════════════════════════════
                        Forms\Components\Tabs\Tab::make('Identidad')
                            ->label('🎨 Marca')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\Section::make('Información del Negocio')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre del Negocio')
                                            ->required()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Personalizada')
                                            ->prefix('conectahn.com/')
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

                                Forms\Components\Section::make('Logo')
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

                                Forms\Components\Section::make('Personalización de Marca')
                                    ->description($isPro ? '✨ Personaliza tu identidad visual.' : '🔒 Funciones PRO - Actualiza tu plan para desbloquear')
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
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? '🔒 PRO' : null)
                                            ->helperText('Máx 2MB. Recomendado: 1920x600px. Se muestra en la cabecera de tu sitio.'),

                                        Forms\Components\FileUpload::make('banner_path')
                                            ->label('Banner Secundario')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('16:9')
                                            ->directory('tenants/banners')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048)
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? '🔒 PRO' : null),

                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label('Color Secundario')
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? '🔒 PRO' : null)
                                            ->helperText('Color alternativo para variaciones.'),
                                    ])
                                    ->columns(2),
                            ]),

                        // ═══════════════════════════════════════════════════════════
                        // TAB 2: UBICACIÓN
                        // ═══════════════════════════════════════════════════════════
                        Forms\Components\Tabs\Tab::make('Ubicación')
                            ->label('📍 Ubicación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\Section::make('Contacto')
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

                                Forms\Components\Section::make('Dirección')
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

                                Forms\Components\Section::make('GPS')
                                    ->description($isPro ? 'Pin en el mapa' : '🔒 PRO-only')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->label('Latitud')
                                            ->numeric()
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? '🔒 PRO' : null),

                                        Forms\Components\TextInput::make('longitude')
                                            ->label('Longitud')
                                            ->numeric()
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? '🔒 PRO' : null),
                                    ])
                                    ->columns(2),
                            ]),

                        // ═══════════════════════════════════════════════════════════
                        // TAB 3: OPERACIONES
                        // ═══════════════════════════════════════════════════════════
                        Forms\Components\Tabs\Tab::make('Operaciones')
                            ->label('⏰ Operaciones')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Forms\Components\Section::make('Redes Sociales')
                                    ->description($isPro ? 'Conecta tus redes' : '🔒 PRO-only')
                                    ->schema([
                                        Forms\Components\TextInput::make('facebook_url')
                                            ->label('Facebook')
                                            ->url()
                                            ->disabled(! $isPro)
                                            ->prefixIcon('heroicon-o-globe-alt'),

                                        Forms\Components\TextInput::make('instagram_url')
                                            ->label('Instagram')
                                            ->url()
                                            ->disabled(! $isPro)
                                            ->prefixIcon('heroicon-o-camera'),

                                        Forms\Components\TextInput::make('tiktok_url')
                                            ->label('TikTok')
                                            ->url()
                                            ->disabled(! $isPro)
                                            ->prefixIcon('heroicon-o-play'),

                                        Forms\Components\TextInput::make('website_url')
                                            ->label('Sitio Web')
                                            ->url()
                                            ->disabled(! $isPro)
                                            ->prefixIcon('heroicon-o-globe-americas'),
                                    ])
                                    ->columns(2),

                                // Horario Simple para FREE
                                Forms\Components\Section::make('Horario General')
                                    ->visible(! $isPro)
                                    ->schema([
                                        Forms\Components\TextInput::make('simple_schedule')
                                            ->label('Horario de Atención')
                                            ->placeholder('Ej: Lunes a Viernes de 8:00 AM a 5:00 PM')
                                            ->columnSpanFull(),
                                    ]),

                                // Horario Complejo para PRO
                                Forms\Components\Section::make('Horarios Detallados')
                                    ->visible($isPro)
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

                                Forms\Components\Section::make('WhatsApp')
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
        $isPro = $this->tenant?->is_pro ?? false;

        return [
            \Filament\Actions\Action::make('view_site')
                ->label('Ver mi Sitio')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => url('/'.$this->tenant->slug))
                ->openUrlInNewTab(),

            \Filament\Actions\Action::make('upgrade')
                ->label('✨ Mejorar a PRO')
                ->icon('heroicon-m-sparkles')
                ->color('warning')
                ->visible(! $isPro)
                ->url(fn () => route('filament.app.pages.manage-billing', ['tenant' => $this->tenant->slug])),
        ];
    }
}
