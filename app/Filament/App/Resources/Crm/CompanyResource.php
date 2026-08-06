<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Filament\App\Resources\Crm\CompanyResource\Pages;
use App\Models\CrmCompany;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyResource extends Resource
{
    protected static ?string $model = CrmCompany::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información de la empresa')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')->label('Nombre')->required()->maxLength(200),
                                TextInput::make('industry')->label('Industria')->maxLength(100),
                                Select::make('size')->label('Tamaño')
                                    ->options(['micro' => 'Micro', 'small' => 'Pequeña', 'medium' => 'Mediana', 'large' => 'Grande', 'enterprise' => 'Enterprise'])
                                    ->native(false),
                                TextInput::make('website')->label('Sitio web')->url(),
                                TextInput::make('country')->label('País')->maxLength(2),
                                TextInput::make('city')->label('Ciudad')->maxLength(100),
                                TextInput::make('tax_id')->label('Identificación fiscal')->maxLength(50),
                                TextInput::make('phone')->label('Teléfono')->tel()->maxLength(50),
                            ]),
                        Textarea::make('notes')->label('Notas')->rows(3)->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->weight('medium'),
                TextColumn::make('industry')->label('Industria')->placeholder('—')->toggleable(),
                TextColumn::make('size')->label('Tamaño')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('city')->label('Ciudad')->placeholder('—'),
                TextColumn::make('country')->label('País')->placeholder('—')->toggleable(),
                TextColumn::make('contacts_count')->label('Contactos')->counts('contacts')->badge()->color('info'),
                TextColumn::make('deals_count')->label('Deals')->counts('deals')->badge()->color('primary'),
            ])
            ->filters([
                SelectFilter::make('size')->label('Tamaño')
                    ->options(['micro' => 'Micro', 'small' => 'Pequeña', 'medium' => 'Mediana', 'large' => 'Grande', 'enterprise' => 'Enterprise']),
                SelectFilter::make('industry')->label('Industria'),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('name', 'asc');
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}



