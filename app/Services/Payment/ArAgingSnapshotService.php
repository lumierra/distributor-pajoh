<?php

namespace App\Services\Payment;

use App\Models\ArAgingSnapshot;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generate AR aging snapshot harian — dipanggil oleh daily job 23:00.
 *
 * Per customer, agregat outstanding ke 4 bucket berdasar selisih
 * snapshot_date - invoice.due_date:
 *  - 0-30 hari
 *  - 31-60 hari
 *  - 61-90 hari
 *  - over 90 hari
 *
 * Snapshot di-upsert (unique pada snapshot_date + customer_id).
 */
class ArAgingSnapshotService
{
    /**
     * @return int jumlah customer yang ter-snapshot
     */
    public function generateFor(?Carbon $date = null): int
    {
        $snapshotDate = ($date ?? now())->startOfDay();
        $snapshotDateString = $snapshotDate->toDateString();

        $rows = Invoice::query()
            ->select('customer_id', 'due_date', 'outstanding')
            ->whereIn('status', [
                Invoice::STATUS_OPEN,
                Invoice::STATUS_PARTIAL_PAID,
                Invoice::STATUS_OVERDUE,
            ])
            ->where('outstanding', '>', 0)
            ->get();

        $buckets = []; // customer_id => [b1, b2, b3, b4, total]
        foreach ($rows as $row) {
            $cid = (int) $row->customer_id;
            $buckets[$cid] ??= [0.0, 0.0, 0.0, 0.0, 0.0];

            $daysOverdue = $snapshotDate->diffInDays($row->due_date, false) * -1;
            // diffInDays($x, false) negatif kalau $x di masa depan. Untuk yang
            // belum overdue ($daysOverdue < 0), masuk bucket 0-30.
            $overdueDays = max(0, $daysOverdue);

            if ($overdueDays <= 30) {
                $buckets[$cid][0] += (float) $row->outstanding;
            } elseif ($overdueDays <= 60) {
                $buckets[$cid][1] += (float) $row->outstanding;
            } elseif ($overdueDays <= 90) {
                $buckets[$cid][2] += (float) $row->outstanding;
            } else {
                $buckets[$cid][3] += (float) $row->outstanding;
            }
            $buckets[$cid][4] += (float) $row->outstanding;
        }

        DB::transaction(function () use ($buckets, $snapshotDateString): void {
            // Hapus snapshot lama untuk tanggal ini (re-run idempotent)
            ArAgingSnapshot::query()->where('snapshot_date', $snapshotDateString)->delete();

            foreach ($buckets as $customerId => $b) {
                ArAgingSnapshot::create([
                    'snapshot_date' => $snapshotDateString,
                    'customer_id' => $customerId,
                    'bucket_0_30' => $b[0],
                    'bucket_31_60' => $b[1],
                    'bucket_61_90' => $b[2],
                    'bucket_over_90' => $b[3],
                    'total_outstanding' => $b[4],
                    'created_at' => now(),
                ]);
            }
        });

        return count($buckets);
    }
}
