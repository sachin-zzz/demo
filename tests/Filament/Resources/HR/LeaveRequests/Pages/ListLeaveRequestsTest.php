<?php

use App\Enums\LeaveStatus;
use App\Filament\Resources\HR\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('can render the list page', function () {
    $records = LeaveRequest::factory()->for(Employee::factory(), 'employee')->count(3)->create();

    Livewire::test(ListLeaveRequests::class)
        ->assertOk()
        ->assertCanSeeTableRecords($records);
});

it('can approve a pending leave request', function () {
    $record = LeaveRequest::factory()->for(Employee::factory(), 'employee')->create([
        'status' => LeaveStatus::Pending,
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->callAction(TestAction::make('approve')->table($record))
        ->assertNotified();

    $record->refresh();
    expect($record->status)->toBe(LeaveStatus::Approved);
    expect($record->reviewed_at)->not->toBeNull();
});

it('approve is hidden for non-pending requests', function () {
    $record = LeaveRequest::factory()->for(Employee::factory(), 'employee')->create([
        'status' => LeaveStatus::Approved,
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->assertActionHidden(TestAction::make('approve')->table($record));
});

it('can reject a pending leave request', function () {
    $record = LeaveRequest::factory()->for(Employee::factory(), 'employee')->create([
        'status' => LeaveStatus::Pending,
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->callAction(TestAction::make('reject')->table($record), [
            'reviewer_notes' => 'Team is understaffed',
        ])
        ->assertNotified();

    $record->refresh();
    expect($record->status)->toBe(LeaveStatus::Rejected);
    expect($record->reviewer_notes)->toBe('Team is understaffed');
    expect($record->reviewed_at)->not->toBeNull();
});

it('reject is hidden for non-pending requests', function () {
    $record = LeaveRequest::factory()->for(Employee::factory(), 'employee')->create([
        'status' => LeaveStatus::Approved,
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->assertActionHidden(TestAction::make('reject')->table($record));
});

it('can delete a leave request', function () {
    $record = LeaveRequest::factory()->for(Employee::factory(), 'employee')->create();

    Livewire::test(ListLeaveRequests::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($record))
        ->assertNotified();
});

it('can bulk approve leave requests', function () {
    $records = LeaveRequest::factory()->for(Employee::factory(), 'employee')->count(3)->create([
        'status' => LeaveStatus::Pending,
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->selectTableRecords($records)
        ->callAction(TestAction::make('approve')->table()->bulk());

    foreach ($records as $record) {
        $record->refresh();
        expect($record->status)->toBe(LeaveStatus::Approved);
    }
});

it('can bulk reject leave requests', function () {
    $records = LeaveRequest::factory()->for(Employee::factory(), 'employee')->count(3)->create([
        'status' => LeaveStatus::Pending,
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->selectTableRecords($records)
        ->callAction(TestAction::make('reject')->table()->bulk(), [
            'reviewer_notes' => 'Budget constraints',
        ]);

    foreach ($records as $record) {
        $record->refresh();
        expect($record->status)->toBe(LeaveStatus::Rejected);
    }
});

it('can bulk delete leave requests', function () {
    $records = LeaveRequest::factory()->for(Employee::factory(), 'employee')->count(3)->create();

    Livewire::test(ListLeaveRequests::class)
        ->selectTableRecords($records)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified();
});

it('department conflict badge shows count when same-department colleagues have overlapping leave', function () {
    $department = Department::factory()->create();
    $employee1 = Employee::factory()->for($department)->create();
    $employee2 = Employee::factory()->for($department)->create();

    $overlap = LeaveRequest::factory()->for($employee2, 'employee')->create([
        'status' => LeaveStatus::Approved,
        'start_date' => '2025-08-01',
        'end_date' => '2025-08-07',
    ]);

    $record = LeaveRequest::factory()->for($employee1, 'employee')->create([
        'status' => LeaveStatus::Pending,
        'start_date' => '2025-08-04',
        'end_date' => '2025-08-06',
    ]);

    $component = Livewire::test(ListLeaveRequests::class);

    $component->assertTableColumnStateSet(
        'department_conflicts',
        "1 other off in {$department->name}",
        $record
    );
});

it('department conflict badge shows pending colleagues too', function () {
    $department = Department::factory()->create();
    $employee1 = Employee::factory()->for($department)->create();
    $employee2 = Employee::factory()->for($department)->create();
    $employee3 = Employee::factory()->for($department)->create();

    LeaveRequest::factory()->for($employee2, 'employee')->create([
        'status' => LeaveStatus::Pending,
        'start_date' => '2025-09-01',
        'end_date' => '2025-09-05',
    ]);

    LeaveRequest::factory()->for($employee3, 'employee')->create([
        'status' => LeaveStatus::Approved,
        'start_date' => '2025-09-03',
        'end_date' => '2025-09-07',
    ]);

    $record = LeaveRequest::factory()->for($employee1, 'employee')->create([
        'status' => LeaveStatus::Pending,
        'start_date' => '2025-09-02',
        'end_date' => '2025-09-04',
    ]);

    $component = Livewire::test(ListLeaveRequests::class);

    $component->assertTableColumnStateSet(
        'department_conflicts',
        "2 others off in {$department->name}",
        $record
    );
});

it('department conflict badge is empty when no colleagues overlap', function () {
    $department = Department::factory()->create();
    $employee1 = Employee::factory()->for($department)->create();
    $employee2 = Employee::factory()->for($department)->create();

    LeaveRequest::factory()->for($employee2, 'employee')->create([
        'status' => LeaveStatus::Approved,
        'start_date' => '2025-10-20',
        'end_date' => '2025-10-22',
    ]);

    $record = LeaveRequest::factory()->for($employee1, 'employee')->create([
        'status' => LeaveStatus::Pending,
        'start_date' => '2025-11-01',
        'end_date' => '2025-11-03',
    ]);

    Livewire::test(ListLeaveRequests::class)
        ->assertTableColumnStateSet('department_conflicts', null, $record);
});

it('usedLeaveDaysThisYear counts approved and taken leave for current year only', function () {
    $employee = Employee::factory()->create(['leave_allowance' => 20]);

    LeaveRequest::factory()->for($employee, 'employee')->create([
        'status' => LeaveStatus::Approved,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->startOfYear()->addDays(4)->toDateString(),
        'days_requested' => 5,
    ]);

    LeaveRequest::factory()->for($employee, 'employee')->create([
        'status' => LeaveStatus::Taken,
        'start_date' => now()->startOfYear()->addDays(10)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(12)->toDateString(),
        'days_requested' => 3,
    ]);

    // Rejected leave should not count
    LeaveRequest::factory()->for($employee, 'employee')->create([
        'status' => LeaveStatus::Rejected,
        'start_date' => now()->startOfYear()->addDays(20)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(22)->toDateString(),
        'days_requested' => 4,
    ]);

    expect($employee->usedLeaveDaysThisYear())->toBe(8.0);
});

it('usedLeaveDaysThisYear returns zero when employee has no leave this year', function () {
    $employee = Employee::factory()->create(['leave_allowance' => 20]);

    expect($employee->usedLeaveDaysThisYear())->toBe(0.0);
});

it('employee is flagged as over limit when used days meets allowance', function () {
    $employee = Employee::factory()->create(['leave_allowance' => 5]);

    LeaveRequest::factory()->for($employee, 'employee')->create([
        'status' => LeaveStatus::Approved,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->startOfYear()->addDays(4)->toDateString(),
        'days_requested' => 5,
    ]);

    expect($employee->usedLeaveDaysThisYear())->toBeGreaterThanOrEqual($employee->leave_allowance);
});
