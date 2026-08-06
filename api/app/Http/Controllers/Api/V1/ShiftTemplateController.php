<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftTemplateRequest;
use App\Models\ShiftTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShiftTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ShiftTemplate::query()->with('team')->orderBy('name');

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        return response()->json($query->get());
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

    public function destroy(ShiftTemplate $shiftTemplate): Response
    {
        $shiftTemplate->delete();

        return response()->noContent();
    }
}
