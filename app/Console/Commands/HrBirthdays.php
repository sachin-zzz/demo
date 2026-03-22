<?php

namespace App\Console\Commands;

use App\Models\HR\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HrBirthdays extends Command
{
    protected $signature = 'hr:birthdays';

    protected $description = 'List active employees with birthdays in the next 7 days.';

    public function handle(): int
    {
        $dates = collect(range(0, 7))
            ->map(fn (int $i): string => now()->addDays($i)->format('m-d'))
            ->toArray();

        $formatExpr = $this->birthdayFormatExpression();

        $employees = Employee::query()
            ->whereNotNull('date_of_birth')
            ->where('is_active', true)
            ->whereIn(DB::raw($formatExpr), $dates)
            ->with('department')
            ->orderByRaw(
                "CASE WHEN {$formatExpr} >= {$this->todayFormatExpression()} THEN 0 ELSE 1 END,
                 {$formatExpr} ASC"
            )
            ->get();

        if ($employees->isEmpty()) {
            $this->info('No upcoming birthdays in the next 7 days.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Department', 'Birthday', 'Turning', 'Days Away'],
            $employees->map(function (Employee $employee): array {
                $today = now()->startOfDay();
                $birthday = $employee->date_of_birth->copy()->year($today->year);

                if ($birthday->lt($today)) {
                    $birthday->addYear();
                }

                $days = (int) $today->diffInDays($birthday);
                $turningAge = $birthday->year - $employee->date_of_birth->year;

                return [
                    $employee->name,
                    $employee->department?->name ?? '—',
                    $employee->date_of_birth->format('M j'),
                    $turningAge . ' yrs',
                    $days === 0 ? 'Today!' : 'In ' . $days . ($days === 1 ? ' day' : ' days'),
                ];
            })->all()
        );

        return self::SUCCESS;
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
