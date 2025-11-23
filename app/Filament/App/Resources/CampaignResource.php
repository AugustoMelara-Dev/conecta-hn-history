<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';
    
    protected static ?string $navigationLabel = 'Campañas';
    
    protected static ?string $modelLabel = 'Campaña';
    
    protected static ?string $pluralModelLabel = 'Campañas';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Marketing PRO';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Detalles de la Campaña')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: ¡Black Friday! 50% de descuento'),
                        
                        Forms\Components\RichEditor::make('content')
                            ->label('Contenido')
                            ->required()
                            ->placeholder('Escribe tu mensaje aquí...')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                            ]),
                    ]),
                
                Forms\Components\Section::make('Estadísticas')
                    ->schema([
                        Forms\Components\Placeholder::make('recipients_count')
                            ->label('Destinatarios')
                            ->content(fn (?Campaign $record) => $record ? $record->recipients_count . ' seguidores' : 'Se calculará al enviar'),
                        
                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Fecha de envío')
                            ->content(fn (?Campaign $record) => $record && $record->sent_at ? $record->sent_at->format('d/m/Y H:i') : 'Aún no enviada'),
                    ])
                    ->columns(2)
                    ->hidden(fn (string $operation) => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('recipients_count')
                    ->label('Destinatarios')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ? $state->format('d/m/Y H:i') : 'Pendiente'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('send')
                    ->label('Enviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar Campaña')
                    ->modalDescription(fn (Campaign $record) => 'Se enviará esta campaña a todos tus seguidores. ¿Estás seguro?')
                    ->visible(fn (Campaign $record) => !$record->sent_at)
                    ->action(function (Campaign $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $followersCount = $tenant->followers()->count();
                        
                        if ($followersCount === 0) {
                            Notification::make()
                                ->title('Sin seguidores')
                                ->warning()
                                ->body('No tienes seguidores para enviar esta campaña.')
                                ->send();
                            return;
                        }
                        
                        // Dispatch the job to send emails
                        \App\Jobs\SendCampaignEmail::dispatch($record);
                        
                        // Update campaign status
                        $record->update([
                            'sent_at' => now(),
                            'recipients_count' => $followersCount,
                        ]);
                        
                        Notification::make()
                            ->title('Campaña Enviada')
                            ->success()
                            ->body("Se envió la campaña a {$followersCount} seguidores.")
                            ->send();
                    }),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn (Campaign $record) => !$record->sent_at),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay campañas')
            ->emptyStateDescription('Crea tu primera campaña de marketing para conectar con tus seguidores.')
            ->emptyStateIcon('heroicon-o-megaphone');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\App\Resources\CampaignResource\Pages\ListCampaigns::route('/'),
            'create' => \App\Filament\App\Resources\CampaignResource\Pages\CreateCampaign::route('/create'),
            'edit' => \App\Filament\App\Resources\CampaignResource\Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
