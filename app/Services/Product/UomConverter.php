<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductUnit;
use InvalidArgumentException;

/**
 * Konversi antar unit produk (BSR ⇄ TGH ⇄ KCL) menggunakan `qty_to_base`.
 * Base unit = KCL dengan `qty_to_base = 1`.
 */
class UomConverter
{
    /**
     * Convert qty dari unit `$fromUnit` ke base unit (KCL).
     */
    public function toBase(ProductUnit $fromUnit, int $qty): int
    {
        return $qty * $fromUnit->qty_to_base;
    }

    /**
     * Convert qty dari base unit ke unit `$toUnit`. Hasil mungkin pecahan
     * (mis. 7 KCL ÷ TGH 6 = 1.16 TGH); kita kembalikan integer floor + sisa
     * lewat `formatBreakdown()`.
     */
    public function fromBase(ProductUnit $toUnit, int $baseQty): int
    {
        return intdiv($baseQty, $toUnit->qty_to_base);
    }

    /**
     * Pecah base qty jadi breakdown across all units, mulai dari yang
     * terbesar. Mengembalikan format "10 Krt + 2 Pak + 4 Pcs".
     */
    public function formatBreakdown(Product $product, int $baseQty): string
    {
        if ($baseQty <= 0) {
            return '0';
        }

        $units = $product->units->sortByDesc('qty_to_base')->values();
        $parts = [];
        $remaining = $baseQty;

        foreach ($units as $unit) {
            $whole = intdiv($remaining, $unit->qty_to_base);
            if ($whole > 0) {
                $parts[] = "{$whole} {$unit->name}";
                $remaining -= $whole * $unit->qty_to_base;
            }
        }

        return $parts === [] ? '0' : implode(' + ', $parts);
    }

    /**
     * Validasi hirarki UoM (BSR > TGH > KCL=1).
     * Validasi:
     *  - Minimal 1 unit.
     *  - Tepat 1 unit dengan qty_to_base = 1 (base unit, sumber stok).
     *  - Semua unit punya qty_to_base > 0.
     *  - unit_id tidak duplikat (1 master unit hanya boleh ditambahkan sekali per produk).
     *
     * @param  array<int, array{unit_id?:int, qty_to_base:int}>  $units
     *
     * @throws InvalidArgumentException
     */
    public function validateHierarchy(array $units): void
    {
        if (empty($units)) {
            throw new InvalidArgumentException('Minimal 1 satuan wajib ditambahkan.');
        }

        $baseCount = 0;
        $seenUnitIds = [];
        foreach ($units as $u) {
            $qty = (int) ($u['qty_to_base'] ?? 0);
            if ($qty <= 0) {
                throw new InvalidArgumentException('qty_to_base setiap satuan harus > 0.');
            }
            if ($qty === 1) {
                $baseCount++;
            }
            $unitId = $u['unit_id'] ?? null;
            if ($unitId !== null) {
                if (in_array($unitId, $seenUnitIds, true)) {
                    throw new InvalidArgumentException('Satuan duplikat: pilih satuan yang berbeda untuk tiap baris.');
                }
                $seenUnitIds[] = $unitId;
            }
        }

        if ($baseCount === 0) {
            throw new InvalidArgumentException('Wajib ada minimal 1 satuan dengan qty_to_base = 1 (base unit).');
        }
        if ($baseCount > 1) {
            throw new InvalidArgumentException('Hanya boleh ada 1 satuan dengan qty_to_base = 1 (base unit).');
        }
    }
}
