<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmPipelineResource\RelationManagers;

use App\Filament\App\Resources\CrmPipelineResource;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'Etapas';

    protected static ?string $modelLabel = 'Etapa';

    protected static ?string $pluralModelLabel = 'Etapas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('key')
                    ->label('Llave')
                    ->maxLength(120),
                Grid::make(4)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                CrmStage::TYPE_OPEN => 'Abierta',
                                CrmStage::TYPE_WON => 'Ganada',
                                CrmStage::TYPE_LOST => 'Perdida',
                            ])
                            ->default(CrmStage::TYPE_OPEN)
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('probability')
                            ->label('Probabilidad')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Principal')
                            ->default(false),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->integer()
                            ->default(0),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Etapa')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => match ($state) {
                        CrmStage::TYPE_WON => 'Ganada',
                        CrmStage::TYPE_LOST => 'Perdida',
                        default => 'Abierta',
                    }),
                Tables\Columns\TextColumn::make('probability')
                    ->label('Prob.')
                    ->suffix('%'),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Principal')
                    ->boolean(),
                Tables\Columns\TextColumn::make('deals_count')
                    ->label('Oportunidades')
                    ->counts('deals'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar etapa')
                    ->visible(fn (): bool => $this->canManagePipeline())
                    ->mutateDataUsing(fn (array $data): array => $this->stampStageData($data))
                    ->after(fn (CrmStage $record): mixed => $this->normalizeDefaultStage($record)),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => $this->canManagePipeline())
                    ->mutateDataUsing(fn (array $data): array => $this->stampStageData($data))
                    ->after(fn (CrmStage $record): mixed => $this->normalizeDefaultStage($record)),
                DeleteAction::make()
                    ->visible(fn (CrmStage $record): bool => $this->canDeleteStage($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stampStageData(array $data): array
    {
        $pipeline = $this->pipeline();

        if (! $pipeline instanceof CrmPipeline) {
            return $data;
        }

        $data['tenant_id'] = (string) $pipeline->tenant_id;
        $data['crm_pipeline_id'] = $pipeline->getKey();
        $data['key'] = Str::slug((string) ($data['key'] ?: $data['name']));

        return $data;
    }

    private function canManagePipeline(): bool
    {
        $pipeline = $this->pipeline();

        return $pipeline instanceof CrmPipeline && CrmPipelineResource::canEdit($pipeline);
    }

    private function canDeleteStage(CrmStage $stage): bool
    {
        return $this->canManagePipeline()
            && ! (bool) $stage->is_default
            && ! $stage->deals()->exists();
    }

    private function normalizeDefaultStage(CrmStage $stage): mixed
    {
        if ((bool) $stage->is_default) {
            CrmStage::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $stage->tenant_id)
                ->where('crm_pipeline_id', $stage->crm_pipeline_id)
                ->whereKeyNot($stage->getKey())
                ->update(['is_default' => false]);
        }

        return null;
    }

    private function pipeline(): ?CrmPipeline
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof CrmPipeline ? $owner : null;
    }
}
