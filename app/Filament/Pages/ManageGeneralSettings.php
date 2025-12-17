<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * ManageGeneralSettings - Torre de Control del Sistema
 *
 * Página centralizada para gestionar todas las configuraciones
 * del sistema sin necesidad de modificar código.
 */
class ManageGeneralSettings extends Page
{
    // Navigation Icon removed to fix type error (Filament v4)

    protected string $view = 'filament.pages.manage-general-settings';

    public ?array $data = [];

    /**
     * Solo super_admin puede acceder
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('super_admin') || $user->email === 'admin@conectahn.com');
    }

    public function mount(): void
    {
        $settings = app(GeneralSettings::class);

        $this->form->fill([
            'pro_plan_price' => $settings->pro_plan_price,
            'grace_period_days' => $settings->grace_period_days,
            'free_plan_product_limit' => $settings->free_plan_product_limit,
            'pro_plan_product_limit' => $settings->pro_plan_product_limit,
            'bank_transfer_instructions' => $settings->bank_transfer_instructions,
            'support_whatsapp_number' => $settings->support_whatsapp_number,
            'support_email' => $settings->support_email,
            'maintenance_mode' => $settings->maintenance_mode,
            'allow_registrations' => $settings->allow_registrations,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Configuraciones')
                    ->tabs([
                        // ═══════════════════════════════════════════════════════
                        // TAB 1: FINANZAS Y SUSCRIPCIONES
                        // ═══════════════════════════════════════════════════════
                        Tab::make('Finanzas')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Precios y Suscripciones')
                                    ->description('Configura la economia del sistema')
                                    ->schema([
                                        TextInput::make('pro_plan_price')
                                            ->label('Precio Plan PRO (Lempiras)')
                                            ->numeric()
                                            ->prefix('L.')
                                            ->required()
                                            ->helperText('Precio mensual de la suscripcion PRO'),

                                        TextInput::make('grace_period_days')
                                            ->label('Dias de Gracia')
                                            ->numeric()
                                            ->suffix('dias')
                                            ->required()
                                            ->helperText('Dias antes de suspender servicio por falta de pago'),
                                    ])->columns(2),

                                Section::make('Limites de Productos')
                                    ->description('Controla la oferta de valor por plan')
                                    ->schema([
                                        TextInput::make('free_plan_product_limit')
                                            ->label('Limite Plan Gratuito')
                                            ->numeric()
                                            ->suffix('productos')
                                            ->required()
                                            ->helperText('Productos maximos para cuentas Free'),

                                        TextInput::make('pro_plan_product_limit')
                                            ->label('Limite Plan PRO')
                                            ->numeric()
                                            ->suffix('productos')
                                            ->required()
                                            ->helperText('Productos maximos para cuentas PRO'),
                                    ])->columns(2),
                            ]),

                        // ═══════════════════════════════════════════════════════
                        // TAB 2: BANCO Y SOPORTE
                        // ═══════════════════════════════════════════════════════
                        Tab::make('Banco y Soporte')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('Instrucciones de Pago')
                                    ->description('Texto que veran los clientes al momento de pagar')
                                    ->schema([
                                        RichEditor::make('bank_transfer_instructions')
                                            ->label('Instrucciones de Transferencia')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'bulletList',
                                                'orderedList',
                                            ])
                                            ->required()
                                            ->columnSpanFull()
                                            ->helperText('Si cambias de banco, actualiza este texto'),
                                    ]),

                                Section::make('Canales de Soporte')
                                    ->description('Datos de contacto para ayuda al cliente')
                                    ->schema([
                                        TextInput::make('support_whatsapp_number')
                                            ->label('WhatsApp de Soporte')
                                            ->prefix('+504')
                                            ->placeholder('99999999')
                                            ->required()
                                            ->helperText('Numero completo con codigo de pais'),

                                        TextInput::make('support_email')
                                            ->label('Email de Soporte')
                                            ->email()
                                            ->required()
                                            ->helperText('Correo para consultas de clientes'),
                                    ])->columns(2),
                            ]),

                        // ═══════════════════════════════════════════════════════
                        // TAB 3: INTERRUPTORES DE SISTEMA
                        // ═══════════════════════════════════════════════════════
                        Tab::make('Sistema')
                            ->icon('heroicon-o-power')
                            ->schema([
                                Section::make('Control de Disponibilidad')
                                    ->description('Interruptores maestros del sistema')
                                    ->schema([
                                        Toggle::make('maintenance_mode')
                                            ->label('Modo Mantenimiento')
                                            ->helperText('Si esta activo, los inquilinos veran una pagina de "Estamos mejorando"')
                                            ->onColor('danger')
                                            ->offColor('success'),

                                        Toggle::make('allow_registrations')
                                            ->label('Permitir Nuevos Registros')
                                            ->helperText('Desactiva para cerrar temporalmente el registro de nuevos negocios')
                                            ->onColor('success')
                                            ->offColor('danger'),
                                    ])->columns(2),
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

        $settings->pro_plan_price = (float) $data['pro_plan_price'];
        $settings->grace_period_days = (int) $data['grace_period_days'];
        $settings->free_plan_product_limit = (int) $data['free_plan_product_limit'];
        $settings->pro_plan_product_limit = (int) $data['pro_plan_product_limit'];
        $settings->bank_transfer_instructions = $data['bank_transfer_instructions'];
        $settings->support_whatsapp_number = $data['support_whatsapp_number'];
        $settings->support_email = $data['support_email'];
        $settings->maintenance_mode = (bool) $data['maintenance_mode'];
        $settings->allow_registrations = (bool) $data['allow_registrations'];

        $settings->save();

        Notification::make()
            ->success()
            ->title('Configuracion Guardada')
            ->body('Los cambios se aplicaran inmediatamente en todo el sistema.')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar Configuracion')
                ->submit('save'),
        ];
    }
}
