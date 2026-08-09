<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\RelationManagers;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Actividades';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'call' => 'Llamada',
                        'email' => 'Email',
                        'meeting' => 'Reunión',
                        'task' => 'Tarea',
                        'note' => 'Nota',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                    ])
                    ->required()
                    ->native(false),
                TextInput::make('subject')
                    ->label('Asunto')
                    ->maxLength(200),
                Textarea::make('body')
                    ->label('Descripción')
                    ->rows(3),
                DateTimePicker::make('due_at')
                    ->label('Fecha límite'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('subject')
                    ->label('Asunto'),
                TextColumn::make('due_at')
                    ->label('Vence')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('completed_at')
                    ->label('Completada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        [$tenant, $actor] = $this->authority();
                        $contact = $this->ownerContact();

                        return app(CanonicalCrmEntityWriter::class)->createActivity(
                            (string) $tenant->getKey(),
                            (string) $actor->getKey(),
                            $this->activityData($data, $contact),
                        );
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(function (array $data, CrmActivity $record): Model {
                        [$tenant, $actor] = $this->authority();
                        $contact = $this->ownerContact();

                        return app(CanonicalCrmEntityWriter::class)->updateActivity(
                            (string) $tenant->getKey(),
                            (string) $actor->getKey(),
                            (string) $record->getKey(),
                            $this->activityData($data, $contact),
                        );
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return array{Tenant, User} */
    private function authority(): array
    {
        $tenant = Filament::getTenant();
        $actor = Filament::auth()->user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new \RuntimeException('CRM activity relation mutation requires tenant and actor context.');
        }

        return [$tenant, $actor];
    }

    private function ownerContact(): Contact
    {
        $contact = $this->getOwnerRecord();
        if (! $contact instanceof Contact) {
            throw new \RuntimeException('CRM activity relation requires a contact owner record.');
        }

        return $contact;
    }

    /** @param array<string, mixed> $data */
    private function activityData(array $data, Contact $contact): array
    {
        if (array_key_exists('tenant_id', $data)) {
            throw new \InvalidArgumentException('Tenant ownership is controlled by Filament context.');
        }

        if (array_key_exists('contact_id', $data)
            && (string) $data['contact_id'] !== (string) $contact->getKey()) {
            throw ValidationException::withMessages([
                'contact_id' => ['The activity must belong to its parent contact.'],
            ]);
        }

        unset($data['tenant_id'], $data['contact_id']);

        return ['contact_id' => (string) $contact->getKey(), ...$data];
    }
}
