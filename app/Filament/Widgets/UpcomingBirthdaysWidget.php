<?php

namespace App\Filament\Widgets;

use App\Models\HR\Employee;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class UpcomingBirthdaysWidget extends BaseWidget
{
    protected static ?string $heading = 'Upcoming Birthdays';

    protected static ?string $description = 'Active employees with birthdays in the next 7 days';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 13;

    protected bool $paginated = false;

    public function table(Table $table): Table
    {
        $dates = collect(range(0, 7))
            ->map(fn (int $i): string => now()->addDays($i)->format('m-d'))
            ->toArray();

        $formatExpr = $this->birthdayFormatExpression();

        return $table
            ->query(
                Employee::query()
                    ->whereNotNull('date_of_birth')
                    ->where('is_active', true)
                    ->whereIn(DB::raw($formatExpr), $dates)
                    ->with('department')
                    ->orderByRaw(
                        "CASE WHEN {$formatExpr} >= {$this->todayFormatExpression()} THEN 0 ELSE 1 END,
                         {$formatExpr} ASC"
                    )
            )
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->searchable(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—'),

                TextColumn::make('birthday')
                    ->label('Birthday')
                    ->state(fn (Employee $record): string => $record->date_of_birth->format('M j')),

                TextColumn::make('turning')
                    ->label('Turning')
                    ->state(function (Employee $record): string {
                        $today = now()->startOfDay();
                        $birthday = $record->date_of_birth->copy()->year($today->year);

                        if ($birthday->lt($today)) {
                            $birthday->addYear();
                        }

                        return ($birthday->year - $record->date_of_birth->year) . ' yrs';
                    }),

                TextColumn::make('when')
                    ->label('When')
                    ->state(function (Employee $record): string {
                        $today = now()->startOfDay();
                        $birthday = $record->date_of_birth->copy()->year($today->year);

                        if ($birthday->lt($today)) {
                            $birthday->addYear();
                        }

                        $days = (int) $today->diffInDays($birthday);

                        return $days === 0 ? 'Today!' : 'In ' . $days . ($days === 1 ? ' day' : ' days');
                    })
                    ->weight(FontWeight::Bold),
            ])
            ->emptyStateHeading('No upcoming birthdays')
            ->emptyStateDescription('No active employees have birthdays in the next 7 days.');
    }

    private function birthdayFormatExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%m-%d', date_of_birth)"
            : "TO_CHAR(date_of_birth, 'MM-DD')";
    }

    private function todayFormatExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%m-%d', 'now')"
            : "TO_CHAR(CURRENT_DATE, 'MM-DD')";
    }
}
