<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Creates an employee account — always role=employee (this form has no
 * role picker; admin/hr/supervisor accounts aren't created through the
 * panel today). team_id is never accepted here: every employee lands on
 * the one default team automatically (see EmployeeController::store() and
 * DECISIONS.md's "multi-team is deferred" entry) — there is no team picker
 * anywhere in the UI to have sent one.
 */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];
    }
}
