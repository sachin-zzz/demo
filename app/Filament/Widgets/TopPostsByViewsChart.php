<?php

namespace App\Filament\Widgets;

use App\Models\Blog\Post;
use Filament\Widgets\ChartWidget;

class TopPostsByViewsChart extends ChartWidget
{
    protected ?string $heading = 'Top 5 Most-Viewed Posts';

    protected static ?int $sort = 0;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $posts = Post::query()
            ->where('view_count', '>', 0)
            ->orderByDesc('view_count')
            ->limit(5)
            ->get(['title', 'view_count']);

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => $posts->pluck('view_count')->all(),
                    'backgroundColor' => '#8b5cf6',
                ],
            ],
            'labels' => $posts->pluck('title')->map(fn (string $title) => strlen($title) > 30 ? substr($title, 0, 30) . '...' : $title)->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
        ];
    }
}
