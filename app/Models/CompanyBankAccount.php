<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyBankAccount extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'bank_name',
        'bank_code',
        'account_number',
        'account_holder',
        'branch',
        'is_active',
        'show_on_invoice',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_on_invoice' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeShownOnInvoice(Builder $query): Builder
    {
        return $query->where('show_on_invoice', true);
    }
}
