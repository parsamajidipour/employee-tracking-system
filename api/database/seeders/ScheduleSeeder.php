<?php

namespace Database\Seeders;

use App\Enums\ShiftExceptionType;
use App\Enums\UserRole;
use App\Models\EmployeeLeave;
use App\Models\EmployeeShift;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\ScheduleChangeLogger;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function __construct(private readonly ScheduleChangeLogger $logger) {}

    public function run(): void
    {
        $actor = User::where('role', UserRole::Hr)->first() ?? User::where('role', UserRole::Admin)->firstOrFail();

        $default = ShiftTemplate::where('name', 'Default')->firstOrFail();
        $ramadan = ShiftTemplate::where('name', 'Ramadan')->firstOrFail();

        $employees = User::employees()->orderBy('id')->get()->keyBy('username');

        $assignments = [
            ['username' => 'yusuf', 'template' => $default],
            ['username' => 'khalid', 'template' => $default],
            ['username' => 'aisha', 'template' => $ramadan],
        ];

        foreach ($assignments as $assignment) {
            $employee = $employees->get($assignment['username']);

            if ($employee === null || EmployeeShift::where('employee_id', $employee->id)->exists()) {
                continue;
            }

            $shift = EmployeeShift::create([
                'employee_id' => $employee->id,
                'template_id' => $assignment['template']->id,
                'effective_from' => now()->subMonth(),
            ]);

            $this->logger->record($actor, $employee->id, null, $shift->only([
                'employee_id', 'template_id', 'effective_from', 'effective_to',
            ]), $shift->effective_from, 'Initial assignment');
        }

        $exceptions = [
            ['username' => 'ahmed', 'date' => now()->addDay(), 'type' => ShiftExceptionType::Leave, 'start_at' => null, 'end_at' => null, 'note' => 'Annual leave'],
            ['username' => 'fatma', 'date' => now()->addDays(2), 'type' => ShiftExceptionType::Holiday, 'start_at' => null, 'end_at' => null, 'note' => 'Public holiday'],
            ['username' => 'noura', 'date' => now(), 'type' => ShiftExceptionType::Overtime, 'start_at' => '07:00:00', 'end_at' => '19:00:00', 'note' => 'Site inspection overrun'],
            ['username' => 'omar', 'date' => now(), 'type' => ShiftExceptionType::EarlyEnd, 'start_at' => '07:00:00', 'end_at' => '12:00:00', 'note' => 'Medical appointment'],
        ];

        foreach ($exceptions as $exception) {
            $employee = $employees->get($exception['username']);

            if ($employee === null) {
                continue;
            }

            $row = ShiftException::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $exception['date']->toDateString()],
                [
                    'type' => $exception['type'],
                    'start_at' => $exception['start_at'],
                    'end_at' => $exception['end_at'],
                    'note' => $exception['note'],
                ],
            );

            $this->logger->record($actor, $employee->id, null, $row->only([
                'employee_id', 'date', 'type', 'start_at', 'end_at', 'note',
            ]), null, 'Seeded exception');
        }

        $this->seedLeaves($employees, $actor);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $employees
     */
    private function seedLeaves($employees, User $actor): void
    {
        $leaves = [
            ['username' => 'yusuf', 'starts_at' => now()->addDays(3)->setTime(8, 0), 'ends_at' => now()->addDays(5)->setTime(17, 0), 'note' => 'Family leave'],
            ['username' => 'khalid', 'starts_at' => now()->addDays(1)->setTime(12, 0), 'ends_at' => now()->addDays(1)->setTime(17, 0), 'note' => 'Half day off'],
        ];

        foreach ($leaves as $leave) {
            $employee = $employees->get($leave['username']);

            if ($employee === null || EmployeeLeave::where('employee_id', $employee->id)->exists()) {
                continue;
            }

            $row = EmployeeLeave::create([
                'employee_id' => $employee->id,
                'starts_at' => $leave['starts_at'],
                'ends_at' => $leave['ends_at'],
                'note' => $leave['note'],
                'created_by' => $actor->id,
            ]);

            $this->logger->record($actor, $employee->id, null, [
                'type' => 'leave',
                'starts_at' => $row->starts_at->toISOString(),
                'ends_at' => $row->ends_at->toISOString(),
                'note' => $row->note,
            ], $row->starts_at, 'Seeded leave');
        }
    }
}
