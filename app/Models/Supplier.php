<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const LEGAL_FORMS = ['PT', 'CV', 'UD', 'KOPERASI', 'PABRIK', 'LAINNYA'];

    protected $fillable = [
        'code',
        'name',
        'legal_form',
        'npwp',
        'nib',
        'supplier_category_id',
        'phone',
        'whatsapp',
        'email',
        'fax',
        'address',
        'city',
        'province',
        'postal_code',
        'contact_person_name',
        'contact_person_role',
        'contact_person_phone',
        'contact_person_email',
        'payment_term_days',
        'default_lead_time_days',
        'is_active',
        'notes',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'tags' => 'array',
            'payment_term_days' => 'integer',
            'default_lead_time_days' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class)->latest();
    }

    public function defaultBank(): ?SupplierBankAccount
    {
        return $this->bankAccounts()->where('is_default', true)->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCategory(Builder $query, string $code): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->where('code', $code));
    }

    /**
     * Stub — relasi PO/GRN dibuat di T07/T08. Akan dikembangkan ketika modul
     * itu landing dengan pengecekan status open.
     *
     * @return array{can:bool, reasons:array<int,string>}
     */
    public function canBeDeleted(): array
    {
        return ['can' => true, 'reasons' => []];
    }
}
