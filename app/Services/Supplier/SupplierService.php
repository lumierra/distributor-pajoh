<?php

namespace App\Services\Supplier;

use App\Exceptions\SupplierHasActiveTransactionsException;
use App\Models\Supplier;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrator untuk Supplier CRUD.
 *
 * Tanggung jawab:
 *  - Generate kode atomik via NumberingService
 *  - Wrap create/update/delete dalam DB transaction
 *  - Invalidate cache 'suppliers:active' setelah mutasi
 *  - Cek `canBeDeleted()` sebelum soft-delete (block kalau ada PO/GRN open)
 */
class SupplierService
{
    public const CACHE_KEY_ACTIVE = 'suppliers:active';

    public const CACHE_TTL_SECONDS = 600;

    public function __construct(private readonly NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data): Supplier {
            $data['code'] ??= $this->numbering->next('supplier_code');
            $data['is_active'] = $data['is_active'] ?? true;

            $supplier = Supplier::create($data);

            $this->invalidateCache();

            return $supplier;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            // Code adalah read-only setelah create.
            unset($data['code']);

            $supplier->update($data);
            $this->invalidateCache();

            return $supplier;
        });
    }

    /**
     * Soft delete dengan validasi business rule.
     */
    public function delete(Supplier $supplier): void
    {
        $check = $supplier->canBeDeleted();
        if (! $check['can']) {
            throw new SupplierHasActiveTransactionsException($check['reasons']);
        }

        DB::transaction(function () use ($supplier): void {
            $supplier->delete();
            $this->invalidateCache();
        });
    }

    public function toggleActive(Supplier $supplier): Supplier
    {
        $supplier->forceFill(['is_active' => ! $supplier->is_active])->save();
        $this->invalidateCache();

        return $supplier;
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
    }
}
