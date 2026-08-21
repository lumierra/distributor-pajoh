/**
 * Pecah qty (dalam BASE UNIT) jadi breakdown antar-satuan produk, dari satuan
 * terbesar ke terkecil — cerminan `UomConverter::formatBreakdown` di backend.
 *
 * `units` = array product_units [{ name, qty_to_base }].
 *
 * breakdownUnits(402, units) → [{ qty: 10, unit: 'KRT' }, { qty: 2, unit: 'PCS' }]
 */
export function breakdownUnits(baseQty, units) {
    const n = Math.trunc(Number(baseQty) || 0);
    if (n <= 0 || !Array.isArray(units) || units.length === 0) return [];

    const sorted = [...units].sort((a, b) => (b.qty_to_base ?? 1) - (a.qty_to_base ?? 1));
    const parts = [];
    let remaining = n;

    for (const u of sorted) {
        const factor = Number(u.qty_to_base) || 1;
        const whole = Math.floor(remaining / factor);
        if (whole > 0) {
            parts.push({ qty: whole, unit: u.name });
            remaining -= whole * factor;
        }
    }
    return parts;
}

/**
 * String breakdown, mis. "10 KRT + 2 PCS". Kembalikan "0" kalau kosong.
 */
export function formatBreakdown(baseQty, units) {
    const parts = breakdownUnits(baseQty, units);
    if (parts.length === 0) return '0';
    return parts.map((p) => `${p.qty} ${p.unit}`).join(' + ');
}

/**
 * Nama base unit (qty_to_base = 1) dari daftar units; fallback '' .
 */
export function baseUnitName(units) {
    if (!Array.isArray(units)) return '';
    const base = units.find((u) => Number(u.qty_to_base) === 1);
    return base?.name ?? '';
}
