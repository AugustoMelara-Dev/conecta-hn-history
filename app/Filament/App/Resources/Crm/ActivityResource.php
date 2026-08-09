<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm;

use App\Domain\Crm\Enums\ActivityType;
use App\Filament\App\Resources\Crm\ActivityResource\Pages;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class ActivityResource extends Resource
{
    protected static ?string $model = CrmActivity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Actividades';

    protected static ?string $modelLabel = 'actividad';

    protected static ?string $pluralModelLabel = 'Actividades';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Select::make('type')
                                    ->label('Tipo')
                                    ->options(ActivityType::options())
                                    ->required()
                                    ->native(false)
                                    ->live(),
                                TextInput::make('subject')
                                    ->label('Asunto')
                                    ->maxLength(200),
                                Select::make('contact_id')
                                    ->label('Contacto')
                                    ->options(fn (): array => self::contactOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                                Select::make('deal_id')
                                    ->label('Deal')
                                    ->options(fn (): array => self::dealOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                                Select::make('owner_id')
                                    ->label('Responsable')
                                    ->options(fn (): array => self::ownerOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                                DateTimePicker::make('due_at')
                                    ->label('Fecha límite')
                                    ->timezone('America/Tegucigalpa')
                                    ->displayFormat('d/m/Y H:i'),
                                Select::make('priority')
                                    ->label('Prioridad')
                                    ->options([1 => 'Alta', 2 => 'Media', 3 => 'Baja'])
                                    ->default(3),
                            ]),
                        Textarea::make('body')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ActivityType::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => ActivityType::tryFrom($state)?->color() ?? 'gray'),
                TextColumn::make('subject')->label('Asunto')->searchable()->placeholder('—'),
                TextColumn::make('contact.display_name')->label('Contacto')->placeholder('—'),
                TextColumn::make('deal.title')->label('Deal')->placeholder('—'),
                TextColumn::make('owner.name')->label('Responsable')->placeholder('—'),
                TextColumn::make('due_at')->label('Vence')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('completed_at')->label('Completada')->dateTime('d/m/Y H:i')->placeholder('Pendiente'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(ActivityType::options()),
                SelectFilter::make('owner_id')->label('Responsable')->options(fn (): array => self::ownerOptions()),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $query) => $query->overdue())
                    ->toggle(),
                Tables\Filters\Filter::make('pending')
                    ->label('Pendientes')
                    ->query(fn (Builder $query) => $query->pending())
                    ->toggle(),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('due_at', 'asc');
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

    /** @return array<string, string> */
    private static function contactOptions(): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        return Contact::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Contact $contact): array => [
                (string) $contact->getKey() => $contact->display_name,
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function dealOptions(): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        return CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /** @return array<string, string> */
    private static function ownerOptions(): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        $query = User::query()
            ->where(function (Builder $users) use ($tenant): void {
                $users->where('users.id', $tenant->user_id);

                if (! DatabaseSchema::hasTable('tenant_memberships')) {
                    return;
                }

                $eligibleMemberships = DB::table('tenant_memberships')
                    ->select('user_id')
                    ->where('tenant_id', $tenant->getKey())
                    ->where('status', 'active')
                    ->whereNotNull('accepted_at')
                    ->whereNull('suspended_at')
                    ->whereNull('revoked_at')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) = 1');

                $users->orWhereIn('users.id', $eligibleMemberships);
            })
            ->orderBy('name');

        return $query->pluck('name', 'id')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'create' => Pages\CreateActivity::route('/create'),
            'edit' => Pages\EditActivity::route('/{record}/edit'),
        ];
    }
}
