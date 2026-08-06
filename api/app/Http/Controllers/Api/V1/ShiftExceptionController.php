<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShiftExceptionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftExceptionRequest;
use App\Http\Requests\UpdateShiftExceptionRequest;
use App\Models\ShiftException;
use App\Services\ScheduleChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShiftExceptionController extends Controller
{
    public function __construct(private readonly ScheduleChangeLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ShiftException::query()->orderByDesc('date');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return response()->json($query->get());
    }

    public function store(StoreShiftExceptionRequest $request): JsonResponse
    {
        $attributes = $request->safe()->except(['reason']);
        $attributes = $this->normalizeTimes($attributes);

        $exception = ShiftException::create($attributes);

        $this->logger->record(
            $request->user(),
            $exception->employee_id,
            before: null,
            after: $this->snapshot($exception),
            // No effective_from concept for a dated exception — see
            // App\Services\ScheduleChangeLogger's docblock.
            effectiveFrom: null,
            reason: $request->validated('reason'),
        );

        return response()->json($exception, 201);
    }

    public function update(UpdateShiftExceptionRequest $request, ShiftException $shiftException): JsonResponse
    {
        $before = $this->snapshot($shiftException);

        $attributes = $request->safe()->except(['reason']);
        $type = ShiftExceptionType::from($attributes['type'] ?? $shiftException->type->value);
        $attributes = $this->normalizeTimes($attributes, $type);

        $shiftException->update($attributes);

        $this->logger->record(
            $request->user(),
            $shiftException->employee_id,
            before: $before,
            after: $this->snapshot($shiftException),
            effectiveFrom: null,
            reason: $request->validated('reason'),
        );

        return response()->json($shiftException);
    }

    public function destroy(Request $request, ShiftException $shiftException): Response
    {
        $before = $this->snapshot($shiftException);
        $employeeId = $shiftException->employee_id;

        $shiftException->delete();

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
     * leave/holiday deny the day outright and carry no times — forced to
     * null here regardless of what was submitted, so stored data can never
     * disagree with ShiftExceptionType::definesWindow().
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeTimes(array $attributes, ?ShiftExceptionType $type = null): array
    {
        $type ??= ShiftExceptionType::from($attributes['type']);

        if ($type->isDeny()) {
            $attributes['start_at'] = null;
            $attributes['end_at'] = null;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ShiftException $exception): array
    {
        return [
            'type' => $exception->type->value,
            'date' => $exception->date?->toDateString(),
            'start_at' => $exception->start_at,
            'end_at' => $exception->end_at,
            'note' => $exception->note,
        ];
    }
}
