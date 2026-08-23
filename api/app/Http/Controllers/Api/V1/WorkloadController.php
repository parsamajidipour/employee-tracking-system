<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CaseWorkloadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkloadController extends Controller
{
    public function index(CaseWorkloadService $workload): JsonResponse
    {
        $today = CarbonImmutable::now()->toDateString();

        $rows = User::query()->employees()->active()->orderBy('name')->get()->map(
            fn (User $employee) => [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'summary' => $workload->summary($employee),
                'today' => $workload->dailyActivity($employee, $today),
            ],
        );

        return response()->json($rows);
    }

    public function show(User $employee, Request $request, CaseWorkloadService $workload): JsonResponse
    {
        $date = $request->string('date')->toString() ?: CarbonImmutable::now()->toDateString();

        return response()->json([
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'summary' => $workload->summary($employee),
            'activity' => $workload->dailyActivity($employee, $date),
        ]);
    }
}
