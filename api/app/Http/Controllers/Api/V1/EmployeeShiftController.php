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
    public function __construct(private readonly ScheduleChangeLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = EmployeeShift::query()->with('template')->orderByDesc('effective_from');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return response()->json($query->get());
    }

    public function store(StoreEmployeeShiftRequest $request): JsonResponse
    {
        $employeeShift = EmployeeShift::create($request->safe()->except(['reason']));

        $this->logger->record(
            $request->user(),
            $employeeShift->employee_id,
            before: null,
            after: $this->snapshot($employeeShift),
            effectiveFrom: $employeeShift->effective_from,
            reason: $request->validated('reason'),
        );

        return response()->json($employeeShift, 201);
    }

    public function update(UpdateEmployeeShiftRequest $request, EmployeeShift $employeeShift): JsonResponse
    {
        $before = $this->snapshot($employeeShift);

        $employeeShift->update($request->safe()->except(['reason']));

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
        $effectiveFrom = $employeeShift->effective_from;

        $employeeShift->delete();

        $this->logger->record(
            $request->user(),
            $employeeId,
            before: $before,
            after: null,
            effectiveFrom: $effectiveFrom,
            reason: $request->input('reason'),
        );

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(EmployeeShift $employeeShift): array
    {
        return [
            'template_id' => $employeeShift->template_id,
            'effective_from' => $employeeShift->effective_from?->toISOString(),
            'effective_to' => $employeeShift->effective_to?->toISOString(),
        ];
    }
}
