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
     *
     * @param  array<int, array{level:string, qty_to_base:int}>  $units
     *
     * @throws InvalidArgumentException
     */
    public function validateHierarchy(array $units): void
    {
        $byLevel = collect($units)->keyBy('level');

        $kcl = $byLevel->get(ProductUnit::LEVEL_KCL);
        if (! $kcl || (int) $kcl['qty_to_base'] !== 1) {
            throw new InvalidArgumentException('Unit KCL wajib ada dan qty_to_base harus = 1.');
        }

        if ($tgh = $byLevel->get(ProductUnit::LEVEL_TGH)) {
            if ((int) $tgh['qty_to_base'] <= 1) {
                throw new InvalidArgumentException('Unit TGH harus punya qty_to_base > 1 (lebih besar dari KCL).');
            }
        }

        if ($bsr = $byLevel->get(ProductUnit::LEVEL_BSR)) {
            $tghQty = $tgh ? (int) $tgh['qty_to_base'] : 1;
            if ((int) $bsr['qty_to_base'] <= $tghQty) {
                throw new InvalidArgumentException('Unit BSR harus punya qty_to_base > TGH.');
            }
        }
    }
}
