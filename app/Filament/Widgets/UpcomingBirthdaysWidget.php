<?php

namespace App\Filament\Widgets;

use App\Models\HR\Employee;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UpcomingBirthdaysWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->upcomingBirthdaysQuery())
            ->defaultPaginationPageOption(5)
            ->heading('Upcoming Birthdays')
            ->description('Employees with birthdays in the next 7 days')
            ->emptyStateHeading('No upcoming birthdays')
            ->emptyStateIcon(Heroicon::Cake)
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->formatStateUsing(function (Employee $record): string {
                        $birthday = Carbon::parse($record->date_of_birth)->setYear(now()->year);

                        if ($birthday->lt(now()->startOfDay())) {
                            $birthday->addYear();
                        }

                        $isToday = now()->startOfDay()->diffInDays($birthday->startOfDay()) === 0;

                        return $isToday ? $record->name . ' 🎂' : $record->name;
                    }),

                TextColumn::make('date_of_birth')
                    ->label('Birthday')
                    ->date('F j')
                    ->sortable(),

                TextColumn::make('age')
                    ->label('Turning')
                    ->state(fn (Employee $record): string => Carbon::parse($record->date_of_birth)->age + 1 . ' years old')
                    ->color('gray'),

                TextColumn::make('days_until_birthday')
                    ->label('Days Away')
                    ->state(function (Employee $record): string {
                        $birthday = Carbon::parse($record->date_of_birth)->setYear(now()->year);

                        if ($birthday->lt(now()->startOfDay())) {
                            $birthday->addYear();
                        }

                        $days = (int) now()->startOfDay()->diffInDays($birthday->startOfDay());

                        return $days === 0 ? 'Today! 🎂' : $days . ' day' . ($days === 1 ? '' : 's');
                    })
                    ->badge()
                    ->color(fn (Employee $record): string => $this->getBadgeColor($record)),

                TextColumn::make('job_title')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('department.name')
                    ->toggleable(),
            ]);
    }

    /** @return Builder<Employee> */
    private function upcomingBirthdaysQuery(): Builder
    {
        $ids = Employee::query()
            ->whereNotNull('date_of_birth')
            ->where('is_active', true)
            ->get(['id', 'date_of_birth'])
            ->filter(function (Employee $employee): bool {
                $birthday = Carbon::parse($employee->date_of_birth)->setYear(now()->year);

                if ($birthday->lt(now()->startOfDay())) {
                    $birthday->addYear();
                }

                return $birthday->lte(now()->addDays(7)->endOfDay());
            })
            ->pluck('id')
            ->all();

        return Employee::query()->whereIn('id', $ids)->with('department');
    }

    private function getBadgeColor(Employee $record): string
    {
        $birthday = Carbon::parse($record->date_of_birth)->setYear(now()->year);

        if ($birthday->lt(now()->startOfDay())) {
            $birthday->addYear();
        }

        $days = (int) now()->startOfDay()->diffInDays($birthday->startOfDay());

        return match (true) {
            $days === 0 => 'success',
            $days <= 3 => 'warning',
            default => 'info',
        };
    }
}
