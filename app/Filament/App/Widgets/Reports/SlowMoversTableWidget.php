<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass SlowMoversTableWidget extends TableWidget
{
    protected static ?string $heading = 'Productos lentos';
    protected static ?int $sort = 9;

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = \App\Models\Product::query();
        if ($tenantId) $query->where('tenant_id', $tenantId)->where('stock', '>', 0)->orderBy('last_sale_at', 'asc')->limit(10);

        return $table->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->label('Producto'),
                \Filament\Tables\Columns\TextColumn::make('stock')->label('Stock'),
            ]);
    }
}\n
