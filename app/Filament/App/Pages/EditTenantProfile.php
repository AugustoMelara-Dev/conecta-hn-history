<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EditTenantProfile extends BaseEditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Perfil del negocio';
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->tenant = $tenant
            ? $tenant->newQueryWithoutScopes()->whereKey($tenant->getKey())->first()
            : null;

        abort_unless($this->tenant && static::canView($this->tenant), 404);

        $this->fillForm();
    }

    public function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        $isPro = $tenant instanceof Tenant && (bool) $tenant->is_pro;
        $tenantAttributes = $this->tenant?->getAttributes() ?? [];
        $hasCategoryColumn = Schema::hasTable('categories')
            && Schema::hasColumn('tenants', 'category_id')
            && array_key_exists('category_id', $tenantAttributes);
        $hasCityColumn = Schema::hasTable('cities')
            && Schema::hasColumn('tenants', 'city_id')
            && array_key_exists('city_id', $tenantAttributes);

        return $form
            ->model($this->tenant)
            ->schema([
                Forms\Components\Tabs::make('ProfileSettings')
                    ->extraAttributes(['data-testid' => 'business-profile-tabs'])
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Identidad')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\Section::make('Informacion publica')
                                    ->description('Estos datos se muestran en el directorio y en tu micrositio.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre del negocio')
                                            ->required()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL publica')
                                            ->prefix(rtrim((string) config('app.url'), '/').'/')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->alphaDash()
                                            ->helperText('Cambiar esta URL puede romper enlaces que ya compartiste.'),

                                        Forms\Components\TextInput::make('slogan')
                                            ->label('Frase de marca')
                                            ->maxLength(100)
                                            ->placeholder('La mejor pizza de Honduras')
                                            ->helperText('Maximo 100 caracteres. Se muestra debajo del nombre.')
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripcion corta')
                                            ->rows(3)
                                            ->maxLength(255)
                                            ->helperText('Resumen breve para buscadores, directorio y tarjetas publicas.')
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('about')
                                            ->label('Historia del negocio')
                                            ->rows(5)
                                            ->placeholder('Cuenta que hace diferente a tu negocio, tu historia y lo que prometes al cliente.')
                                            ->helperText('Texto extendido para tu micrositio.')
                                            ->columnSpanFull(),

                                        ...($hasCategoryColumn ? [
                                            Forms\Components\Select::make('category_id')
                                                ->label('Categoria')
                                                ->options(fn (): array => Category::query()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->all())
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->native(false)
                                                ->helperText('Ayuda a que clientes correctos te encuentren en el directorio.'),
                                        ] : []),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Logo')
                                    ->description('Usa una imagen simple, legible y centrada.')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo_path')
                                            ->label('Logo')
                                            ->avatar()
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('1:1')
                                            ->directory('tenants/logos')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048)
                                            ->helperText('Maximo 2 MB. Recomendado: formato cuadrado.'),
                                    ]),

                                Forms\Components\Section::make('Marca visual')
                                    ->description($isPro
                                        ? 'Personaliza la apariencia publica de tu negocio.'
                                        : 'Disponible en Pro. Puedes preparar los datos y activar el plan cuando lo necesites.')
                                    ->icon('heroicon-o-sparkles')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('brand_color')
                                            ->label('Color principal')
                                            ->required()
                                            ->default('#2563EB')
                                            ->regex('/^#[0-9A-Fa-f]{6}$/')
                                            ->helperText('Se usa en botones, enlaces y detalles de tu micrositio.'),

                                        Forms\Components\FileUpload::make('hero_image')
                                            ->label('Imagen de portada')
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
                                            ->hint(! $isPro ? 'Pro' : null)
                                            ->helperText('Maximo 2 MB. Recomendado: 1920 x 600 px.'),

                                        Forms\Components\FileUpload::make('banner_path')
                                            ->label('Banner secundario')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('16:9')
                                            ->directory('tenants/banners')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048)
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? 'Pro' : null),

                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label('Color secundario')
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? 'Pro' : null)
                                            ->helperText('Color alternativo para variaciones de marca.'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Ubicacion')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\Section::make('Contacto')
                                    ->description('Canales visibles para clientes y pedidos.')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->label('Correo')
                                            ->email()
                                            ->prefixIcon('heroicon-o-envelope'),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('Telefono')
                                            ->tel()
                                            ->prefixIcon('heroicon-o-phone'),

                                        Forms\Components\TextInput::make('whatsapp_number')
                                            ->label('WhatsApp')
                                            ->tel()
                                            ->prefixIcon('heroicon-o-chat-bubble-left-right'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Direccion')
                                    ->description('Ubicacion principal para clientes.')
                                    ->schema([
                                        ...($hasCityColumn ? [
                                            Forms\Components\Select::make('city_id')
                                                ->label('Ciudad')
                                                ->options(fn (): array => City::query()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->all())
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                        ] : []),

                                        Forms\Components\Textarea::make('address')
                                            ->label('Direccion')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Coordenadas')
                                    ->description($isPro ? 'Opcional para ubicar el negocio en mapa.' : 'Disponible en Pro.')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->label('Latitud')
                                            ->numeric()
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? 'Pro' : null),

                                        Forms\Components\TextInput::make('longitude')
                                            ->label('Longitud')
                                            ->numeric()
                                            ->disabled(! $isPro)
                                            ->hint(! $isPro ? 'Pro' : null),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Operaciones')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Forms\Components\Section::make('Redes y enlaces')
                                    ->description($isPro ? 'Conecta canales externos del negocio.' : 'Disponible en Pro.')
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
                                            ->label('Sitio web')
                                            ->url()
                                            ->disabled(! $isPro)
                                            ->prefixIcon('heroicon-o-globe-americas'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Horario general')
                                    ->description('Texto simple para mostrar disponibilidad cuando no usas horarios detallados.')
                                    ->visible(! $isPro)
                                    ->schema([
                                        Forms\Components\TextInput::make('simple_schedule')
                                            ->label('Horario de atencion')
                                            ->placeholder('Ej: Lunes a viernes de 8:00 AM a 5:00 PM')
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Horarios detallados')
                                    ->description('Configura dias y horas de apertura.')
                                    ->visible($isPro)
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Repeater::make('hours_data')
                                            ->label('Horarios')
                                            ->schema([
                                                Forms\Components\Select::make('day')
                                                    ->label('Dia')
                                                    ->options([
                                                        'lunes' => 'Lunes',
                                                        'martes' => 'Martes',
                                                        'miercoles' => 'Miercoles',
                                                        'jueves' => 'Jueves',
                                                        'viernes' => 'Viernes',
                                                        'sabado' => 'Sabado',
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
                                            ->addActionLabel('Agregar dia')
                                            ->defaultItems(0)
                                            ->maxItems(7),
                                    ]),

                                Forms\Components\Section::make('WhatsApp')
                                    ->description('Mensaje sugerido para iniciar conversaciones con clientes.')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Textarea::make('whatsapp_message')
                                            ->label('Mensaje predeterminado')
                                            ->rows(2)
                                            ->placeholder('Hola, me interesa hacer un pedido.'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $tenant = $this->tenant;
        $isPro = $tenant instanceof Tenant && (bool) $tenant->is_pro;

        return [
            Action::make('view_site')
                ->label('Ver sitio')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $tenant ? url('/'.$tenant->slug) : url('/directorio'))
                ->openUrlInNewTab(),

            Action::make('upgrade')
                ->label('Mejorar plan')
                ->icon('heroicon-m-sparkles')
                ->color('warning')
                ->visible(! $isPro)
                ->url(fn () => $tenant
                    ? route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug])
                    : url('/registrar-negocio')),
        ];
    }

    public static function canView(Model $tenant): bool
    {
        $user = auth()->user();

        return $user?->canAccessTenant($tenant) ?? false;
    }
}
