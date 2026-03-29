<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Application\TenantGovernance\CustomerProfiles\Commands\CompleteCustomerTaskCommand;
use App\Application\TenantGovernance\CustomerProfiles\Handlers\CompleteCustomerTaskHandler;
use App\Models\CustomerTask;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tareas y SLA';

    protected static ?string $modelLabel = 'Tarea';

    protected static ?string $pluralModelLabel = 'Tareas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titulo')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('body')
                    ->label('Contexto')
                    ->rows(4),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('task_type')
                            ->label('Tipo')
                            ->options([
                                'follow_up' => 'Seguimiento',
                                'sale' => 'Venta',
                                'service' => 'Servicio',
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
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('assigned_user_id')
                            ->label('Owner')
                            ->options(\App\Filament\App\Resources\CustomerProfileResource::teamOptions())
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('location_id')
                            ->label('Sede')
                            ->options(\App\Filament\App\Resources\CustomerProfileResource::locationOptions())
                            ->searchable()
                            ->native(false),
                        Forms\Components\TextInput::make('source_channel')
                            ->label('Canal')
                            ->placeholder('whatsapp, email, mostrador'),
                    ]),
                Forms\Components\Grid::make(2)
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
                    ->badge()
                    ->color(fn (CustomerTask $record): string => match ($record->status) {
                        'completed' => 'gray',
                        'waiting_customer' => 'info',
                        'in_progress' => 'warning',
                        'cancelled' => 'danger',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('priority_label')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (CustomerTask $record): string => match ($record->priority) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'low' => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('sla_label')
                    ->label('SLA')
                    ->badge()
                    ->color(fn (CustomerTask $record): string => $record->sla_color),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Owner')
                    ->default('Sin owner')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Sede')
                    ->default('Sin sede')
                    ->toggleable(),
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
                    ->mutateDataUsing(function (array $data): array {
                        $data['tenant_id'] = (string) $this->getOwnerRecord()->tenant_id;
                        $data['created_by_user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CustomerTask $record): bool => ! in_array($record->status, ['completed', 'cancelled'], true))
                    ->action(function (CustomerTask $record): void {
                        $updated = app(CompleteCustomerTaskHandler::class)->handle(
                            new CompleteCustomerTaskCommand(
                                tenantId: (string) $record->tenant_id,
                                taskId: (int) $record->getKey(),
                                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
                            ),
                        );

                        if (! $updated instanceof CustomerTask) {
                            Notification::make()
                                ->title('No se pudo completar')
                                ->danger()
                                ->body('La tarea ya no existe dentro de este negocio.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Tarea completada')
                            ->success()
                            ->body('La tarea ya quedo cerrada en el workspace del cliente.')
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
