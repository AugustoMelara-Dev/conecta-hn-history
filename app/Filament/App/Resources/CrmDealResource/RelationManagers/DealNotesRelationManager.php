<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\RelationManagers;

use App\Filament\App\Resources\CrmDealResource;
use App\Models\CrmDeal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DealNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notas de la oportunidad';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('note_type')
                    ->label('Tipo')
                    ->options([
                        'general' => 'General',
                        'follow_up' => 'Seguimiento',
                        'sale' => 'Venta',
                        'inbound_reply' => 'Respuesta',
                    ])
                    ->default('follow_up')
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
                Forms\Components\Select::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'urgent' => 'Urgente',
                        'high' => 'Alta',
                        'normal' => 'Normal',
                        'low' => 'Baja',
                    ])
                    ->default('normal')
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('note_type_label')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('body')
                    ->label('Nota')
                    ->wrap()
                    ->limit(140),
                Tables\Columns\TextColumn::make('priority_label')
                    ->label('Prioridad')
                    ->badge(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Responsable')
                    ->default('Sistema')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_action_at')
                    ->label('Siguiente toque')
                    ->since()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrada')
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar nota')
                    ->visible(fn (): bool => $this->canManageDeal())
                    ->mutateDataUsing(fn (array $data): array => $this->stampDealData($data)),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => $this->canManageDeal())
                    ->mutateDataUsing(fn (array $data): array => $this->stampDealData($data)),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->canManageDeal()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->canManageDeal()),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampDealData(array $data): array
    {
        $deal = $this->deal();

        if (! $deal instanceof CrmDeal) {
            return $data;
        }

        $data['tenant_id'] = (string) $deal->tenant_id;
        $data['customer_profile_id'] = $deal->customer_profile_id;
        $data['crm_deal_id'] = $deal->getKey();
        $data['created_by_user_id'] = auth()->id();
        $data['priority'] = (string) ($data['priority'] ?? 'normal');

        return $data;
    }

    private function canManageDeal(): bool
    {
        $deal = $this->deal();

        return $deal instanceof CrmDeal && CrmDealResource::canEdit($deal);
    }

    private function deal(): ?CrmDeal
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof CrmDeal ? $owner : null;
    }
}
