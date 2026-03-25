<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Models\CustomerNote;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notas del cliente';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('note_type')
                    ->label('Tipo')
                    ->options([
                        'general' => 'General',
                        'follow_up' => 'Seguimiento',
                        'service' => 'Servicio',
                        'sale' => 'Venta',
                        'inbound_reply' => 'Respuesta',
                    ])
                    ->default('general')
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('body')
                    ->label('Nota')
                    ->rows(5)
                    ->required()
                    ->maxLength(5000),
                Forms\Components\DateTimePicker::make('next_action_at')
                    ->label('Siguiente toque')
                    ->seconds(false),
                Forms\Components\Toggle::make('pin_note')
                    ->label('Fijar nota')
                    ->dehydrated(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('pinned_at', 'desc')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('note_type_label')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (CustomerNote $record): string => match ($record->note_type) {
                        'follow_up' => 'warning',
                        'service' => 'info',
                        'sale' => 'success',
                        'inbound_reply' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('body')
                    ->label('Nota')
                    ->wrap()
                    ->limit(120),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Responsable')
                    ->default('Sistema')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_action_at')
                    ->label('Siguiente toque')
                    ->since()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar nota')
                    ->mutateDataUsing(function (array $data): array {
                        $data['tenant_id'] = (string) $this->getOwnerRecord()->tenant_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['pinned_at'] = ! empty($data['pin_note']) ? now() : null;
                        unset($data['pin_note']);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->fillForm(fn (CustomerNote $record): array => [
                        'note_type' => $record->note_type,
                        'body' => $record->body,
                        'next_action_at' => $record->next_action_at,
                        'pin_note' => filled($record->pinned_at),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        $data['tenant_id'] = (string) $this->getOwnerRecord()->tenant_id;
                        $data['pinned_at'] = ! empty($data['pin_note']) ? now() : null;
                        unset($data['pin_note']);

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
