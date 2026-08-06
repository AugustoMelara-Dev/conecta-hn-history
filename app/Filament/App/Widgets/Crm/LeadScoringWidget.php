<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Crm;

use App\Domain\Crm\Enums\LifecycleStage;
use App\Models\Contact;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LeadScoringWidget extends TableWidget
{
    protected static ?string $heading = 'Hot leads (score > 70)';
    protected static ?int $sort = 20;

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $service = new \App\Application\Crm\LeadScoringService();

        $query = Contact::query()
            ->where('tenant_id', $tenantId ?? '0')
            ->whereIn('lifecycle_stage', [
                LifecycleStage::Lead->value,
                LifecycleStage::Qualified->value,
                LifecycleStage::Proposal->value,
            ]);

        return $table->query($query)
            ->columns([
                TextColumn::make('display_name')->label('Contacto')->searchable(),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('lifecycle_stage')->label('Etapa')->badge(),
                TextColumn::make('score')
                    ->label('Score')
                    ->getStateUsing(fn (Contact $r) => $service->score($r)->value)
                    ->numeric(),
            ])
            ->defaultSort('last_contact_at', 'desc');
    }
}
