<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftTemplateRequest;
use App\Models\EmployeeShift;
use App\Models\ShiftTemplate;
use App\Services\ScheduleChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ShiftTemplateController extends Controller
{
    public function __construct(private readonly ScheduleChangeLogger $logger) {}

    public function index(): JsonResponse
    {
        return response()->json(ShiftTemplate::query()->orderBy('name')->get());
    }

    public function store(ShiftTemplateRequest $request): JsonResponse
    {
        $template = ShiftTemplate::create($request->validated());

        return response()->json($template, 201);
    }

    public function update(ShiftTemplateRequest $request, ShiftTemplate $shiftTemplate): JsonResponse
    {
        $shiftTemplate->update($request->validated());

        return response()->json($shiftTemplate);
    }

    public function destroy(Request $request, ShiftTemplate $shiftTemplate): Response
    {
        DB::transaction(function () use ($request, $shiftTemplate): void {
            $affectedShifts = EmployeeShift::where('template_id', $shiftTemplate->id)->get();

            foreach ($affectedShifts as $shift) {
                $this->logger->record(
                    $request->user(),
                    $shift->employee_id,
                    before: [
                        'template_id' => $shift->template_id,
                        'effective_from' => $shift->effective_from?->toISOString(),
                        'effective_to' => $shift->effective_to?->toISOString(),
                    ],
                    after: null,
                    effectiveFrom: null,
                    reason: "Shift template \"{$shiftTemplate->name}\" was deleted",
                );
            }

            EmployeeShift::where('template_id', $shiftTemplate->id)->delete();

            $shiftTemplate->delete();
        });

        return response()->noContent();
    }
}
