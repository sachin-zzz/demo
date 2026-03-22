<?php

use App\Filament\Widgets\LowStockAlerts;
use App\Models\Shop\Product;
use Livewire\Livewire;

it('renders the low stock alerts widget', function () {
    $lowStockProduct = Product::factory()->create(['qty' => 5]);
    $inStockProduct = Product::factory()->create(['qty' => 20]);

    Livewire::test(LowStockAlerts::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Product::where('qty', '<', 10)->get())
        ->assertCanNotSeeTableRecords(Product::where('qty', '>=', 10)->get());
});

it('shows products with stock below 10', function () {
    Product::factory()->create(['qty' => 0]);
    Product::factory()->create(['qty' => 9]);
    Product::factory()->create(['qty' => 10]);

    Livewire::test(LowStockAlerts::class)
        ->assertOk()
        ->assertCountTableRecords(2);
});
