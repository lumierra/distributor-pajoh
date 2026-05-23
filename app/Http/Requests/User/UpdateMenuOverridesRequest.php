<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuOverridesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('updateMenuOverrides', $target) ?? false);
    }

    /**
     * Payload shape:
     * {
     *   "overrides": [
     *     { "menu_id": 12, "can_view": true|false|null, "can_create": ..., ..., "note": "..." },
     *     ...
     *   ]
     * }
     *
     * Tiap kolom bisa NULL (= ikut role default), TRUE (grant), atau FALSE (deny).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'overrides' => ['present', 'array'],
            'overrides.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'overrides.*.can_view' => ['nullable', 'boolean'],
            'overrides.*.can_create' => ['nullable', 'boolean'],
            'overrides.*.can_update' => ['nullable', 'boolean'],
            'overrides.*.can_delete' => ['nullable', 'boolean'],
            'overrides.*.can_approve' => ['nullable', 'boolean'],
            'overrides.*.can_export' => ['nullable', 'boolean'],
            'overrides.*.note' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
