<?php

namespace App\Http\Requests\Menu;

use App\Models\Menu;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        $menu = $this->route('menu');

        return $menu instanceof Menu && ($this->user()?->can('update', $menu) ?? false);
    }

    /**
     * Menu metadata yang boleh diubah hanya label/icon/order/is_active/description.
     * Code & route diatur lewat migration (T02 §4).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:128'],
            'icon' => ['nullable', 'string', 'max:64'],
            'order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
