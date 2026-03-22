<?php

use App\Filament\Widgets\UpcomingBirthdaysWidget;
use App\Models\HR\Employee;
use Livewire\Livewire;

it('renders the upcoming birthdays widget', function () {
    Employee::factory()->create([
        'is_active' => true,
        'date_of_birth' => now()->subYears(30)->addDays(3),
    ]);

    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertOk();
});

it('shows employees with birthdays in the next 7 days', function () {
    $upcoming = Employee::factory()->create([
        'is_active' => true,
        'date_of_birth' => now()->subYears(25)->addDays(2),
    ]);

    $notUpcoming = Employee::factory()->create([
        'is_active' => true,
        'date_of_birth' => now()->subYears(25)->addDays(10),
    ]);

    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertCanSeeTableRecords([$upcoming])
        ->assertCanNotSeeTableRecords([$notUpcoming]);
});

it('does not show inactive employees', function () {
    $inactive = Employee::factory()->create([
        'is_active' => false,
        'date_of_birth' => now()->subYears(25)->addDays(1),
    ]);

    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('shows empty state when no upcoming birthdays', function () {
    Livewire::test(UpcomingBirthdaysWidget::class)
        ->assertSee('No upcoming birthdays');
});
