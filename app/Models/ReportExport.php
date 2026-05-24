<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'report_type',
        'format',
        'filters',
        'rows_count',
        'file_size_bytes',
        'file_path',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'rows_count' => 'integer',
            'file_size_bytes' => 'integer',
            'exported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
