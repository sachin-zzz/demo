<?php

namespace App\Filament\Widgets;

use App\Models\Shop\Product;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockAlert extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 8;

    protected static ?string $heading = 'Low Stock Alert';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('qty', '<', 10)
                    ->with(['brand', 'productCategories'])
                    ->orderBy('qty', 'asc')
            )
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('qty')
                    ->label('Stock')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color(fn (Product $record): string => $record->qty === 0 ? 'danger' : 'warning'),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productCategories.name')
                    ->label('Categories')
                    ->badge(),
                TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
            ]);
    }
}
