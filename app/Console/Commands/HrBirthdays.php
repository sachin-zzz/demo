<?php

namespace App\Console\Commands;

use App\Models\HR\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HrBirthdays extends Command
{
    protected $signature = 'hr:birthdays {--days=7 : Number of days ahead to look}';

    protected $description = 'List employees with upcoming birthdays.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $upcoming = Employee::query()
            ->whereNotNull('date_of_birth')
            ->where('is_active', true)
            ->get(['id', 'name', 'job_title', 'date_of_birth'])
            ->map(function (Employee $employee) use ($days): ?array {
                $birthday = Carbon::parse($employee->date_of_birth)->setYear(now()->year);

                if ($birthday->lt(now()->startOfDay())) {
                    $birthday->addYear();
                }

                if ($birthday->gt(now()->addDays($days)->endOfDay())) {
                    return null;
                }

                $daysUntil = (int) now()->startOfDay()->diffInDays($birthday->startOfDay());
                $turningAge = Carbon::parse($employee->date_of_birth)->age + 1;
                $isToday = $daysUntil === 0;

                return [
                    'name' => $isToday ? $employee->name . ' 🎂' : $employee->name,
                    'job_title' => $employee->job_title,
                    'birthday' => $birthday->format('F j'),
                    'turning' => $turningAge . ' years old',
                    'days_away' => $isToday ? 'Today! 🎂' : $daysUntil . ' day' . ($daysUntil === 1 ? '' : 's'),
                ];
            })
            ->filter()
            ->sortBy('days_away')
            ->values();

        if ($upcoming->isEmpty()) {
            $this->info("No upcoming birthdays in the next {$days} days.");

            return self::SUCCESS;
        }

        $this->info("Upcoming birthdays in the next {$days} days:");
        $this->newLine();

        $this->table(
            ['Name', 'Job Title', 'Birthday', 'Turning', 'Days Away'],
            $upcoming->toArray(),
        );

        return self::SUCCESS;
    }
}
