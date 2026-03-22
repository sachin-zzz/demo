<?php

use App\Models\HR\Employee;

it('prints upcoming birthdays', function () {
    Employee::factory()->create([
        'is_active' => true,
        'date_of_birth' => now()->subYears(30)->addDays(2),
    ]);

    $this->artisan('hr:birthdays')
        ->assertSuccessful();
});

it('shows no upcoming birthdays message when none exist', function () {
    $this->artisan('hr:birthdays')
        ->expectsOutputToContain('No upcoming birthdays')
        ->assertSuccessful();
});

it('respects the --days option', function () {
    $withinRange = Employee::factory()->create([
        'is_active' => true,
        'name' => 'Alice Within',
        'date_of_birth' => now()->subYears(25)->addDays(3),
    ]);

    $outsideRange = Employee::factory()->create([
        'is_active' => true,
        'name' => 'Bob Outside',
        'date_of_birth' => now()->subYears(25)->addDays(15),
    ]);

    $this->artisan('hr:birthdays --days=5')
        ->expectsOutputToContain($withinRange->name)
        ->doesntExpectOutputToContain($outsideRange->name)
        ->assertSuccessful();
});

it('does not show inactive employees', function () {
    $inactive = Employee::factory()->create([
        'is_active' => false,
        'name' => 'Inactive Employee',
        'date_of_birth' => now()->subYears(25)->addDays(1),
    ]);

    $this->artisan('hr:birthdays')
        ->doesntExpectOutputToContain($inactive->name)
        ->assertSuccessful();
});
