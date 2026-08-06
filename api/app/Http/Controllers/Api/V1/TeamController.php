<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Team::orderBy('name')->get());
    }

    public function store(TeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return response()->json($team, 201);
    }

    public function update(TeamRequest $request, Team $team): JsonResponse
    {
        $team->update($request->validated());

        return response()->json($team);
    }

    public function destroy(Team $team): Response
    {
        $team->delete();

        return response()->noContent();
    }
}
