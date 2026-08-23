<?php

namespace App\Services;

use App\Models\TrackingInterruption;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TrackingInterruptionService
{
    public function __construct(private readonly ShiftWindowResolver $resolver) {}

    public function start(User $employee, string $reason, CarbonImmutable $at): ?TrackingInterruption
    {
        if ($this->resolver->resolve($employee, $at) === null) {
            return null;
        }

        $open = TrackingInterruption::where('employee_id', $employee->id)->whereNull('ended_at')->first();
        if ($open !== null) {
            return $open;
        }

        return TrackingInterruption::create([
            'employee_id' => $employee->id,
            'reason' => $reason,
            'started_at' => $at,
        ]);
    }

    public function stop(User $employee, CarbonImmutable $at): void
    {
        DB::transaction(function () use ($employee, $at) {
            TrackingInterruption::where('employee_id', $employee->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $at]);
        });
    }
}
