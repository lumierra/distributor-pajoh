<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSequence extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'doc_type',
        'period_year',
        'period_month',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
