<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\Tenant;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Timeline interno';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer')->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Momento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'operations' => 'success',
                        'security' => 'danger',
                        'negocios' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'operations' => 'Operaciones',
                        'security' => 'Seguridad',
                        'negocios' => 'Negocio',
                        default => Str::headline((string) $state),
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Evento')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (Activity $record): string => (string) $record->description),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Detalle')
                    ->state(fn (Activity $record): string => self::summarizeProperties($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Ejecutado por')
                    ->default('Sistema'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->modalHeading('Detalle del evento')
                    ->modalContent(fn (Activity $record) => view('filament.resources.activity-log.view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin movimientos internos aun')
            ->emptyStateDescription('Aprobaciones, notas de soporte, pagos y cambios de plan apareceran aqui.')
            ->paginated([5, 10, 25]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Tenant
            && DatabaseSchema::hasTable(config('activitylog.table_name', 'activity_log'));
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        $count = 0;

        try {
            if ($ownerRecord instanceof Tenant) {
                $count = $ownerRecord->activityLogs()->count();
            }
        } catch (\Throwable) {
            $count = 0;
        }

        return Tab::make('Timeline interno')
            ->icon('heroicon-m-clock')
            ->badge($count > 0 ? $count : null)
            ->badgeColor($count > 0 ? 'info' : 'gray');
    }

    private static function summarizeProperties(Activity $record): string
    {
        $properties = $record->properties?->toArray() ?? [];
        $parts = [];

        $note = trim((string) Arr::get($properties, 'note', ''));
        if ($note !== '') {
            $parts[] = "Nota: {$note}";
        }

        $reason = trim((string) Arr::get($properties, 'reason', ''));
        if ($reason !== '') {
            $parts[] = "Motivo: {$reason}";
        }

        $duration = trim((string) Arr::get($properties, 'duration_label', ''));
        if ($duration !== '') {
            $parts[] = "Vigencia: {$duration}";
        }

        $plan = trim((string) Arr::get($properties, 'plan', ''));
        if ($plan !== '') {
            $parts[] = 'Plan: '.Str::upper($plan);
        }

        $paymentReference = trim((string) Arr::get($properties, 'payment_reference', ''));
        if ($paymentReference !== '') {
            $parts[] = "Referencia: {$paymentReference}";
        }

        $paymentAmount = trim((string) Arr::get($properties, 'payment_amount', ''));
        if ($paymentAmount !== '') {
            $parts[] = "Monto: {$paymentAmount}";
        }

        $targetUser = trim((string) Arr::get($properties, 'target_user_email', ''));
        if ($targetUser !== '') {
            $parts[] = "Cuenta: {$targetUser}";
        }

        $changedAttributes = array_keys((array) Arr::get($properties, 'attributes', []));
        if ($parts === [] && $changedAttributes !== []) {
            $fields = collect($changedAttributes)
                ->reject(fn (string $field): bool => in_array($field, ['updated_at', 'created_at'], true))
                ->take(4)
                ->map(fn (string $field): string => Str::headline(str_replace('_', ' ', $field)))
                ->implode(', ');

            if ($fields !== '') {
                $parts[] = "Campos: {$fields}";
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Sin detalles adicionales.';
    }
}
