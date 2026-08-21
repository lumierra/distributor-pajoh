<?php

namespace App\Http\Requests\Grn;

use App\Models\GoodsReceipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadGrnAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grn = $this->route('goods_receipt');

        return $grn instanceof GoodsReceipt
            && ($this->user()?->can('manageAttachments', $grn) ?? false);
    }

    /**
     * Multi-file, format bebas (foto/PDF/dll), maks 10 MB per file, jumlah
     * tidak dibatasi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:10240'], // 10 MB per file
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal 1 file lampiran.',
            'files.*.max' => 'Ukuran tiap file maksimal 10 MB.',
        ];
    }
}
