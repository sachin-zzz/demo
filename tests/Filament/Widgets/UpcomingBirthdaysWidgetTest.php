<?php

use App\Filament\Widgets\UpcomingBirthdaysWidget;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use Livewire\Livewire;

it('renders the upcoming birthdays widget', function () {
    $department = Department::factory()->create();

    $upcoming = Employee::factory()->count(2)->sequence(
        ['date_of_birth' => now()->subYears(30), 'is_active' => true],
        ['date_of_birth' => now()->addDays(5)->subYears(25), 'is_active' => true],
    )->create(['department_id' => $department->id]);

    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords($upcoming);
});

it('excludes employees with birthdays outside the next 7 days', function () {
    $department = Department::factory()->create();

    $outsideWindow = Employee::factory()->create([
        'department_id' => $department->id,
        'date_of_birth' => now()->addDays(10)->subYears(28),
        'is_active' => true,
    ]);

    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertOk()
        ->assertCanNotSeeTableRecords(collect([$outsideWindow]));
});

it('excludes inactive employees', function () {
    $department = Department::factory()->create();

    $inactive = Employee::factory()->create([
        'department_id' => $department->id,
        'date_of_birth' => now()->subYears(22),
        'is_active' => false,
    ]);

    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertOk()
        ->assertCanNotSeeTableRecords(collect([$inactive]));
});

it('shows empty state when no upcoming birthdays exist', function () {
    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertOk()
        ->assertSeeText('No upcoming birthdays');
});
