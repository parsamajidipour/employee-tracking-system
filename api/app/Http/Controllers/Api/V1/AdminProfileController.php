<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminPasswordRequest;
use App\Http\Requests\UpdateAdminProfileRequest;
use Illuminate\Http\JsonResponse;

class AdminProfileController extends Controller
{
    public function update(UpdateAdminProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->safe()->only(['name', 'email']));

        return response()->json($request->user()->only(['id', 'name', 'email']));
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        return response()->json($request->user()->only(['id', 'name', 'email']));
    }
}
