<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Domain\Crm\Enums\CustomFieldType;
use App\Filament\App\Resources\Crm\CustomFieldResource\Pages;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomFieldResource extends Resource
{
    protected static ?string $model = \App\Models\CrmCustomField::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Campos personalizados';

    protected static ?string $modelLabel = 'campo personalizado';

    protected static ?string $pluralModelLabel = 'Campos personalizados';

    protected static ?int $navigationSort = 17;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identidad')->schema([
                    Grid::make(2)->schema([
                        Select::make('entity')
                            ->label('Entidad')
                            ->options(['contact' => 'Contacto', 'company' => 'Empresa', 'deal' => 'Deal'])
                            ->required()
                            ->native(false),
                        TextInput::make('name')
                            ->label('Nombre visible')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('key')
                            ->label('Key (slug)')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Identificador único en la entidad (snake_case).'),
                        Select::make('type')
                            ->label('Tipo')
                            ->options(CustomFieldType::options())
                            ->required()
                            ->live()
                            ->native(false),
                        Toggle::make('required')->label('Obligatorio'),
                        TextInput::make('order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                    ]),
                ]),
                Section::make('Configuración específica del tipo')
                    ->schema([
                        KeyValue::make('options')
                            ->label('Opciones (para tipo select)')
                            ->keyLabel('Valor')
                            ->valueLabel('Etiqueta')
                            ->visible(fn ($get) => $get('type') === CustomFieldType::Select->value),
                        Textarea::make('description')
                            ->label('Descripción / ayuda')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity')->label('Entidad')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'contact' => 'info', 'company' => 'warning', 'deal' => 'primary', default => 'gray',
                    }),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('key')->label('Key')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge(),
                IconColumn::make('required')->label('Obligatorio')->boolean(),
                TextColumn::make('order')->label('Orden')->sortable(),
            ])
            ->filters([
                SelectFilter::make('entity')->label('Entidad')->options(['contact' => 'Contacto', 'company' => 'Empresa', 'deal' => 'Deal']),
                SelectFilter::make('type')->label('Tipo')->options(CustomFieldType::options()),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('entity')->defaultSort('order');
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
            'index' => Pages\ListCustomFields::route('/'),
            'create' => Pages\CreateCustomField::route('/create'),
            'edit' => Pages\EditCustomField::route('/{record}/edit'),
        ];
    }
}



