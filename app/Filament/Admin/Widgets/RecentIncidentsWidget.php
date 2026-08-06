<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class RecentIncidentsWidget extends TableWidget
{
    protected static ?string $heading = 'Incidentes recientes';
    protected static ?int $sort = 6;

    public function table(Tables\Table $table): Tables\Table
    {
        $data = DB::table('activity_log')
            ->where('log_name', 'incident')
            ->orderBy('created_at', 'desc')->limit(10)->get();

        return $table
            ->records($data)
            ->columns([
                TextColumn::make('description')->label('Título'),
                TextColumn::make('event')->label('Severidad')->badge(),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
            ]);
    }
}
