<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminProfileRequest;
use Illuminate\Http\JsonResponse;

class AdminProfileController extends Controller
{
    public function update(UpdateAdminProfileRequest $request): JsonResponse
    {
        $attributes = $request->safe()->only(['name', 'email']);

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        $request->user()->update($attributes);
        $request->session()->regenerate();

        return response()->json($request->user()->only(['id', 'name', 'email']));
    }
}
