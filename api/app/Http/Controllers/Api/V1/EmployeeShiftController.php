<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeShiftRequest;
use App\Http\Requests\UpdateEmployeeShiftRequest;
use App\Models\EmployeeShift;
use App\Services\ScheduleChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeShiftController extends Controller
{
    public function __construct(private readonly ScheduleChangeLogger $logger) {}

    public function index(Request $request): JsonResponse
    {
        $query = EmployeeShift::query()->with('employee:id,name')->orderByDesc('effective_from');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->integer('template_id'));
        }

        return response()->json($query->get());
    }

    public function store(StoreEmployeeShiftRequest $request): JsonResponse
    {
        $attributes = $request->safe()->except(['reason']);
        $shift = EmployeeShift::create($attributes);

        $this->logger->record(
            $request->user(),
            $shift->employee_id,
            before: null,
            after: $this->snapshot($shift),
            effectiveFrom: $shift->effective_from,
            reason: $request->validated('reason'),
        );

        return response()->json($shift, 201);
    }

    public function update(UpdateEmployeeShiftRequest $request, EmployeeShift $employeeShift): JsonResponse
    {
        $before = $this->snapshot($employeeShift);

        $attributes = $request->safe()->except(['reason']);
        $employeeShift->update($attributes);

        $this->logger->record(
            $request->user(),
            $employeeShift->employee_id,
            before: $before,
            after: $this->snapshot($employeeShift),
            effectiveFrom: $employeeShift->effective_from,
            reason: $request->validated('reason'),
        );

        return response()->json($employeeShift);
    }

    public function destroy(Request $request, EmployeeShift $employeeShift): Response
    {
        $before = $this->snapshot($employeeShift);
        $employeeId = $employeeShift->employee_id;

        $employeeShift->delete();

        $this->logger->record(
            $request->user(),
            $employeeId,
            before: $before,
            after: null,
            effectiveFrom: null,
            reason: $request->input('reason'),
        );

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(EmployeeShift $shift): array
    {
        return [
            'template_id' => $shift->template_id,
            'effective_from' => $shift->effective_from?->toISOString(),
            'effective_to' => $shift->effective_to?->toISOString(),
        ];
    }
}
