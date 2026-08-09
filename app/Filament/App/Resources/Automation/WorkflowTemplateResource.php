<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation;

use App\Filament\App\Resources\Automation\WorkflowTemplateResource\Pages;
use App\Models\WorkflowDefinition;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * WorkflowTemplateResource - Catálogo de templates pre-armados.
 *
 * Click en un template lo clona a un Workflow del tenant actual.
 */
class WorkflowTemplateResource extends Resource
{
    protected static ?string $model = WorkflowDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'template';

    protected static ?string $pluralModelLabel = 'Templates';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información')->components([
                    Grid::make(2)->components([
                        TextInput::make('name')->label('Nombre')->required()->maxLength(200),
                        TextInput::make('slug')->label('Slug')->required()->maxLength(100)->unique(ignoreRecord: true),
                        TextInput::make('category')->label('Categoría')->maxLength(50),
                        TextInput::make('description')->label('Descripción')->maxLength(500),
                    ]),
                    Textarea::make('graph_json')
                        ->label('Graph JSON')
                        ->rows(15)
                        ->required()
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->weight('medium'),
                TextColumn::make('category')->label('Categoría')->badge(),
                TextColumn::make('description')->label('Descripción')->limit(80),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categoría'),
            ])
            ->actions([
                Action::make('clone_to_tenant')
                    ->label('Clonar a mi tenant')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->action(function ($record) {
                        $tenantId = Filament::getTenant()?->getKey();
                        if (! $tenantId) {
                            Notification::make()->title('No tenant context')->danger()->send();

                            return;
                        }
                        $clone = $record->replicate();
                        $clone->tenant_id = $tenantId;
                        $clone->name = $record->name;
                        $clone->is_active = false;
                        $clone->status = 'draft';
                        $clone->save();
                        Notification::make()->title('Template clonado')->success()->send();
                    }),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('category');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('tenant_id'); // Templates globales
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowTemplates::route('/'),
            'create' => Pages\CreateWorkflowTemplate::route('/create'),
            'edit' => Pages\EditWorkflowTemplate::route('/{record}/edit'),
        ];
    }
}
