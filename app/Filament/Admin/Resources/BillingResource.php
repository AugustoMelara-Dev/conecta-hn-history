<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BillingResource\Pages;
use App\Models\SubscriptionPayment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillingResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Billing global';
    protected static ?string $modelLabel = 'pago';
    protected static ?string $pluralModelLabel = 'Pagos';
    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Tenant')->searchable()->sortable(),
                TextColumn::make('amount')->label('Monto')->money('HNL'),
                TextColumn::make('currency')->label('Moneda'),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBilling::route('/')];
    }
}
