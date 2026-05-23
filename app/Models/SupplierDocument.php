<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierDocument extends Model
{
    use SoftDeletes;

    public const TYPES = ['NPWP', 'NIB', 'PKP', 'KONTRAK', 'SURAT_PERJANJIAN', 'LAINNYA'];

    protected $fillable = [
        'supplier_id',
        'type',
        'title',
        'file_path',
        'file_size',
        'file_mime',
        'issued_date',
        'expires_date',
        'notes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expires_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expires_date')
            ->whereBetween('expires_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_date')->where('expires_date', '<', now()->toDateString());
    }
}
