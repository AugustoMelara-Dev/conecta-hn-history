<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\RelationManagers;

use App\Filament\App\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\CustomerTask;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DealTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tareas de la oportunidad';

    protected static ?string $modelLabel = 'Tarea';

    protected static ?string $pluralModelLabel = 'Tareas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->label('Titulo')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('body')
                    ->label('Contexto')
                    ->rows(4),
                Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('task_type')
                            ->label('Tipo')
                            ->options([
                                'follow_up' => 'Seguimiento',
                                'sale' => 'Venta',
                                'reactivation' => 'Reactivacion',
                                'retention' => 'Retencion',
                            ])
                            ->default('follow_up')
                            ->native(false)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'open' => 'Abierta',
                                'in_progress' => 'En curso',
                                'waiting_customer' => 'Esperando cliente',
                                'completed' => 'Completada',
                                'cancelled' => 'Cancelada',
                            ])
                            ->default('open')
                            ->native(false)
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->label('Prioridad')
                            ->options([
                                'urgent' => 'Urgente',
                                'high' => 'Alta',
                                'normal' => 'Normal',
                                'low' => 'Baja',
                            ])
                            ->default('normal')
                            ->native(false)
                            ->required(),
                    ]),
                Grid::make(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('due_at')
                            ->label('Proxima accion')
                            ->seconds(false),
                        Forms\Components\DateTimePicker::make('sla_due_at')
                            ->label('SLA')
                            ->seconds(false),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sla_due_at', 'asc')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tarea')
                    ->wrap()
                    ->description(fn (CustomerTask $record): string => (string) ($record->body ?: $record->task_type_label)),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('priority_label')
                    ->label('Prioridad')
                    ->badge(),
                Tables\Columns\TextColumn::make('sla_label')
                    ->label('SLA')
                    ->badge()
                    ->color(fn (CustomerTask $record): string => $record->sla_color),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Siguiente toque')
                    ->since()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva tarea')
                    ->visible(fn (): bool => $this->canManageDeal())
                    ->mutateDataUsing(fn (array $data): array => $this->stampDealData($data)),
            ])
            ->actions([
                Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CustomerTask $record): bool => $this->canManageDeal() && ! in_array($record->status, ['completed', 'cancelled'], true))
                    ->action(function (CustomerTask $record): void {
                        $record->forceFill([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ])->save();

                        Notification::make()
                            ->title('Tarea completada')
                            ->body('La tarea quedo cerrada en la oportunidad.')
                            ->color('success')
                            ->send();
                    }),
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
