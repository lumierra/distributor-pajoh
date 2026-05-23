<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('updatePermissions', $role) ?? false);
    }

    /**
     * Payload shape:
     * {
     *   "permissions": [
     *     { "menu_id": 12, "can_view": true, "can_create": false, ... },
     *     ...
     *   ]
     * }
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'permissions.*.can_view' => ['required', 'boolean'],
            'permissions.*.can_create' => ['required', 'boolean'],
            'permissions.*.can_update' => ['required', 'boolean'],
            'permissions.*.can_delete' => ['required', 'boolean'],
            'permissions.*.can_approve' => ['required', 'boolean'],
            'permissions.*.can_export' => ['required', 'boolean'],
        ];
    }
}
