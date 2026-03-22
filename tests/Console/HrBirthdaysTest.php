<?php

use App\Models\HR\Department;
use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('outputs a message when no upcoming birthdays exist', function () {
    $this->artisan('hr:birthdays')
        ->expectsOutput('No upcoming birthdays in the next 7 days.')
        ->assertExitCode(0);
});

it('outputs a table of upcoming birthdays', function () {
    $department = Department::factory()->create(['name' => 'Engineering']);

    $employee = Employee::factory()->create([
        'name' => 'Jane Smith',
        'department_id' => $department->id,
        'date_of_birth' => now()->subYears(30),
        'is_active' => true,
    ]);

    $this->artisan('hr:birthdays')
        ->expectsTable(
            ['Name', 'Department', 'Birthday', 'Turning', 'Days Away'],
            [[$employee->name, 'Engineering', now()->format('M j'), '30 yrs', 'Today!']]
        )
        ->assertExitCode(0);
});

it('excludes inactive employees from birthday list', function () {
    Employee::factory()->create([
        'date_of_birth' => now()->subYears(30),
        'is_active' => false,
    ]);

    $this->artisan('hr:birthdays')
        ->expectsOutput('No upcoming birthdays in the next 7 days.')
        ->assertExitCode(0);
});
