<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TopPostsByViewsChart;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class BlogDashboard extends BaseDashboard
{
    protected static string $routePath = 'blog';

    protected static ?string $title = 'Blog Dashboard';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 4;

    public function getWidgets(): array
    {
        return [
            TopPostsByViewsChart::class,
        ];
    }
}
