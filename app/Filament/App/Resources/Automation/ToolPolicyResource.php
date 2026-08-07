<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation;

use App\Filament\App\Resources\Automation\ToolPolicyResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * ToolPolicyResource - admin/manager only; tabla de tools que cada agente puede correr.
 */
class ToolPolicyResource extends Resource
{
    protected static ?string $model = \App\Models\ToolPolicy::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Tool Policies';

    protected static ?string $modelLabel = 'tool policy';

    protected static ?string $pluralModelLabel = 'Tool Policies';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('automation.policies.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración')->components([
                    Grid::make(2)->components([
                        Select::make('user_id')
                            ->label('Agente / Usuario')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')->label('Nombre de la política')->required()->maxLength(100),
                        MultiSelect::make('allowed_tools')
                            ->label('Tools permitidas')
                            ->options([
                                'send_email' => 'Enviar email',
                                'send_sms' => 'Enviar SMS',
                                'send_whatsapp' => 'Enviar WhatsApp',
                                'create_record' => 'Crear registro',
                                'update_record' => 'Actualizar registro',
                                'call_webhook' => 'Llamar webhook',
                                'run_ai_command' => 'Ejecutar AI',
                            ])
                            ->required(),
                        Select::make('rate_limit_per_hour')
                            ->label('Rate limit (por hora)')
                            ->options([10 => '10', 50 => '50', 100 => '100', 500 => '500', 1000 => '1000'])
                            ->default(100),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Agente')->searchable(),
                TextColumn::make('name')->label('Política'),
                TextColumn::make('allowed_tools')->label('Tools permitidas')->limit(60),
                TextColumn::make('rate_limit_per_hour')->label('Rate limit'),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('tenant_id', $tenant->getKey());
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListToolPolicies::route('/'),
            'create' => Pages\CreateToolPolicy::route('/create'),
            'edit' => Pages\EditToolPolicy::route('/{record}/edit'),
        ];
    }
}
