<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YearEndClosing extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_FAILED];

    protected $fillable = [
        'fiscal_year',
        'closing_date',
        'status',
        'closed_at',
        'closed_by',
        'total_revenue',
        'total_cost',
        'total_margin',
        'margin_percent',
        'total_purchases',
        'total_payments_received',
        'total_outstanding_carry_over',
        'total_stock_value_closing',
        'invoice_count',
        'customer_count_active',
        'product_count_sold',
        'top_5_customers',
        'top_5_products',
        'top_3_sales',
        'carry_over_summary',
        'carry_over_po_ids',
        'carry_over_so_ids',
        'carry_over_invoice_ids',
        'carry_over_cn_ids',
        'summary_pdf_path',
        'notes',
        'pre_check_passed_at',
        'carry_over_done_at',
        'sequence_reset_done_at',
        'partition_setup_done_at',
        'summary_generated_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'closed_at' => 'datetime',
            'total_revenue' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_margin' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'total_purchases' => 'decimal:2',
            'total_payments_received' => 'decimal:2',
            'total_outstanding_carry_over' => 'decimal:2',
            'total_stock_value_closing' => 'decimal:2',
            'top_5_customers' => 'array',
            'top_5_products' => 'array',
            'top_3_sales' => 'array',
            'carry_over_summary' => 'array',
            'carry_over_po_ids' => 'array',
            'carry_over_so_ids' => 'array',
            'carry_over_invoice_ids' => 'array',
            'carry_over_cn_ids' => 'array',
            'pre_check_passed_at' => 'datetime',
            'carry_over_done_at' => 'datetime',
            'sequence_reset_done_at' => 'datetime',
            'partition_setup_done_at' => 'datetime',
            'summary_generated_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
