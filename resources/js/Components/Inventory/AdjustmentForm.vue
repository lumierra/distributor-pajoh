<script setup>
import { Loader2, Plus, Trash2 } from '@lucide/vue';
import { onMounted } from 'vue';
import { formatBreakdown } from '@/lib/uom';
import { REASON_LABELS } from '@/Components/Inventory/adjustmentMeta';
import CurrencyInput from '@/Components/Shared/CurrencyInput.vue';
import SearchableSelect from '@/Components/Shared/SearchableSelect.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    form: { type: Object, required: true },
    submitLabel: { type: String, default: 'Simpan Draft' },
});

const emit = defineEmits(['submit']);

// Katalog produk-berstok (dengan batch + qty). Dimuat sekali saat mount.
async function loadStockProducts() {
    try {
        const res = await fetch(route('adjustments.stock-products'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        props.form.__catalog = data.products ?? [];
        backfillUnits();
    } catch {
        props.form.__catalog = [];
    }
}

// Edit mode: baris sudah terisi produk tapi belum punya _units/base_unit
// (katalog baru dimuat async). Lengkapi dari katalog.
function backfillUnits() {
    for (const row of props.form.items ?? []) {
        if (!row.product_id || (row._units?.length ?? 0) > 0) continue;
        const prod = catalog().find((p) => p.product_id === row.product_id);
        if (prod) {
            row._units = prod.units ?? [];
            row.base_unit = prod.base_unit;
            if (!row.product_unit_id) row.product_unit_id = prod.base_unit_id ?? (prod.units?.[0]?.id ?? null);
        }
    }
}

onMounted(() => {
    if (!props.form.__catalog?.length) loadStockProducts();
});

function catalog() {
    return props.form.__catalog ?? [];
}

function addItem() {
    props.form.items.push({
        product_id: null,
        product_unit_id: null,
        batch_id: null,
        product_name: '',
        product_sku: '',
        batch_code: '',
        base_unit: '',
        direction: 'out',
        qty: 1,
        cost_price: 0,
        notes: '',
        _system_qty: null,
        _units: [], // [{id,name,qty_to_base}]
    });
}

// Satuan produk terurut besar→kecil untuk dropdown.
function unitsForRow(row) {
    return [...(row._units ?? [])].sort((a, b) => (b.qty_to_base ?? 1) - (a.qty_to_base ?? 1));
}

function unitFactor(row) {
    const u = (row._units ?? []).find((x) => x.id === row.product_unit_id);
    return u ? Number(u.qty_to_base) || 1 : 1;
}

// Qty base = qty × faktor satuan terpilih.
function qtyBase(row) {
    return (Number(row.qty) || 0) * unitFactor(row);
}

// Tampilkan angka base dalam satuan terbesar (mis. 1258 → "104 KRT + 10 PACK").
function breakdown(row, baseQty) {
    const units = row._units ?? [];
    if (baseQty == null) return '—';
    return units.length ? formatBreakdown(baseQty, units) : Number(baseQty).toLocaleString('id-ID');
}

function removeItem(idx) {
    props.form.items.splice(idx, 1);
}

function onProductChange(idx, productId) {
    const row = props.form.items[idx];
    const id = productId ? Number(productId) : null;
    row.product_id = id;
    row.batch_id = null;
    row.batch_code = '';
    row._system_qty = null;
    row.product_unit_id = null;
    row._units = [];
    const prod = catalog().find((p) => p.product_id === id);
    if (prod) {
        row.product_name = prod.name;
        row.product_sku = prod.sku;
        row.base_unit = prod.base_unit;
        row._units = prod.units ?? [];
        // Default satuan = base unit (paling kecil).
        row.product_unit_id = prod.base_unit_id ?? (prod.units?.[0]?.id ?? null);
        // Auto-pilih batch pertama kalau cuma 1.
        if (prod.batches.length === 1) onBatchChange(idx, prod.batches[0].batch_id);
    }
}

function batchesForRow(idx) {
    const row = props.form.items[idx];
    if (!row?.product_id) return [];
    const prod = catalog().find((p) => p.product_id === row.product_id);
    return prod?.batches ?? [];
}

function onBatchChange(idx, batchId) {
    const row = props.form.items[idx];
    const id = batchId ? Number(batchId) : null;
    row.batch_id = id;
    const b = batchesForRow(idx).find((x) => x.batch_id === id);
    if (b) {
        row.batch_code = b.batch_code;
        row._system_qty = b.qty_on_hand;
    } else {
        row.batch_code = '';
        row._system_qty = null;
    }
}

// Preview stok (base) setelah adjustment untuk baris.
function afterQty(row) {
    if (row._system_qty === null) return null;
    const sys = Number(row._system_qty) || 0;
    return row.direction === 'in' ? sys + qtyBase(row) : sys - qtyBase(row);
}

function outExceedsStock(row) {
    return row.direction === 'out' && row._system_qty !== null && qtyBase(row) > Number(row._system_qty);
}

const canSubmit = () => props.form.items.length > 0 && props.form.items.every((r) => r.product_id && r.batch_id && Number(r.qty) > 0);
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Header -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3.5">Header</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tanggal *</Label>
                    <Input v-model="form.adjustment_date" type="date" required class="h-10 rounded-xl" />
                    <p v-if="form.errors.adjustment_date" class="text-xs text-destructive">{{ form.errors.adjustment_date }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Select v-model="form.reason_category">
                        <SelectTrigger class="h-10 w-full rounded-xl">
                            <SelectValue placeholder="Pilih alasan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(label, key) in REASON_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.reason_category" class="text-xs text-destructive">{{ form.errors.reason_category }}</p>
                </div>
                <div class="space-y-1 sm:col-span-3">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" class="rounded-xl" placeholder="Keterangan tambahan (opsional)" />
                </div>
            </div>
        </section>

        <!-- Items -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3.5 flex items-center justify-between border-b border-foreground/5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items ({{ form.items.length }})</p>
                <Button type="button" size="sm" variant="outline" class="rounded-full" @click="addItem">
                    <Plus class="size-3.5" /> Tambah Item
                </Button>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-4 font-semibold min-w-[220px]">Produk</th>
                            <th class="text-left py-2.5 px-2 font-semibold min-w-[140px]">Batch</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Stok Sistem</th>
                            <th class="text-left py-2.5 px-2 font-semibold">Satuan</th>
                            <th class="text-left py-2.5 px-2 font-semibold">Arah</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Qty</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Jadi</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Cost/Unit</th>
                            <th class="py-2.5 px-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-if="form.items.length === 0">
                            <td colspan="9" class="py-10 text-center text-muted-foreground text-xs">
                                Klik "Tambah Item" untuk mulai. Hanya produk yang punya stok yang bisa disesuaikan.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-foreground/2.5 transition-colors align-top">
                            <td class="py-2 px-4">
                                <SearchableSelect
                                    :model-value="row.product_id"
                                    :options="catalog()"
                                    :option-value="(p) => p.product_id"
                                    :option-label="(p) => `${p.name} (${p.sku})`"
                                    placeholder="Ketik nama / SKU produk…"
                                    empty-text="Tidak ada produk berstok."
                                    trigger-class="h-9 rounded-lg"
                                    @update:model-value="(v) => onProductChange(idx, v)"
                                >
                                    <template #option="{ option }">
                                        <p class="text-sm font-medium truncate">{{ option.name }}</p>
                                        <p class="text-[11px] text-muted-foreground font-mono">{{ option.sku }}</p>
                                    </template>
                                </SearchableSelect>
                                <p v-if="form.errors[`items.${idx}.product_id`]" class="text-[11px] text-destructive mt-0.5">
                                    {{ form.errors[`items.${idx}.product_id`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Select
                                    :model-value="row.batch_id ? String(row.batch_id) : ''"
                                    @update:model-value="(v) => onBatchChange(idx, v)"
                                >
                                    <SelectTrigger class="h-9 w-full rounded-lg">
                                        <SelectValue placeholder="Pilih batch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="b in batchesForRow(idx)" :key="b.batch_id" :value="String(b.batch_id)">
                                            {{ b.batch_code }} · {{ breakdown(row, b.qty_on_hand) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors[`items.${idx}.batch_id`]" class="text-[11px] text-destructive mt-0.5">
                                    {{ form.errors[`items.${idx}.batch_id`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2 text-right font-mono text-xs pt-4">
                                <span v-if="row._system_qty !== null">{{ breakdown(row, Number(row._system_qty)) }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="py-2 px-2">
                                <Select
                                    :model-value="row.product_unit_id ? String(row.product_unit_id) : ''"
                                    :disabled="!row.product_id"
                                    @update:model-value="(v) => (row.product_unit_id = v ? Number(v) : null)"
                                >
                                    <SelectTrigger class="h-9 w-full rounded-lg">
                                        <SelectValue placeholder="Satuan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="u in unitsForRow(row)" :key="u.id" :value="String(u.id)">
                                            {{ u.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Select v-model="row.direction">
                                    <SelectTrigger class="h-9 w-full rounded-lg">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="in">Tambah (+)</SelectItem>
                                        <SelectItem value="out">Kurang (−)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Input
                                    v-model="row.qty"
                                    type="number"
                                    min="1"
                                    :class="['h-9 rounded-lg text-right font-mono', outExceedsStock(row) ? 'ring-2 ring-red-300' : '']"
                                />
                                <p v-if="unitFactor(row) > 1 && Number(row.qty) > 0" class="text-[11px] text-muted-foreground font-mono text-right mt-0.5">
                                    = {{ Number(qtyBase(row)).toLocaleString('id-ID') }} {{ row.base_unit }}
                                </p>
                                <p v-if="outExceedsStock(row)" class="text-[11px] text-red-600">Melebihi stok</p>
                            </td>
                            <td class="py-2 px-2 text-right font-mono text-xs pt-4">
                                <span
                                    v-if="afterQty(row) !== null"
                                    :class="afterQty(row) < 0 ? 'text-red-600' : row.direction === 'in' ? 'text-emerald-700' : 'text-amber-700'"
                                >
                                    {{ breakdown(row, afterQty(row)) }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="py-2 px-2">
                                <CurrencyInput v-model="row.cost_price" class="h-9 rounded-lg font-mono" />
                            </td>
                            <td class="py-2 px-2 text-center pt-3">
                                <button
                                    type="button"
                                    class="size-8 rounded-lg hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors"
                                    @click="removeItem(idx)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-muted-foreground px-5 py-3">
                Pilih <strong>Satuan</strong> lalu isi Qty di satuan itu (mis. 2 KRT). Stok Sistem & Jadi
                ditampilkan dalam satuan terbesar. Arah <strong>Tambah</strong> = stok bertambah (mis. temuan lebih),
                <strong>Kurang</strong> = stok berkurang (rusak/hilang/susut). Cost/unit opsional (nilai koreksi).
            </p>
        </section>

        <div class="flex justify-end gap-2">
            <slot name="actions" />
            <Button
                type="submit"
                size="default"
                class="rounded-full bg-brand text-white hover:bg-brand-dark"
                :disabled="form.processing || !canSubmit()"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
