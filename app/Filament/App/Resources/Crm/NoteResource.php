<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Filament\App\Resources\Crm\NoteResource\Pages;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NoteResource extends Resource
{
    protected static ?string $model = \App\Models\CustomerNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Notas';

    protected static ?string $modelLabel = 'nota';

    protected static ?string $pluralModelLabel = 'Notas';

    protected static ?int $navigationSort = 14;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detalles')->schema([
                    Grid::make(2)->schema([
                        Select::make('contact_id')
                            ->label('Contacto')
                            ->relationship('contact', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($r) => $r->display_name ?? '—')
                            ->searchable(['first_name', 'last_name', 'email'])
                            ->preload(),
                        Select::make('deal_id')
                            ->label('Deal')
                            ->relationship('deal', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('created_by')
                            ->label('Autor')
                            ->relationship('creator', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('type')
                            ->label('Tipo')
                            ->options(['general' => 'General', 'call_summary' => 'Resumen de llamada', 'meeting' => 'Reunión', 'follow_up' => 'Seguimiento'])
                            ->default('general'),
                    ]),
                    Textarea::make('body')
                        ->label('Contenido')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact.display_name')->label('Contacto')->placeholder('—')->searchable(),
                TextColumn::make('deal.name')->label('Deal')->placeholder('—'),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('body')->label('Contenido')->limit(80)->wrap(),
                TextColumn::make('creator.name')->label('Autor')->placeholder('—'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(['general' => 'General', 'call_summary' => 'Resumen de llamada', 'meeting' => 'Reunión', 'follow_up' => 'Seguimiento']),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListNotes::route('/'),
            'create' => Pages\CreateNote::route('/create'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }
}



