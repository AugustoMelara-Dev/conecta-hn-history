<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads;

use App\Filament\App\Resources\Leads\Schemas\LeadForm;
use App\Models\Lead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    public static function getNavigationLabel(): string
    {
        return 'Prospectos';
    }

    public static function getModelLabel(): string
    {
        return 'Prospecto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Prospectos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Lead::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Lead::class) ?? false;
    }

    public static function canEdit($record): bool
    {
        return $record instanceof Lead && (auth()->user()?->can('update', $record) ?? false);
    }

    public static function canDelete($record): bool
    {
        return $record instanceof Lead && (auth()->user()?->can('delete', $record) ?? false);
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->placeholder('Sin nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->placeholder('Sin correo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefono')
                    ->placeholder('Sin telefono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->placeholder('Consulta general')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => match ($state) {
                        'whatsapp_click' => 'WhatsApp',
                        'manual' => 'Manual',
                        'website' => 'Sitio web',
                        'catalog' => 'Catalogo',
                        null, '' => 'Manual',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => match ($state) {
                        'new' => 'Nuevo',
                        'contacted' => 'Contactado',
                        'closed' => 'Cerrado',
                        'lost' => 'Perdido',
                        default => 'Nuevo',
                    })
                    ->color('gray')
                    ->description('Solo lectura')
                    ->visible(fn (): bool => ! static::canMutateLeadStatus()),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Estado')
                    ->options([
                        'new' => 'Nuevo',
                        'contacted' => 'Contactado',
                        'closed' => 'Cerrado',
                        'lost' => 'Perdido',
                    ])
                    ->selectablePlaceholder(false)
                    ->visible(fn (): bool => static::canMutateLeadStatus()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'new' => 'Nuevo',
                        'contacted' => 'Contactado',
                        'closed' => 'Cerrado',
                        'lost' => 'Perdido',
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        'manual' => 'Manual',
                        'website' => 'Sitio web',
                        'catalog' => 'Catalogo',
                        'whatsapp_click' => 'WhatsApp',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'Prospectos',
                'heading' => 'Todavia no tienes prospectos',
                'description' => 'Aqui veras los contactos manuales y tambien las consultas que entren desde tus canales publicos.',
                'buttonLabel' => static::canCreate() ? 'Crear prospecto' : null,
                'buttonUrl' => static::canCreate() ? static::getUrl('create') : null,
            ]));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    private static function canMutateLeadStatus(): bool
    {
        return static::canCreate();
    }
}

